.PHONY: up up-mysql up-es down down-es wait wait-mysql wait-es test coverage test-es8 test-es9 test-matrix test-full-matrix clean help build-images

.DEFAULT_GOAL := help

# Colors
GREEN  := \033[92m
YELLOW := \033[93m
RED    := \033[91m
CYAN   := \033[36m
RESET  := \033[0m

# MySQL config
MYSQL_VERSION ?= 8.0
MYSQL_CONTAINER_NAME := elastic-query-wizard-mysql
MYSQL_HOST_PORT := 23306
MYSQL_DATABASE := test
MYSQL_USER := test
MYSQL_PASSWORD := test

# Elasticsearch config
ES_VERSION ?= 9.3.0
ES_CONTAINER_NAME := elastic-query-wizard-elasticsearch
ES_HOST_PORT := 29200
ES_IMAGE := elasticsearch

# Supported versions for matrix testing
ES_VERSIONS := 8.19.11 9.3.0
PHP_VERSIONS := 8.1 8.2 8.3 8.4
LARAVEL_VERSIONS := 10 11 12

# Docker image for matrix testing
DOCKER_IMAGE_PREFIX := elastic-query-wizard-php
COMPOSER_CACHE_VOLUME := elastic-query-wizard-composer-cache

# Extra arguments for phpunit (use: make test ARGS="--filter=testName")
ARGS ?=

##@ Docker

up: up-mysql up-es ## Start MySQL and Elasticsearch containers

up-mysql: ## Start MySQL container
	@if docker ps --format '{{.Names}}' | grep -q "^$(MYSQL_CONTAINER_NAME)$$"; then \
		printf "$(GREEN)✔ $(MYSQL_CONTAINER_NAME) already running$(RESET)\n"; \
	else \
		printf "$(YELLOW)→ Starting $(MYSQL_CONTAINER_NAME) container$(RESET)\n"; \
		docker run --rm -d \
			--name $(MYSQL_CONTAINER_NAME) \
			-p $(MYSQL_HOST_PORT):3306 \
			-e MYSQL_RANDOM_ROOT_PASSWORD=yes \
			-e MYSQL_DATABASE=$(MYSQL_DATABASE) \
			-e MYSQL_USER=$(MYSQL_USER) \
			-e MYSQL_PASSWORD=$(MYSQL_PASSWORD) \
			mysql:$(MYSQL_VERSION) \
			--default-authentication-plugin=mysql_native_password; \
		printf "$(GREEN)✔ $(MYSQL_CONTAINER_NAME) started$(RESET)\n"; \
	fi

up-es: ## Start Elasticsearch container
	@if docker ps --format '{{.Names}}' | grep -q "^$(ES_CONTAINER_NAME)$$"; then \
		printf "$(GREEN)✔ $(ES_CONTAINER_NAME) already running$(RESET)\n"; \
	else \
		printf "$(YELLOW)→ Starting $(ES_CONTAINER_NAME) container (ES $(ES_VERSION))$(RESET)\n"; \
		docker run --rm -d \
			--name $(ES_CONTAINER_NAME) \
			-p $(ES_HOST_PORT):9200 \
			-e discovery.type=single-node \
			-e xpack.security.enabled=false \
			-e ES_JAVA_OPTS="-Xms512m -Xmx512m" \
			$(ES_IMAGE):$(ES_VERSION); \
		printf "$(GREEN)✔ $(ES_CONTAINER_NAME) started$(RESET)\n"; \
	fi

down: ## Stop all containers
	@printf "$(YELLOW)→ Stopping containers$(RESET)\n"
	@-docker stop $(MYSQL_CONTAINER_NAME) 2>/dev/null || true
	@-docker stop $(ES_CONTAINER_NAME) 2>/dev/null || true
	@printf "$(GREEN)✔ Containers stopped$(RESET)\n"

down-es: ## Stop Elasticsearch container only
	@-docker stop $(ES_CONTAINER_NAME) 2>/dev/null || true

wait: wait-mysql wait-es ## Wait until containers are ready

wait-mysql: ## Wait until MySQL is ready
	@printf "$(YELLOW)→ Waiting for $(MYSQL_CONTAINER_NAME)$(RESET)\n"
	@until docker exec $(MYSQL_CONTAINER_NAME) mysqladmin -u $(MYSQL_USER) -p$(MYSQL_PASSWORD) -h 127.0.0.1 ping 2>/dev/null; do \
		printf "$(RED)✘ $(MYSQL_CONTAINER_NAME) not ready, waiting...$(RESET)\n"; \
		sleep 3; \
	done
	@printf "$(GREEN)✔ $(MYSQL_CONTAINER_NAME) ready$(RESET)\n"

wait-es: ## Wait until Elasticsearch is ready
	@printf "$(YELLOW)→ Waiting for $(ES_CONTAINER_NAME)$(RESET)\n"
	@until curl -fsS "127.0.0.1:$(ES_HOST_PORT)/_cluster/health?wait_for_status=yellow&timeout=60s" >/dev/null 2>&1; do \
		printf "$(RED)✘ $(ES_CONTAINER_NAME) not ready, waiting...$(RESET)\n"; \
		sleep 3; \
	done
	@printf "$(GREEN)✔ $(ES_CONTAINER_NAME) ready$(RESET)\n"

clean: ## Remove all containers and volumes
	@printf "$(YELLOW)→ Cleaning up$(RESET)\n"
	@-docker stop $(MYSQL_CONTAINER_NAME) 2>/dev/null || true
	@-docker stop $(ES_CONTAINER_NAME) 2>/dev/null || true
	@-docker rm -f $(MYSQL_CONTAINER_NAME) 2>/dev/null || true
	@-docker rm -f $(ES_CONTAINER_NAME) 2>/dev/null || true
	@printf "$(GREEN)✔ Cleanup complete$(RESET)\n"

build-images: ## Build Docker images for matrix testing
	@printf "$(YELLOW)→ Building Docker images for matrix testing$(RESET)\n"
	@for php_version in $(PHP_VERSIONS); do \
		printf "$(CYAN)▶ Building $(DOCKER_IMAGE_PREFIX):$$php_version$(RESET)\n"; \
		docker build -q -t $(DOCKER_IMAGE_PREFIX):$$php_version \
			--build-arg PHP_VERSION=$$php_version \
			docker/; \
	done
	@docker volume create $(COMPOSER_CACHE_VOLUME) >/dev/null 2>&1 || true
	@printf "$(GREEN)✔ Images built$(RESET)\n"

##@ Testing

test: ## Run all tests (ARGS="--filter=testName")
	@printf "$(YELLOW)→ Running all tests$(RESET)\n"
	@vendor/bin/phpunit --testdox --colors=always $(ARGS)
	@printf "$(GREEN)✔ Tests completed$(RESET)\n"

coverage: ## Run tests with coverage (ARGS="--filter=testName")
	@printf "$(YELLOW)→ Running tests with coverage$(RESET)\n"
	@XDEBUG_MODE=coverage vendor/bin/phpunit --testdox --coverage-text --colors=always $(ARGS)
	@printf "$(GREEN)✔ Coverage report generated$(RESET)\n"

test-es8: ## Run tests with Elasticsearch 8.x
	@$(MAKE) down
	@composer update --with="elasticsearch/elasticsearch:^8.0" --no-interaction --no-progress
	@ES_VERSION=8.19.11 $(MAKE) up wait test
	@$(MAKE) down

test-es9: ## Run tests with Elasticsearch 9.x
	@$(MAKE) down
	@composer update --with="elasticsearch/elasticsearch:^9.0" --no-interaction --no-progress
	@ES_VERSION=9.3.0 $(MAKE) up wait test
	@$(MAKE) down

test-matrix: ## Run tests on all Elasticsearch versions (current PHP)
	@printf "$(CYAN)════════════════════════════════════════$(RESET)\n"
	@printf "$(CYAN)  Running ES matrix$(RESET)\n"
	@printf "$(CYAN)════════════════════════════════════════$(RESET)\n"
	@for es_version in $(ES_VERSIONS); do \
		printf "\n$(YELLOW)▶ Testing with Elasticsearch $$es_version$(RESET)\n"; \
		es_major=$$(echo $$es_version | cut -d. -f1); \
		composer update --with="elasticsearch/elasticsearch:^$$es_major.0" --no-interaction --no-progress; \
		$(MAKE) down 2>/dev/null || true; \
		ES_VERSION=$$es_version $(MAKE) up wait test || exit 1; \
		$(MAKE) down; \
		printf "$(GREEN)✔ ES $$es_version passed$(RESET)\n"; \
	done
	@printf "\n$(GREEN)════════════════════════════════════════$(RESET)\n"
	@printf "$(GREEN)  All ES matrix tests passed!$(RESET)\n"
	@printf "$(GREEN)════════════════════════════════════════$(RESET)\n"

test-full-matrix: build-images ## Run full test matrix (PHP × Laravel × ES) via Docker
	@printf "$(CYAN)════════════════════════════════════════════════════════════$(RESET)\n"
	@printf "$(CYAN)  Running full test matrix (PHP × Laravel × Elasticsearch)$(RESET)\n"
	@printf "$(CYAN)════════════════════════════════════════════════════════════$(RESET)\n"
	@$(MAKE) down 2>/dev/null || true
	@$(MAKE) up-mysql wait-mysql
	@passed=0; failed=0; skipped=0; \
	for es_version in $(ES_VERSIONS); do \
		es_major=$$(echo $$es_version | cut -d. -f1); \
		printf "\n$(CYAN)━━━ Starting Elasticsearch $$es_version ━━━$(RESET)\n"; \
		ES_VERSION=$$es_version $(MAKE) up-es wait-es; \
		for php_version in $(PHP_VERSIONS); do \
			for laravel_version in $(LARAVEL_VERSIONS); do \
				if [ "$$php_version" = "8.1" ] && [ "$$laravel_version" != "10" ]; then \
					printf "$(YELLOW)⊘ PHP $$php_version / Laravel $$laravel_version / ES $$es_version - skipped (Laravel $$laravel_version requires PHP 8.2+)$(RESET)\n"; \
					skipped=$$((skipped + 1)); \
					continue; \
				fi; \
				printf "\n$(CYAN)▶ PHP $$php_version / Laravel $$laravel_version / ES $$es_version$(RESET)\n"; \
				case $$laravel_version in \
					10) testbench_version=8 ;; \
					11) testbench_version=9 ;; \
					12) testbench_version=10 ;; \
				esac; \
				if docker run --rm \
					--network host \
					-v "$$(pwd):/src:ro" \
					-v $(COMPOSER_CACHE_VOLUME):/root/.composer/cache \
					-w /app \
					-e ELASTIC_HOST=127.0.0.1:$(ES_HOST_PORT) \
					-e DB_HOST=127.0.0.1 \
					-e DB_PORT=$(MYSQL_HOST_PORT) \
					-e DB_DATABASE=$(MYSQL_DATABASE) \
					-e DB_USERNAME=$(MYSQL_USER) \
					-e DB_PASSWORD=$(MYSQL_PASSWORD) \
					$(DOCKER_IMAGE_PREFIX):$$php_version sh -c "\
						cp -r /src/. /app/ && \
						composer update \
							--with='orchestra/testbench:^$$testbench_version.0' \
							--with='elasticsearch/elasticsearch:^$$es_major.0' \
							--prefer-dist --no-interaction --no-progress && \
						vendor/bin/phpunit --colors=always \
					"; then \
					printf "$(GREEN)✔ PHP $$php_version / Laravel $$laravel_version / ES $$es_version passed$(RESET)\n"; \
					passed=$$((passed + 1)); \
				else \
					printf "$(RED)✘ PHP $$php_version / Laravel $$laravel_version / ES $$es_version failed$(RESET)\n"; \
					failed=$$((failed + 1)); \
				fi; \
			done; \
		done; \
		printf "$(CYAN)━━━ Stopping Elasticsearch $$es_version ━━━$(RESET)\n"; \
		$(MAKE) down-es; \
	done; \
	$(MAKE) down; \
	printf "\n$(CYAN)════════════════════════════════════════════════════════════$(RESET)\n"; \
	printf "$(CYAN)  Results: $(GREEN)$$passed passed$(CYAN), $(RED)$$failed failed$(CYAN), $(YELLOW)$$skipped skipped$(RESET)\n"; \
	printf "$(CYAN)════════════════════════════════════════════════════════════$(RESET)\n"; \
	[ $$failed -eq 0 ]

##@ Help

help: ## Show this help
	@awk 'BEGIN {FS = ":.*##"; printf "\n$(CYAN)Usage:$(RESET)\n  make $(YELLOW)<target>$(RESET)\n"} /^[a-zA-Z0-9_-]+:.*?##/ { printf "  $(YELLOW)%-18s$(RESET) %s\n", $$1, $$2 } /^##@/ { printf "\n$(CYAN)%s$(RESET)\n", substr($$0, 5) }' $(MAKEFILE_LIST)
