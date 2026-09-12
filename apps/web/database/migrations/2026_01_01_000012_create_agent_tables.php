<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The agent layer.
 *
 * An "AI feature" answers one question and returns. An AGENT has a goal, picks its own
 * tools, runs multiple steps, and keeps state between runs. That difference is why agents
 * need their own tables: a feature needs a log line, an agent needs a trace.
 *
 * THE RULE IS UNCHANGED AND NON-NEGOTIABLE. An agent has exactly the same standing as any
 * other AI in this system: it drafts, a human decides. No agent writes to an owner table.
 * Every agent that produces student-visible content writes to `ai_drafts` and stops.
 * The tool registry enforces this — a tool is declared `writes_owner_table` or it is not,
 * and a tool that is may only be bound to a human-in-the-loop step.
 *
 * Four families, per docs/03-AI-AND-AGENTS.md:
 *   ops       — content autopilot: scraper watch, extraction, news curation, question
 *               generation, translation, SEO. Runs on a schedule, fills review queues.
 *   student   — one long-lived tutor agent per student, with memory of their weak areas,
 *               target exam and history. Read-only against owner tables.
 *   business  — ad-sales lead finding, campaign optimisation, churn and win-back, support
 *               triage, revenue anomaly watch. Proposes; a person sends.
 *   platform  — internal: corpus health, cost anomaly, broken-scraper triage, SLA watch.
 */
