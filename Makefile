# @NOTE: Makefile is used to manage running two docker compose setups:
# 	  1. running the released image on your machine
# 	  2. locally building the image and running it on your machine
#   Neither of these is a production setup.

.PHONY: publish-release build-release run-local run-release prepare clean

DATE := $(shell date "+%Y-%m-%d")

prepare:
	./docker-prepare-local.sh

clean:
	rm docker-compose.override.yml

build-release:
	docker build -t "aulaapp/aula-backend:latest" -t "aulaapp/aula-backend:$(DATE)" .

publish-release: build-release
	docker image push "aulaapp/aula-backend:$(DATE)"
	docker image push "aulaapp/aula-backend:latest"

run-release:
	docker compose -f docker-compose.yml pull
	docker compose -f docker-compose.yml up -d

run-local: prepare
	docker compose up -d --build
