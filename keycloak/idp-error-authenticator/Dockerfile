# Self-contained build of a Keycloak image with the Aula IdP-error authenticator baked in.
#
# Stage 1 compiles the provider jar from source (no local JDK/Maven needed — only Docker).
# Stage 2 bakes it into Keycloak and pre-builds the server.
#
# Build (context = this directory):
#   docker build -f keycloak/idp-error-authenticator/Dockerfile \
#     -t aula-keycloak:26.6.1 keycloak/idp-error-authenticator
#
# Keep both tags below in sync with the Keycloak version you deploy to.

# ---- Stage 1: compile the provider ----
FROM maven:3.9-eclipse-temurin-17 AS build
WORKDIR /build
COPY pom.xml .
COPY src ./src
RUN mvn -B -q clean package

# ---- Stage 2: Keycloak image with the provider ----
FROM quay.io/keycloak/keycloak:26.6.1
COPY --from=build /build/target/aula-idp-error-authenticator.jar /opt/keycloak/providers/
# Augment the server at build time so the container starts pre-built and immutable.
# Bake the build-time options that match the runtime env (KC_DB, KC_HTTP_RELATIVE_PATH)
# so `command: start` does not have to re-augment the image on boot.
RUN /opt/keycloak/bin/kc.sh build --db=postgres --http-relative-path=/auth
