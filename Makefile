DCT := docker-compose -f traefik/docker-compose.yml
DCM := docker-compose -f magento2/docker-compose.yml
FPM := $(DCM) exec php-fpm

build:
	@$(DCT) pull
	@$(DCT) build --parallel
	@$(DCM) pull
	@$(DCM) build --parallel

status:
	@$(DCM) ps

start: start-traefik start-magento

stop: stop-magento stop-traefik

restart: stop start

start-traefik:
	@$(DCT) up -d

stop-traefik:
	@$(DCT) down

start-magento:
	@$(DCM) up -d

stop-magento:
	@$(DCM) down

ssh:
	@$(FPM) bash

composer-install:
	@$(FPM) composer install

magento:
	@$(FPM) bash ./bin/magento

#install-magento:
#	@$(FPM) install-magento
#
#install-sampledata:
#	@$(FPM) install-sampledata

dump-autoload:
	@$(FPM) composer dump-autoload
