.PHONY: help analyse build check clean db-down db-up generate install lint test test-integration

# ── Default target ───────────────────────────────────────────

help:
	@echo "EasySQL PHP SDK (multipackage)"
	@echo ""
	@echo "  make analyse          Run PHPStan static analysis"
	@echo "  make build            Run generate + lint + analyse + test"
	@echo "  make check            Verify packages/client was not hand-edited (regenerate + diff)"
	@echo "  make clean            Remove generated files"
	@echo "  make db-down          Stop and remove the local MySQL test container"
	@echo "  make db-up            Start the local MySQL test container (Docker Compose)"
	@echo "  make generate         Re-generate packages/client (Client, Models, docs) from spec"
	@echo "  make install          Install dependencies"
	@echo "  make lint             Check PHP syntax in every package"
	@echo "  make test             Run PHPUnit tests (all packages)"
	@echo "  make test-integration Run PHPUnit including MySQL integration tests (needs db-up)"

# ── Static analysis ──────────────────────────────────────────

analyse:
	./vendor/bin/phpstan analyse --no-progress --memory-limit=1G

# ── Full build ───────────────────────────────────────────────

build: generate lint analyse test
	@echo "Build complete."

# ── Generated-package hand-edit guard ────────────────────────

check: generate
	@modified=$$(git diff --name-only -- packages/client/src packages/client/docs); \
	untracked=$$(git ls-files --others --exclude-standard -- packages/client/src packages/client/docs); \
	if [ -n "$$modified" ] || [ -n "$$untracked" ]; then \
		echo "❌ packages/client changed — hand-edited or spec out of date."; \
		[ -n "$$modified" ] && echo "$$modified"; \
		[ -n "$$untracked" ] && echo "$$untracked"; \
		exit 1; \
	fi; \
	echo "packages/client is in sync with the spec."

# ── Clean generated files ────────────────────────────────────

clean:
	rm -f packages/client/src/Client.php
	rm -rf packages/client/src/Models
	rm -f packages/client/docs/API.md
	@echo "Cleaned generated files."

# ── Local databases ──────────────────────────────────────────

db-up:
	docker compose up -d --wait mysql postgres
	@echo "MySQL ready on 127.0.0.1:3306 and PostgreSQL on 127.0.0.1:5433."

db-down:
	docker compose down -v

# ── Code generation ──────────────────────────────────────────

generate:
	php scripts/generate.php

# ── Dependencies ─────────────────────────────────────────────

install:
	composer install --no-interaction

# ── Linting ──────────────────────────────────────────────────

lint:
	@echo "Checking syntax..."
	@find packages scripts samples -name '*.php' -exec php -l {} \; | grep -v 'No syntax errors' || true
	@echo "Done."

# ── Testing ──────────────────────────────────────────────────

test:
	./vendor/bin/phpunit

test-integration: db-up
	EASYSQL_TEST_MYSQL=1 EASYSQL_TEST_POSTGRES=1 ./vendor/bin/phpunit
