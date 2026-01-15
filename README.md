# aula API

Backend API for serving aula. Visit the [aula documentation](https://docs.aula.de/) for more information about the project.

## Self-Hosting

See [SELF_HOSTING.md](https://github.com/aula-app/aula-backend/blob/main/SELF_HOSTING.md) for up-to-date information. At the moment, running aula-backend and aula-frontend docker images is NOT enough.

## License

See [`LICENSE.txt`](https://github.com/aula-app/aula-backend/blob/main/LICENSE.txt). Licensed under the EUPL-1.2 or later.
You may obtain a copy of the license at https://interoperable-europe.ec.europa.eu/collection/eupl/eupl-text-eupl-12.

## Contribution

Thank you for your interest. See contribution guidelines at [`CONTRIBUTION.md`](https://github.com/aula-app/aula-backend/blob/main/CONTRIBUTION.md).

## Development

### Running

Choose one of the following:

```bash
# Run aula-backend:v2 locally (doesn't include the legacy aula-backend:v1)
docker compose --profile v2-only up --build -d

# Stop aula-backend:v2 and run legacy aula-backend:v1
docker compose --profile v2-only down \
  && make run-legacy-local

# Run both legacy aula-backend:v1 (:8080) and current Laravel-based aula-backend:v2 (:???)
docker build -f Dockerfile . \
  && docker compose --profile v2-only down \
  && make run-legacy-local \
  && docker compose up --build -d aula-backend
```

### Testing - basic setup

The following will refresh your database and create one tenant and seed it with OAuth client.

```bash
# php artisan migrate:fresh # WARNING - this will drop all data in the database

# this will run as your user, so if running in Docker container, you might need to adjust permissions afterwards: chown -R www-data:www-data ./storage/tenant_*
php artisan tinker
$ App\Models\Tenant::create(['name' => 'test11', 'api_base_url' => 'https://neu.aula.de', 'admin1_email' => 'dev@aula.de', 'admin1_username' => 'dev', 'instance_code' => '11111']);

php artisan tenant:seed
# note the generated OAuth client ID, and use it in this command
curl -H 'Content-Type: application/x-www-form-urlencoded' -H 'aula-instance-code: 11111' -H 'Accept: application/json' localhost:8080/api/v2/oauth/token -XPOST -d 'grant_type=password&client_id=$GENERATED_OAUTH_CLIENT_ID&username=test@example.com&password=password'

# now you have access_token you can use to explore the API.. start by reading ./routes/tenant.php file
```

## Legacy aula-backend code

We're currently rewriting the API to use Laravel and be RESTful. New feature development using code in the `./legacy/` folder is stopped since 2025-11-15. Security patches and bugs are welcome. As parts of the system get refactored, and API clients updated, we will remove the related code from the legacy codebase.

### Legacy part of API

- `./legacy/src` contains all PHP source files that are served by Apache2.
- `./legacy/config` has templates for configuration. You can manually edit them.
- `./legacy/init` has SQL scripts to init the legacy DB structure, for local development use only.
- `./legacy/docker-local` is local-only folder bound to Docker volumes storing database and uploaded files.

### Running the legacy code locally through Docker Compose

You can use one of the two following:

```bash
# Run the latest published release locally
make run-legacy-release
```

```bash
# Run locally live development environment (files you edit get updated immediately)
make run-legacy-local
```
