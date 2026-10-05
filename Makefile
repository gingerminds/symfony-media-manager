# ======================================================
# Makefile Bundle
# ======================================================

# --------------------------------------
# Conteneurs Docker
# --------------------------------------
# var/ in Docker volumes: host caches and the macOS dart-sass binary are not reusable.
VAR_VOLUMES=-v gingerminds-media-manager-var:/app/var -v gingerminds-media-manager-app-var:/app/tests/Application/var
# CI image: gd, imagick and zip extensions.
IMAGE=--platform linux/amd64 registry.gitlab.gingerminds.fr/gm/docker-images/php-8.5-fpm-node-22-composer-2
PHP=docker run --rm -v $(PWD):/app $(VAR_VOLUMES) -w /app $(IMAGE)
COMPOSER=$(PHP) composer
CONSOLE=$(PHP) php tests/Application/bin/console

# --------------------------------------
# Setup
# --------------------------------------
install:
	$(COMPOSER) install

update:
	$(COMPOSER) update

# --------------------------------------
# Test application (tests/Application)
# --------------------------------------
assets:
	$(CONSOLE) importmap:install
	$(CONSOLE) sass:build

serve:
	php -S 127.0.0.1:8000 -t tests/Application/public tests/Application/public/index.php

# --------------------------------------
# Tools / Quality
# --------------------------------------
phpunit: assets
	$(PHP) ./vendor/bin/phpunit

phpstan:
	$(PHP) ./vendor/bin/phpstan analyse --memory-limit=1G

phpcs:
	$(PHP) ./vendor/bin/phpcs

php-cs-fixer:
	$(PHP) ./vendor/bin/php-cs-fixer fix

rector:
	$(PHP) ./vendor/bin/rector

fix-codestyle: rector php-cs-fixer

qa: phpstan phpcs phpunit

# --------------------------------------
# Alias pratique
# --------------------------------------
.PHONY: install update assets serve phpunit phpstan phpcs php-cs-fixer rector fix-codestyle qa
