# @NOTE: Makefile is used to manage running two docker compose setups:
# 	  1. running the released image on your machine
# 	  2. locally building the image and running it on your machine
#   Neither of these is a production setup.

.PHONY: publish-legacy-release build-legacy-release run-legacy-local run-legacy-release prepare-legacy-local clean-legacy-local

DATE := $(shell date "+%Y-%m-%d")
DOCKER_TAG := $(shell git rev-parse --short HEAD)

prepare-legacy-local:
	cd ./legacy && ./docker-prepare-local.sh

clean-legacy-local:
	rm ./legacy/docker-compose.override.yml

run-legacy-local: prepare-legacy-local
	docker compose -f ./legacy/docker-compose.yml build --build-arg DOCKER_TAG=$(DOCKER_TAG)
	docker compose -f ./legacy/docker-compose.yml -f ./legacy/docker-compose.override.yml up -d

build-legacy-release:
	docker build -t "aulaapp/aula-backend:legacy-$(git rev-parse --short HEAD)" .

publish-legacy-release: build-legacy-release
	docker image push "aulaapp/aula-backend:legacy-$(git rev-parse --short HEAD)"

run-legacy-release:
	docker compose -f ./legacy/docker-compose.yml pull
	docker compose -f ./legacy/docker-compose.yml up -d
