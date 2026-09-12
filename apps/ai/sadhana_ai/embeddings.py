"""Embedding client.

Batched by default. The corpus backfill embeds tens of thousands of chunks, and one request
per chunk would be both slow and needlessly expensive.

The model name is recorded on every `ai_chunks` row by the caller. That matters more than it
looks: vectors produced by two different models are not comparable, so changing the
embedding model invalidates the entire corpus. Recording it is what lets `corpus:reindex`
find and re-embed everything that predates a change, instead of silently degrading
retrieval quality for months while nobody notices.
"""

from __future__ import annotations

import httpx
import structlog
from tenacity import retry, stop_after_attempt, wait_exponential

from sadhana_ai.config import Settings

log = structlog.get_logger()


class Embedder:
    def __init__(self, settings: Settings) -> None:
        self._settings = settings
        self.model = settings.embedding_model
        self.dimensions = settings.embedding_dimensions

    async def embed(self, text: str) -> list[float]:
        vectors = await self.embed_batch([text])
        return vectors[0]

    async def embed_batch(self, texts: list[str]) -> list[list[float]]:
        """Embed in chunks of `embed_batch_size`.

        Tuned down rather than up: a failed batch of 128 is cheap to retry, a failed batch
        of 1,000 is not.
        """
        out: list[list[float]] = []
        size = self._settings.embed_batch_size

        for start in range(0, len(texts), size):
            batch = texts[start : start + size]
            out.extend(await self._request(batch))

        return out

    @retry(
        stop=stop_after_attempt(4),
        wait=wait_exponential(multiplier=1, min=2, max=30),
        reraise=True,
    )
    async def _request(self, batch: list[str]) -> list[list[float]]:
        """One provider call, with backoff.

        Retried rather than failed because the backfill is a long-running job and a single
        transient 429 partway through should not cost the whole run.
        """
        async with httpx.AsyncClient(timeout=60.0) as client:
            response = await client.post(
                "https://api.voyageai.com/v1/embeddings",
                headers={"Authorization": f"Bearer {self._settings.anthropic_api_key}"},
                json={
                    "model": self.model,
                    "input": batch,
                    # Documents, not queries. Voyage scores asymmetrically and using the
                    # wrong type here quietly costs retrieval quality.
                    "input_type": "document",
                },
            )

            response.raise_for_status()
            payload = response.json()

        return [item["embedding"] for item in payload["data"]]
