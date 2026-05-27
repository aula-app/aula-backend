# @NOTE: Makefile is used to manage running two docker compose setups:
# 	  1. running the released image on your machine
# 	  2. locally building the image and running it on your machine
#   Neither of these is a production setup.

.PHONY: publish-legacy-release build-legacy-release run-legacy-local run-legacy-release prepare-legacy-local clean-legacy-local test

DATE := $(shell date "+%Y-%m-%d")
DOCKER_TAG := $(shell git rev-parse --short HEAD)

prepare-legacy-local:
	cd ./legacy && ./docker-prepare-local.sh

clean-legacy-local:
	rm ./legacy/docker-compose.override.yml

run-legacy-local: prepare-legacy-local
	docker compose -f ./legacy/docker-compose.yml build --build-arg DOCKER_TAG=$(DOCKER_TAG)
	docker compose -f ./legacy/docker-compose.yml up -d

build-legacy-release:
	docker build -t "aulaapp/aula-backend:legacy-$(git rev-parse --short HEAD)" .

publish-legacy-release: build-legacy-release
	docker image push "aulaapp/aula-backend:legacy-$(git rev-parse --short HEAD)"

run-legacy-release:
	docker compose -f ./legacy/docker-compose.yml pull
	docker compose -f ./legacy/docker-compose.yml up -d

test:
	docker compose -f docker-compose.test.yml down
	docker compose -f docker-compose.test.yml up --build --abort-on-container-exit --exit-code-from app-test

# Simplistic tasks that mirror the scans in .github/workflows/main-pr-vuln-scan.yml

.PHONY: trivy
trivy: trivy-legacy-image
trivy: trivy-image
trivy: trivy-fs

.PHONY: trivy-legacy-image
trivy-legacy-image:
	trivy image aula-v2-aula-backend-legacy:latest

.PHONY: trivy-image
trivy-image:
	trivy image aula-v2-aula-backend.v2:latest

.PHONY: trivy-fs
trivy-fs:
	trivy fs .

# your systems php might be a different version than CI's (cf. psalm-docker)
# subtle differences w.r.t. deprecations might show up as psalm errors
.PHONY: psalm-cli
psalm-cli:
	./vendor/bin/psalm

# tries to mirror .github/workflow/main-pr-scan-psalm.yml
# --taint-analysis is unnecessary with psalm-laravel
.PHONY: psalm-docker
psalm-docker:
	docker run \
		-u $(shell id -u):$(shell id -g) \
		-v ${PWD}:/app \
		--rm \
		ghcr.io/danog/psalm:7.0.0-beta19 \
		sh -c \
			"composer install --ignore-platform-reqs ; \
			 /app/vendor/bin/psalm \
				--no-cache \
				--output-format=github \
				--report=/app/psalm-results.sarif"
