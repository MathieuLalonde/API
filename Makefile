.DEFAULT_GOAL := help

# Build images (optionally for a specific service)
# Usage: make build [service]
build:
	docker compose build $(filter-out $@,$(MAKECMDGOALS))

# Build and start all services with dependencies installed
build-all: build
	$(MAKE) up
	$(MAKE) install

install:
	docker compose exec php composer install

# To install or update dependencies, run:
# docker compose run --rm php composer install

# Start services (optionally for a specific service)
# Usage: make up [service]
up:
	docker compose up -d $(filter-out $@,$(MAKECMDGOALS))

down:
	docker compose down $(filter-out $@,$(MAKECMDGOALS))

restart:
	$(MAKE) down
	$(MAKE) up

logs:
	docker compose logs -f $(filter-out $@,$(MAKECMDGOALS))

# Database access
psql:
	docker compose --env-file .env exec db psql -U $(PG_USER) $(PG_DB)

# Flyway migrations
flyway:
	docker compose --env-file .env run --rm flyway $(filter-out $@,$(MAKECMDGOALS))

migrate: flyway

# Wait for database to be ready
_internal_wait_db:
	@echo "Waiting for Postgres to be ready..."
	@if ! docker compose exec -T db sh -c 'until pg_isready -h localhost -U "$$POSTGRES_USER" -d "$$POSTGRES_DB" >/dev/null 2>&1; do echo "Waiting for Postgres..."; sleep 1; done'; then \
		echo "ERROR: Database failed to become ready"; \
		docker compose logs db; \
		exit 1; \
	fi
	@echo "Database is ready!"

# Database backup and restore
backup-db:
	@echo "Creating backup..."
	docker compose exec -T db pg_dump -U $(PG_USER) $(PG_DB) > backup_$(shell date +%Y%m%d_%H%M%S).sql
	@echo "Backup created: backup_$(shell date +%Y%m%d_%H%M%S).sql"

restore-db:
	@read -p "Enter backup file path: " file; \
	docker compose exec -T db psql -U $(PG_USER) $(PG_DB) < $$file

# Reset database (drop DB volume only, recreate, migrate) and restart all services
# WARNING: This will delete all data in the database!
# Note: vendor_data volume is preserved to avoid re-installing dependencies
reset-db:
	@echo "Resetting database..."
	docker compose down db
	docker volume rm api_pgdata 2>/dev/null || true
	docker compose up -d db
	$(MAKE) _internal_wait_db
	docker compose --env-file .env run --rm flyway migrate
	@echo "Database reset complete!"

# Import DVD Profiler XML file
# Usage: make import <filename> [NON_STREAMING=1]
# File must be in manual_imports/ directory (mounted in container)
# Set NON_STREAMING=1 to use full file load instead of streaming (for testing)
import-dvd:
	@if [ -z "$(filter-out $@,$(MAKECMDGOALS))" ]; then \
		echo "Error: File name required"; \
		echo "Usage: make import-dvd <filename.xml> [NON_STREAMING=1]"; \
		echo "File must be in manual_imports/ directory"; \
		echo "Set NON_STREAMING=1 to use full file load (for testing)"; \
		exit 1; \
	fi
	@MSYS_NO_PATHCONV=1 docker compose exec php sh -c 'php bin/import_dvdprofiler.php /var/www/manual_imports/$(filter-out $@,$(MAKECMDGOALS)) $(if $(NON_STREAMING),--non-streaming,)'

import-discogs:
	@MSYS_NO_PATHCONV=1 docker compose exec php sh -c 'php bin/import_discogs.php'


# Ignore undefined targets to avoid errors
%:
	@:

help:
	@echo ""
	@echo "DVD Collection API - Local Development & Docker Management"
	@echo ""
	@echo "=== Setup & Build ==="
	@echo "  make build-all                 Build all images, start containers, and install deps"
	@echo "  make build [service]           Build images (all services, or specific service)"
	@echo "  make install                   Install PHP dependencies (Composer)"
	@echo "  make up [service]              Start all containers (or specific service)"
	@echo "  make down [service]            Stop containers (or specific service)"
	@echo "  make restart                   Restart all containers"
	@echo ""
	@echo "=== Docker Services ==="
	@echo "  make logs <service>            Tail logs for all services (or specific service)"
	@echo ""
	@echo "=== Database & Migrations ==="
	@echo "  make psql                      Open psql shell to database"
	@echo "  make migrate                   Run Flyway migrations"
	@echo "  make flyway [args]             Run Flyway command (e.g., info, validate, repair)"
	@echo "  make backup-db                 Backup database to SQL file"
	@echo "  make restore-db                Restore database from SQL file"
	@echo "  make reset-db                  Drop DB volume, recreate DB, and run migrations"
	@echo ""
	@echo "=== Data Import ==="
	@echo "  make import <file>             Import DVD Profiler XML file"
	@echo ""
