USER_NAME := $(shell id -un)
USER_ID := $(shell id -u)
GROUP_ID := $(shell id -g)
USER_GROUP = $(USER_ID):$(GROUP_ID)

export USER_ID
export GROUP_ID

start:
	docker-compose up -d
build:
	docker-compose build --no-cache
stop:
	docker-compose down
daily:
	make start && make composer && make install && make activate
delete-image:
	docker image rm dockware/play
debug-htaccess:
	docker exec -t shopware.test bash -c "echo \"SetEnv MAPP_CONNECT_CLIENT_DEBUG=debug\" >> .htaccess"
debug-env:
	docker exec -t shopware.test bash -c "echo \"MAPP_CONNECT_CLIENT_DEBUG=debug\" >> .env"
debug:
	make debug-env && make debug-htaccess
composer:
	docker exec -t shopware.test bash -c "composer require mappconnect/client"
install:
	docker exec -t shopware.test bash -c "./bin/console plugin:refresh && ./bin/console plugin:install --clearCache MappConnect"
activate:
	docker exec -t shopware.test bash -c "./bin/console plugin:activate --clearCache MappConnect"
get-version:
	@docker exec -t shopware.test php -r "require './vendor/composer/InstalledVersions.php';echo(Composer\InstalledVersions::getVersion('shopware/core'));"
exec-shopware:
	docker exec -it shopware.test bash

