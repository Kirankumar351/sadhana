.DEFAULT_GOAL := help
WEB := apps/web
AI  := apps/ai

.PHONY: help
help: ## Show this help
	@grep -hE '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

## ---------- environment ----------

.PHONY: up
up: ## Start mysql, redis, meilisearch, qdrant, minio, mailpit
	docker compose up -d
	@echo "Waiting for MySQL..."
	@until docker compose exec -T mysql mysqladmin ping -h localhost -proot --silent 2>/dev/null; do sleep 1; done
	@echo "Ready."

.PHONY: down
down: ## Stop everything
	docker compose down

.PHONY: reset
reset: ## Stop everything and DELETE all local data volumes
	docker compose down -v

.PHONY: install
install: ## Install PHP, JS and Python dependencies
	cd $(WEB) && composer install && npm install
	cd $(AI) && pip install -e ".[dev]"
	@test -f $(WEB)/.env || (cp $(WEB)/.env.example $(WEB)/.env && cd $(WEB) && php artisan key:generate)

## ---------- database ----------

.PHONY: migrate
migrate: ## Run pending migrations
	cd $(WEB) && php artisan migrate

.PHONY: fresh
fresh: ## Drop everything, migrate and seed
	cd $(WEB) && php artisan migrate:fresh --seed

.PHONY: glossary
glossary: ## Seed the glossary — do this before any AI work
	cd $(WEB) && php artisan db:seed --class=GlossarySeeder

## ---------- running ----------

.PHONY: dev
dev: ## Serve, queue worker and vite together
	cd $(WEB) && npx concurrently -k -n serve,queue,vite -c blue,magenta,green \
		"php artisan serve" \
		"php artisan queue:listen --queue=high,push,agents,default,low --tries=3" \
		"npm run dev"

.PHONY: queue
queue: ## Queue worker only, in priority order
	cd $(WEB) && php artisan queue:work redis --queue=high,push,agents,default,low --tries=3

.PHONY: ai
ai: ## Run the Python AI service
	cd $(AI) && uvicorn sadhana_ai.main:app --reload --port 8001

## ---------- quality ----------

.PHONY: check
check: lint stan test budget ## Everything CI runs

.PHONY: lint
lint: ## Formatting
	cd $(WEB) && ./vendor/bin/pint --test
	cd $(AI) && ruff check .

.PHONY: fix
fix: ## Fix formatting
	cd $(WEB) && ./vendor/bin/pint
	cd $(AI) && ruff check --fix .

.PHONY: stan
stan: ## Static analysis
	cd $(WEB) && ./vendor/bin/phpstan analyse --memory-limit=1G
	cd $(AI) && mypy sadhana_ai

.PHONY: test
test: ## Test suites
	cd $(WEB) && php artisan test --parallel
	cd $(AI) && pytest -q

.PHONY: budget
budget: ## Enforce the 100 KB first-load JS budget
	cd $(WEB) && npm run build && npm run budget

## ---------- AI and agents ----------

.PHONY: corpus
corpus: ## Rebuild the retrieval corpus from owner tables
	cd $(WEB) && php artisan corpus:reindex --all

.PHONY: agents-eval
agents-eval: ## Replay every agent golden set
	cd $(WEB) && php artisan agents:eval
