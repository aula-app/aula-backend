# aula API

Backend API for serving aula. Visit the [aula documentation](https://docs.aula.de/) for more information about the project.

## Running locally through Docker Compose

You can use one of the two following:

```bash
# Run the latest published release locally
make run-release
```

```bash
# Run locally live development environment (files you edit get updated immediately)
make run-local
```

## Release docker image

```bash
# Build and publish the image (if you have the privileges on Docker Hub)
make publish-release
```

## Self-Hosting

Please reach out to `dev [at] aula.de` if you're interested in self-hosting. Instructions will soon be published in [aula-selfhosted](https://github.com/aula-app/aula-selfhosted). At the moment, running aula-backend and aula-frontend docker images is NOT enough.

## Development

- `./src` contains all PHP source files that are served by Apache2.
- `./config` has templates for configuration. You can manually edit them.

## License

See `LICENSE.txt`. Licensed under the EUPL-1.2 or later.
You may obtain a copy of the license at https://interoperable-europe.ec.europa.eu/collection/eupl/eupl-text-eupl-12.

## Contribution

Thank you for your interest. See contribution guidelines at `CONTRIBUTION.md`.
