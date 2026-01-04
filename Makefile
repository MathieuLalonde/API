.DEFAULT_GOAL := help

build: 
	docker compose up -d --build

up:
	docker compose up -d $(filter-out $@,$(MAKECMDGOALS))

down:
	docker compose down $(filter-out $@,$(MAKECMDGOALS))

restart: down up

logs:
	docker compose logs -f $(filter-out $@,$(MAKECMDGOALS))

psql:
	docker compose --env-file .env exec db psql -U $(PG_USER) $(PG_DB)

flyway:
	docker compose --env-file .env run --rm flyway $(filter-out $@,$(MAKECMDGOALS))

reset-db:
	docker compose down -v
	docker compose up -d
	docker compose run --rm flyway migrate

# Ignore undefined targets to avoid errors
%:
	@:

help:
	@echo ""
	@echo "Available targets:"
	@echo "  make build             Build and start containers"
	@echo "  make build <service>   Build and start a specific service"
	@echo "  make up                Start containers"
	@echo "  make up <service>      Start a specific service"
	@echo "  make down              Stop containers"
	@echo "  make down <service>    Stop a specific service"
	@echo "  make restart           Restart containers"
	@echo "  make logs              Tail logs"
	@echo "  make logs <service>    Tail logs of a specific service"
	@echo "  make psql              Open psql shell"
	@echo "  make flyway            Run migrations"
	@echo "  make flyway <args>     Run flyway with specific arguments"
	@echo "  make reset-db          Recreate DB + migrate"
