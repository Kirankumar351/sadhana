"""Service configuration.

Values come from the environment, which in practice means the same `.env` Laravel reads —
one source of truth for the keys, model names and the Qdrant collection, so the two halves
of the AI layer cannot drift apart on which model produced the vectors.
"""

from __future__ import annotations

from functools import lru_cache

from pydantic import AliasChoices, Field
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

    # Whichever key is set is the provider that works. See App\Services\AI\AiProvider.
    gemini_api_key: str = ""
    gemini_base_url: str = "https://generativelanguage.googleapis.com"
    gemini_model_embedding: str = "gemini-embedding-2"

    # Voyage issues its own keys; it is a separate company from Anthropic.
    voyage_api_key: str = ""

    # Voyage when its key is set, otherwise Gemini. Anthropic has no embedding model.
    ai_embedding_provider: str = "auto"

    # Small for routing and classification, large for anything a student reads.
    # The relevance classifier reads hundreds of articles a day; a large model there
    # costs roughly 20x for no measurable gain.
    ai_model_small: str = "claude-haiku-4-5"
    ai_model_large: str = "claude-sonnet-5"

    # Laravel's .env names this AI_MODEL_EMBEDDING; both spellings are read.
    embedding_model: str = Field(
        default="voyage-3",
        validation_alias=AliasChoices("AI_MODEL_EMBEDDING", "EMBEDDING_MODEL"),
    )
    embedding_dimensions: int = 1024

    qdrant_host: str = "http://127.0.0.1:6333"
    qdrant_api_key: str = ""
    qdrant_collection: str = "sadhana_chunks"

    # Batch size for the corpus backfill. Tuned down rather than up: a failed batch of 128
    # is cheap to retry, a failed batch of 1,000 is not.
    embed_batch_size: int = 100

    @property
    def service_token_is_set(self) -> bool:
        return bool(self.service_token)

    @property
    def embedding_provider(self) -> str | None:
        """The embedding provider that holds a key, honouring the preference when it can."""
        keyed = [name for name, key in (("voyage", self.voyage_api_key), ("gemini", self.gemini_api_key)) if key]
        preference = self.ai_embedding_provider.strip().lower()

        if preference in keyed:
            return preference

        return keyed[0] if keyed else None

    @property
    def active_embedding_model(self) -> str:
        return self.gemini_model_embedding if self.embedding_provider == "gemini" else self.embedding_model


@lru_cache
def get_settings() -> Settings:
    return Settings()
