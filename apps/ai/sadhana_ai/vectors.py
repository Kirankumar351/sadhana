"""Qdrant vector store.

WHY QDRANT AND NOT MYSQL. The source design documents specify
`ai_chunks.embedding VECTOR(1536)` with a vector index. MySQL 8 has neither — the VECTOR
type arrived in MySQL 9. Rather than force a database upgrade the rest of the stack does
not need, MySQL stays the owner of record for chunk text and provenance, and vectors live
here keyed by `ai_chunks.id`.

The rule this preserves is the one that matters: the corpus is derived, never authored.
Nobody edits a point. Points are written by the embed job and removed when their owner row
goes away.
"""

from __future__ import annotations

from typing import Any

import structlog
from qdrant_client import AsyncQdrantClient, models

from sadhana_ai.config import Settings
from sadhana_ai.schemas import Hit

log = structlog.get_logger()


class QdrantStore:
    def __init__(self, settings: Settings) -> None:
        self._settings = settings
        self._client = AsyncQdrantClient(
            url=settings.qdrant_host,
            api_key=settings.qdrant_api_key or None,
        )
        self._collection = settings.qdrant_collection

    async def ping(self) -> bool:
        try:
            await self._client.get_collections()
        except Exception as exc:  # noqa: BLE001 — health check must never raise
            log.warning("qdrant.unreachable", error=str(exc))
            return False

        return True

    async def ensure_collection(self) -> None:
        """Create the collection if it is missing. Safe to call repeatedly.

        Cosine distance, because the embeddings are normalised and cosine is what the
        provider's similarity scores are calibrated against.
        """
        existing = await self._client.get_collections()

        if any(c.name == self._collection for c in existing.collections):
            return

        await self._client.create_collection(
            collection_name=self._collection,
            vectors_config=models.VectorParams(
                size=self._settings.embedding_dimensions,
                distance=models.Distance.COSINE,
            ),
        )

        # Filterable fields. Without these indexes a scoped search ("ask about this job")
        # degrades to a full scan, which is exactly the query shape used on the highest
        # traffic pages.
        for field in ("source_type", "source_id", "exam_id", "locale"):
            await self._client.create_payload_index(
                collection_name=self._collection,
                field_name=field,
                field_schema=models.PayloadSchemaType.KEYWORD,
            )

        log.info("qdrant.collection_created", collection=self._collection)

    async def upsert_many(
        self, items: list[tuple[int, list[float], dict[str, Any]]]
    ) -> None:
        await self.ensure_collection()

        await self._client.upsert(
            collection_name=self._collection,
            points=[
                models.PointStruct(id=chunk_id, vector=vector, payload=payload)
                for chunk_id, vector, payload in items
            ],
        )

    async def search(
        self, vector: list[float], limit: int, filters: dict[str, Any] | None = None
    ) -> list[Hit]:
        query_filter = self._build_filter(filters or {})

        results = await self._client.query_points(
            collection_name=self._collection,
            query=vector,
            limit=limit,
            query_filter=query_filter,
            with_payload=False,
        )

        return [Hit(id=int(point.id), score=float(point.score)) for point in results.points]

    async def delete_by_source(self, source_type: str, source_id: int) -> int:
        """Remove every point for one owner row.

        The deletion path a copyright takedown depends on. If material is removed but its
        vectors stay, the assistant keeps quoting it.
        """
        result = await self._client.delete(
            collection_name=self._collection,
            points_selector=models.FilterSelector(
                filter=models.Filter(
                    must=[
                        models.FieldCondition(
                            key="source_type", match=models.MatchValue(value=source_type)
                        ),
                        models.FieldCondition(
                            key="source_id", match=models.MatchValue(value=source_id)
                        ),
                    ]
                )
            ),
        )

        return int(getattr(result, "operation_id", 0) or 0)

    def _build_filter(self, filters: dict[str, Any]) -> models.Filter | None:
        conditions: list[models.Condition] = []

        for key in ("source_type", "source_id", "exam_id"):
            if key in filters:
                value = filters[key]

                if isinstance(value, list):
                    conditions.append(
                        models.FieldCondition(key=key, match=models.MatchAny(any=value))
                    )
                else:
                    conditions.append(
                        models.FieldCondition(key=key, match=models.MatchValue(value=value))
                    )

        # Telugu first, English as fallback — an English-only syllabus PDF is far better
        # than no answer for a Telugu-medium student.
        if locales := filters.get("locales"):
            conditions.append(
                models.FieldCondition(key="locale", match=models.MatchAny(any=locales))
            )

        return models.Filter(must=conditions) if conditions else None
