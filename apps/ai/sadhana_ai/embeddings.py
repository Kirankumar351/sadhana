"""Embedding client.

Batched by default. The corpus backfill embeds tens of thousands of chunks, and one request
per chunk would be both slow and needlessly expensive.

WHICHEVER KEY IS SET IS THE PROVIDER USED: Voyage when VOYAGE_API_KEY is set, Gemini when
only GEMINI_API_KEY is. The Laravel side (App\\Services\\AI\\AiProvider) resolves the same way
from the same .env, so both halves embed with the same model.

The model name is recorded on every `ai_chunks` row by the caller. That matters more than it
looks: vectors produced by two different models are not comparable, so changing the
embedding model invalidates the entire corpus. Recording it is what lets `corpus:reindex`
find and re-embed everything that predates a change, instead of silently degrading
retrieval quality for months while nobody notices.

Queries and documents are embedded differently on both providers. Scoring is asymmetric, and
the wrong type quietly costs retrieval quality with nothing failing.
"""

from __future__ import annotations

import httpx
import structlog
from tenacity import retry, retry_if_exception, stop_after_attempt, wait_exponential

from sadhana_ai.config import Settings

log = structlog.get_logger()


def _retryable(error: BaseException) -> bool:
    """Retry transient failures only. A 4xx other than 429 is our bug; retrying burns quota."""
    if isinstance(error, httpx.HTTPStatusError):
        return error.response.status_code in (429, 500, 503)

    return isinstance(error, httpx.TransportError)


class Embedder:
    def __init__(self, settings: Settings) -> None:
        self._settings = settings
        self.provider = settings.embedding_provider
        self.model = settings.active_embedding_model
        self.dimensions = settings.embedding_dimensions

    async def embed(self, text: str) -> list[float]:
        """A student's question."""
        vectors = await self._request([text], query=True)
        return vectors[0]

    async def embed_batch(self, texts: list[str]) -> list[list[float]]:
        """Corpus chunks, in batches of `embed_batch_size`.

        Tuned down rather than up: a failed batch is cheap to retry, a failed batch of 1,000
        is not. Gemini's batch endpoint accepts at most 100 per request.
        """
        out: list[list[float]] = []
        size = min(self._settings.embed_batch_size, 100)

        for start in range(0, len(texts), size):
            out.extend(await self._request(texts[start : start + size], query=False))

        return out

    @retry(
        retry=retry_if_exception(_retryable),
        stop=stop_after_attempt(4),
        wait=wait_exponential(multiplier=1, min=2, max=30),
        reraise=True,
    )
    async def _request(self, batch: list[str], *, query: bool) -> list[list[float]]:
        """One provider call, with backoff on transient failures."""
        if self.provider == "voyage":
            return await self._voyage(batch, query=query)

        if self.provider == "gemini":
            return await self._gemini(batch, query=query)

        raise RuntimeError("No embedding key configured: set VOYAGE_API_KEY or GEMINI_API_KEY.")

    async def _voyage(self, batch: list[str], *, query: bool) -> list[list[float]]:
        async with httpx.AsyncClient(timeout=60.0) as client:
            response = await client.post(
                "https://api.voyageai.com/v1/embeddings",
                headers={"Authorization": f"Bearer {self._settings.voyage_api_key}"},
                json={
                    "model": self.model,
                    "input": batch,
                    "input_type": "query" if query else "document",
                },
            )
            response.raise_for_status()
            payload = response.json()

        return [item["embedding"] for item in payload["data"]]

    async def _gemini(self, batch: list[str], *, query: bool) -> list[list[float]]:
        url = f"{self._settings.gemini_base_url.rstrip('/')}/v1beta/models/{self.model}:batchEmbedContents"

        async with httpx.AsyncClient(timeout=60.0) as client:
            response = await client.post(
                url,
                headers={"x-goog-api-key": self._settings.gemini_api_key},
                json={
                    "requests": [
                        {
                            "model": f"models/{self.model}",
                            "content": {"parts": [{"text": text}]},
                            "taskType": "RETRIEVAL_QUERY" if query else "RETRIEVAL_DOCUMENT",
                            # The Qdrant collection's size. Cosine distance ignores the
                            # magnitude of truncated vectors, so no normalisation is needed.
                            "outputDimensionality": self.dimensions,
                        }
                        for text in batch
                    ]
                },
            )
            response.raise_for_status()
            payload = response.json()

        vectors = [[float(v) for v in item.get("values", [])] for item in payload.get("embeddings", [])]

        # One vector per input, in order, or none at all: a short response shifted by one would
        # attach every vector to the wrong chunk.
        if len(vectors) != len(batch):
            raise RuntimeError(f"Gemini returned {len(vectors)} embeddings for {len(batch)} inputs.")

        return vectors
