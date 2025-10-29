up:
	docker compose up -d --build

down:
	docker compose down

bash:
	docker compose exec php bash

install:
	docker compose exec php bash -lc '\
	set -e; \
	if [ -f artisan ]; then \
	  echo "Laravel skeleton detected -> composer install"; \
	  composer install; \
	  php artisan key:generate --force; \
	else \
	  echo "No artisan found -> creating fresh Laravel skeleton"; \
	  rm -rf /tmp/laravel && mkdir -p /tmp/laravel; \
	  composer create-project laravel/laravel:^11.0 /tmp/laravel; \
	  tar -C /tmp/laravel --exclude=docker --exclude=docker-compose.yml --exclude=Makefile -cf - . | tar -C . -xf -; \
	  php artisan key:generate; \
	fi'

migrate:
	docker compose exec php php artisan migrate

seed:
	docker compose exec php php artisan deadlines:import --country=BE --file=config/deadlines/BE.yml && \
	docker compose exec php php artisan holidays:import --country=BE --file=config/holidays/BE.json

queue:
	docker compose exec php php artisan queue:work --stop-when-empty

schedule:
	docker compose exec php php artisan schedule:work