return new class extends Migration
{
    public function up(): void
    {
        /**
         * An agent is CONFIGURATION, not a class. Adding an agent must not require a deploy,
         * because the ones worth having are discovered while operating the product, not while
         * designing it. The executor is generic; the definition is data.
         */
        Schema::create('agent_definitions', function (Blueprint $t) {
            $t->id();
            $t->string('key', 80)->unique();          // news_curator | scraper_medic | tutor | ad_prospector
            $t->json('name');
            $t->text('description')->nullable();
            $t->enum('family', ['ops', 'student', 'business', 'platform']);

            $t->longText('goal');                     // the standing instruction
            $t->json('tools');                        // allowed tool keys — a hard allowlist
            $t->enum('model_tier', ['small', 'large'])->default('large');
            $t->unsignedSmallInteger('max_steps')->default(12);
            $t->unsignedInteger('max_cost_paise_per_run')->default(5000);
            $t->unsignedSmallInteger('timeout_sec')->default(300);

            /**
             * autonomy:
             *   propose  — writes to ai_drafts and stops. The default, and correct for
             *              anything a student will read.
             *   act      — may call tools that change non-student-facing state (reindex a
             *              chunk, retry a scraper, tag a lead).
             *   escalate — may notify a human and wait.
             * Nothing above `act` exists. There is no autonomy level that publishes.
             */
            $t->enum('autonomy', ['propose', 'act', 'escalate'])->default('propose');

            $t->boolean('is_active')->default(false); // ships OFF; enabled deliberately
            $t->string('schedule_cron', 60)->nullable();
            $t->json('trigger_events')->nullable();   // domain events that also wake it
            $t->timestamps();

            $t->index(['family', 'is_active'], 'idx_family_active');
        });

        /**
         * The tool registry. A tool an agent cannot see, it cannot call.
         *
         * `writes_owner_table` is the safety-critical flag: any tool that mutates a table
         * owning an eligibility criterion, a date, a fee or an answer key is marked true and
         * may only be bound behind human approval. This is checked in code at bind time,
         * not merely documented.
         */
        Schema::create('agent_tools', function (Blueprint $t) {
            $t->id();
            $t->string('key', 80)->unique();          // search_corpus | read_notification | draft_question | ...
            $t->string('name', 160);
            $t->text('description');                  // the model reads this — it is a prompt, write it well
            $t->json('input_schema');                 // JSON Schema, validated before execution
            $t->string('handler_class', 200);

            $t->boolean('writes_owner_table')->default(false);
            $t->boolean('requires_human_approval')->default(false);
            $t->boolean('is_destructive')->default(false);
            $t->unsignedSmallInteger('rate_limit_per_run')->default(20);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        /**
         * One row per agent execution. This is the audit trail, the cost record and the
         * debugging surface. An agent without a readable trace is an agent nobody will
         * trust enough to leave running.
         */
        Schema::create('agent_runs', function (Blueprint $t) {
            $t->id();
            $t->uuid()->unique();
            $t->foreignId('agent_definition_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();  // student agents only

            $t->enum('trigger', ['schedule', 'event', 'manual', 'chained']);
            $t->string('trigger_ref', 160)->nullable();
            $t->json('input')->nullable();

            $t->enum('status', ['queued', 'running', 'succeeded', 'failed', 'cancelled', 'halted'])
                ->default('queued');
            $t->string('halt_reason', 300)->nullable();   // budget | max_steps | guardrail | timeout

            $t->json('output')->nullable();
            $t->unsignedSmallInteger('steps_used')->default(0);
            $t->unsignedInteger('cost_paise')->default(0);
            $t->unsignedInteger('duration_ms')->nullable();
            $t->unsignedSmallInteger('drafts_created')->default(0);

            $t->text('error')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();

            $t->index(['agent_definition_id', 'created_at'], 'idx_agent_history');
            $t->index(['status', 'created_at'], 'idx_agentrun_status');
            $t->index(['user_id', 'created_at'], 'idx_user_runs');
        });

        Schema::create('agent_steps', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agent_run_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('step_index');
            $t->enum('type', ['think', 'tool_call', 'observation', 'answer']);

            $t->string('tool_key', 80)->nullable();
            $t->json('tool_input')->nullable();
            $t->json('tool_output')->nullable();
            $t->boolean('tool_error')->default(false);

            $t->longText('content')->nullable();       // reasoning or final answer
            $t->unsignedInteger('input_tokens')->default(0);
            $t->unsignedInteger('output_tokens')->default(0);
            $t->unsignedInteger('cost_paise')->default(0);
            $t->unsignedInteger('duration_ms')->nullable();
            $t->timestamps();

            $t->unique(['agent_run_id', 'step_index'], 'uk_step');
        });

        /**
         * Agent memory — what makes the student tutor a tutor rather than a chatbot.
         *
         * Scoped to (agent, user) so one student's memory can never leak into another's
         * context. `expires_at` matters: a note that someone was weak in Polity in January
         * is actively misleading in June, so memories decay unless refreshed.
         */
        Schema::create('agent_memories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agent_definition_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('scope', 60)->default('user');  // user | global
            $t->string('key', 120);
            $t->longText('value');
            $t->enum('kind', ['fact', 'preference', 'observation', 'summary'])->default('observation');
            $t->decimal('confidence', 4, 3)->nullable();
            $t->foreignId('source_run_id')->nullable()->constrained('agent_runs')->nullOnDelete();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();

            $t->unique(['agent_definition_id', 'user_id', 'key'], 'uk_memory');
            $t->index(['user_id', 'expires_at'], 'idx_live_memory');
        });

        /**
         * Evaluation sets. An agent that is not evaluated is an agent that silently rots
         * when a prompt changes. Every agent carries a small golden set, and the
         * `agents:eval` command replays it before a prompt or definition change ships.
         */
        Schema::create('agent_evals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agent_definition_id')->constrained()->cascadeOnDelete();
            $t->string('name', 160);
            $t->json('input');
            $t->json('expectations');                  // assertions: contains, refuses, cites, cost_under
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('agent_eval_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agent_eval_id')->constrained()->cascadeOnDelete();
            $t->foreignId('agent_run_id')->nullable()->constrained()->nullOnDelete();
            $t->boolean('passed');
            $t->json('failures')->nullable();
            $t->string('prompt_version', 20)->nullable();
            $t->timestamps();

            $t->index(['agent_eval_id', 'created_at'], 'idx_history');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_eval_runs');
        Schema::dropIfExists('agent_evals');
        Schema::dropIfExists('agent_memories');
        Schema::dropIfExists('agent_steps');
        Schema::dropIfExists('agent_runs');
        Schema::dropIfExists('agent_tools');
        Schema::dropIfExists('agent_definitions');
    }
};
