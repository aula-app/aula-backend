# aula API

Backend API for serving aula. Visit the [aula documentation](https://docs.aula.de/) for more information about the project.

## Running locally through Docker Compose

You can use one of the two following:

```bash
# Run the latest published release locally
make run-release

# Run locally live development environment (files you edit get updated immediately)
make run-local
```

## Release docker image

```bash
# Build and publish the image (if you have the privileges on Docker Hub)
make publish-release
```

## Self-Hosting

Please reach out to `dev [at] aula.de` if you're interested in self-hosting. Instructions TBD (in docs).

## Development

- `./src` contains all PHP source files that are served by Apache2.
- `./config` has templates for configuration. You can manually edit them.
