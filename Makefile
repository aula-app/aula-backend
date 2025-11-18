# @NOTE: Makefile is used to manage running two docker compose setups:
# 	  1. running the released image on your machine
# 	  2. locally building the image and running it on your machine
#   Neither of these is a production setup.

.PHONY: publish-release build-release run-local run-release prepare clean

DATE := $(shell date "+%Y-%m-%d")

prepare-legacy:
	cd ./legacy && ./docker-prepare-local.sh

clean-legacy:
	rm ./legacy/docker-compose.override.yml

build-legacy-release:
	docker build -t -t "aulaapp/aula-backend:legacy-$(git rev-parse --short HEAD)" .

publish-legacy-release: build-legacy-release
	docker image push "aulaapp/aula-backend:legacy-$(git rev-parse --short HEAD)"

run-legacy-release:
	docker compose -f ./legacy/docker-compose.yml pull
	docker compose -f ./legacy/docker-compose.yml up -d

run-legacy-local: prepare-legacy
	docker compose -f ./legacy/docker-compose.yml -f ./legacy/docker-compose.override.yml up --build -d
