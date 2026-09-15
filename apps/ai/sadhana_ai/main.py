"""Sadhana AI service.

Embeddings, vector retrieval, and the agent runtime — the parts of the AI layer where
Python is genuinely the better tool. Everything student-facing still goes through the
Laravel `AiGateway`, which owns the cost caps, the refusal log, the prompt versions and the
output filters.

WHAT THIS SERVICE MUST NEVER DO
-------------------------------
It has no opinion about eligibility, dates, fees or answer keys. It does not write to any
owner table. It embeds text, searches vectors, and runs agent loops whose output lands in
`ai_drafts` for a human to approve. If a change to this service would let it publish
something a student reads, that change belongs in Laravel behind a review queue instead.

See docs/03-AI-AND-AGENTS.md.
"""

from __future__ import annotations

import structlog
from fastapi import Depends, FastAPI, HTTPException, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

from sadhana_ai.config import Settings, get_settings
from sadhana_ai.schemas import (
    EmbedRequest,
    EmbedResponse,
    HealthResponse,
    SearchRequest,
    SearchResponse,
)

log = structlog.get_logger()

app = FastAPI(
    title="Sadhana AI",
    version="0.1.0",
    docs_url="/docs",
)

bearer = HTTPBearer(auto_error=True)


def authenticate(
    credentials: HTTPAuthorizationCredentials = Depends(bearer),
    settings: Settings = Depends(get_settings),
) -> None:
    """Shared-secret auth.

    This service is never exposed publicly — it listens on localhost or inside the private
    network, and Laravel is its only client. The token is a second lock, not the first.
    """
    if credentials.credentials != settings.service_token:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid service token",
        )


@app.get("/health", response_model=HealthResponse)
async def health(settings: Settings = Depends(get_settings)) -> HealthResponse:
    """Liveness, and whether the vector store is reachable.

    Reported separately because the service being up while Qdrant is down is precisely the
    state that produces confident empty answers — retrieval returns nothing, and without
    this signal the cause looks like a corpus gap rather than an outage.
    """
    from sadhana_ai.vectors import QdrantStore

    store = QdrantStore(settings)

    return HealthResponse(
        ok=True,
        vector_store_reachable=await store.ping(),
        embedding_model=settings.active_embedding_model,
    )


@app.post("/embed", response_model=EmbedResponse, dependencies=[Depends(authenticate)])
async def embed(request: EmbedRequest, settings: Settings = Depends(get_settings)) -> EmbedResponse:
    """Embed a batch of chunks and upsert them into the vector store.

    Batched because the corpus backfill embeds tens of thousands of chunks, and one request
    per chunk would be both slow and needlessly expensive.
    """
    from sadhana_ai.embeddings import Embedder
    from sadhana_ai.vectors import QdrantStore

    embedder = Embedder(settings)
    store = QdrantStore(settings)

    vectors = await embedder.embed_batch([chunk.content for chunk in request.chunks])

    await store.upsert_many(
        [
            (chunk.chunk_id, vector, chunk.payload)
            for chunk, vector in zip(request.chunks, vectors, strict=True)
        ]
    )

    log.info("embedded", count=len(request.chunks), model=embedder.model)

    return EmbedResponse(
        embedded=len(request.chunks),
        model=embedder.model,
        dimensions=embedder.dimensions,
    )


@app.post("/search", response_model=SearchResponse, dependencies=[Depends(authenticate)])
async def search(
    request: SearchRequest, settings: Settings = Depends(get_settings)
) -> SearchResponse:
    """Vector search over the corpus.

    Returns chunk ids and scores only — never content. Laravel joins back to `ai_chunks`,
    which is the owner of record, so a chunk deleted in MySQL can never be served from here
    even if its Qdrant point has not been swept yet. That ordering is what makes a copyright
    takedown safe.
    """
    from sadhana_ai.embeddings import Embedder
    from sadhana_ai.vectors import QdrantStore

    embedder = Embedder(settings)
    store = QdrantStore(settings)

    vector = await embedder.embed(request.query)
    hits = await store.search(vector, limit=request.limit, filters=request.filters)

    return SearchResponse(hits=hits)


@app.delete("/chunks/{source_type}/{source_id}", dependencies=[Depends(authenticate)])
async def delete_source(
    source_type: str, source_id: int, settings: Settings = Depends(get_settings)
) -> dict[str, int]:
    """Remove every point belonging to one owner row.

    The deletion path a copyright takedown depends on. If material is removed but its
    vectors stay, the assistant keeps quoting it and the takedown is incomplete in exactly
    the way that matters legally.
    """
    from sadhana_ai.vectors import QdrantStore

    store = QdrantStore(settings)
    removed = await store.delete_by_source(source_type, source_id)

    log.info("chunks.deleted", source_type=source_type, source_id=source_id, removed=removed)

    return {"removed": removed}
