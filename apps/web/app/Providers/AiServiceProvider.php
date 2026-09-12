<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Answer;
use App\Models\Exam;
use App\Models\ExamNotification;
use App\Models\Material;
use App\Models\NewsItem;
use App\Models\Profile;
use App\Observers\CorpusObserver;
use App\Observers\ProfileObserver;
use App\Services\AI\AnthropicClient;
use App\Services\AI\Contracts\EmbeddingClient;
use App\Services\AI\Contracts\ModelClient;
use App\Services\AI\Contracts\VectorStore;
use App\Services\AI\QdrantStore;
use App\Services\AI\VoyageEmbedder;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the AI layer.
 *
 * Everything here is bound to an interface so the provider is a swappable decision. That
 * matters more than usual for the vector store: the source documents assumed a MySQL VECTOR
 * column that MySQL 8 does not have, so moving to pgvector or MySQL 9 later should be one
 * class rather than a migration of every AI feature.
 */
class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModelClient::class, AnthropicClient::class);
        $this->app->singleton(EmbeddingClient::class, VoyageEmbedder::class);

        $this->app->singleton(VectorStore::class, fn () => match (config('ai.vector.driver')) {
            'qdrant' => new QdrantStore,
            default => new QdrantStore,
        });
    }

    public function boot(): void
    {
        /**
         * The corpus is derived from these five tables and nothing else.
         *
         * Registering them in one place is deliberate: a sixth corpus-bearing model added
         * later without an observer would silently never be retrievable, and the symptom
         * would be an assistant that says "I don't have this" about content that plainly
         * exists.
         */
        foreach ([ExamNotification::class, Exam::class, Material::class, NewsItem::class, Answer::class] as $model) {
            $model::observe(CorpusObserver::class);
        }

        // A changed qualification changes every eligibility result on the feed.
        Profile::observe(ProfileObserver::class);
    }
}
