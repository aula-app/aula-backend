# Controllers

## Q: Why have thin Controllers and why not move stuff from UseCases to Controller layer?

A: Controllers should handle:
* transport concerns - set HTTP status codes, headers, cookies, content type, and perform content-negotiation
  * for example: file upload (form multipart) or reading raw payloads that aren’t mapped by laravel-data
* exceptions - translate domain exceptions into appropriate HTTP responses (409, 404, 403, 422, 500)
* request-scoped concerns: request-scoped tracing IDs, rate-limit checks

## Q: When to put authoriZation logic where?

A: if the rule depends on HTTP details (headers, session) keep it in controller; if it depends on domain state or user+resource relationships, put it in UseCase.
