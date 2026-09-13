<?php

use App\Providers\AgentServiceProvider;
use App\Providers\AiServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,

    /**
     * Binds the model, embedding and vector clients, and registers the observers that keep
     * the retrieval corpus in step with the tables that own the facts.
     */
    AiServiceProvider::class,

    /**
     * The agent tool registry — a hard allowlist. A tool not registered here cannot
     * be called by any agent.
     */
    AgentServiceProvider::class,

    AdminPanelProvider::class,
];
