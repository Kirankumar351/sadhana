"""Service configuration.

Values come from the environment, which in practice means the same `.env` Laravel reads —
one source of truth for the model names and the Qdrant collection, so the two halves of the
AI layer cannot drift apart on which model produced the vectors.
"""

from __future__ import annotations

from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=("../web/.env", ".env"),
        env_file_encoding="utf-8",
        extra="ignore",
    )

    service_token: str = ""

    anthropic_api_key: str = ""
    anthropic_base_url: str = "https://api.anthropic.com"

    # Small for routing and classification, large for anything a student reads.
    # The relevance classifier reads hundreds of articles a day; a large model there
    # costs roughly 20x for no measurable gain.
    ai_model_small: str = "claude-haiku-4-5-20251001"
    ai_model_large: str = "claude-sonnet-5"

    embedding_model: str = "voyage-3"
    embedding_dimensions: int = 1024

    qdrant_host: str = "http://127.0.0.1:6333"
    qdrant_api_key: str = ""
    qdrant_collection: str = "sadhana_chunks"

    # Batch size for the corpus backfill. Tuned down rather than up: a failed batch of 128
    # is cheap to retry, a failed batch of 1,000 is not.
    embed_batch_size: int = 128

    @property
    def service_token_is_set(self) -> bool:
        return bool(self.service_token)


@lru_cache
def get_settings() -> Settings:
    return Settings()
