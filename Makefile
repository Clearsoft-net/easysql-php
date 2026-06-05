.PHONY: generate test lint typecheck build clean install

# ── Default target ───────────────────────────────────────────

help:
	@echo "EasySQL PHP SDK"
	@echo ""
	@echo "  make generate     Re-generate Client.php, Models, and docs from spec"
	@echo "  make lint         Check PHP syntax"
	@echo "  make test         Run PHPUnit tests"
	@echo "  make build        Run generate + lint + test"
	@echo "  make install      Install dependencies"
	@echo "  make clean        Remove generated files"

# ── Dependencies ─────────────────────────────────────────────

install:
	composer install --no-interaction

# ── Code generation ──────────────────────────────────────────

generate:
	php scripts/generate.php

# ── Linting ──────────────────────────────────────────────────

lint:
	@echo "Checking syntax..."
	@find src -name '*.php' -exec php -l {} \; | grep -v 'No syntax errors' || true
	@echo "Done."

# ── Testing ──────────────────────────────────────────────────

test:
	./vendor/bin/phpunit

# ── Full build ───────────────────────────────────────────────

build: generate lint test
	@echo "Build complete."

# ── Clean generated files ────────────────────────────────────

clean:
	rm -f src/Client.php
	rm -rf src/Models
	rm -f docs/API.md
	@echo "Cleaned generated files."
