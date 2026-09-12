"""Request and response shapes.

Deliberately narrow. This service does not accept or return chunk *content* on the search
path — only ids and scores. Laravel joins back to `ai_chunks`, which is the owner of record,
so a row deleted in MySQL can never be served from here even if its Qdrant point survives a
moment longer. That ordering is what makes a takedown safe.
"""

from __future__ import annotations

from typing import Any

from pydantic import BaseModel, Field


class ChunkPayload(BaseModel):
    """One chunk to embed and store.

    `chunk_id` is `ai_chunks.id` and becomes the Qdrant point id — the two are the same
    number by design, so there is no mapping table to fall out of sync.
    """

    chunk_id: int
    content: str
    payload: dict[str, Any] = Field(default_factory=dict)


class EmbedRequest(BaseModel):
    chunks: list[ChunkPayload] = Field(min_length=1, max_length=256)


class EmbedResponse(BaseModel):
    embedded: int
    model: str
    dimensions: int


class SearchRequest(BaseModel):
    query: str = Field(min_length=1)
    limit: int = Field(default=12, ge=1, le=100)

    # Scope filters: source_type, source_id, exam_id, locales.
    # "Ask about this job" from a notification page must not retrieve a different
    # notification — answering about the wrong vacancy is worse than not answering.
    filters: dict[str, Any] = Field(default_factory=dict)


class Hit(BaseModel):
    id: int
    score: float


class SearchResponse(BaseModel):
    hits: list[Hit]


class HealthResponse(BaseModel):
    ok: bool
    vector_store_reachable: bool
    embedding_model: str
