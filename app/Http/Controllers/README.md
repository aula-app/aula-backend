# Controllers

## Q: Why have thin Controllers and why not move stuff from UseCases to Controller layer?

A: Controllers should handle:
* transport concerns - set HTTP status codes, headers, cookies, content type, and perform content-negotiation
  * for example: file upload (form multipart) or reading raw payloads that aren’t mapped by laravel-data
* exceptions - translate domain exceptions into appropriate HTTP responses (409, 404, 403, 422, 500)
* request-scoped concerns: request-scoped tracing IDs, rate-limit checks

## Q: When to put authoriZation logic where?

A: **All of it in the UseCase**, none in the Controller.

The earlier rule ("HTTP details in the Controller, domain state in the UseCase")
sounds right but splits the checks per action, so a reader has to open two files
to answer "is this endpoint guarded?". Worse, a UseCase whose only guard lives in
its Controller is unguarded for every other caller.

Reasons for the UseCase:

* it is the boundary that *everything* crosses: HTTP, console commands, queued
  jobs, and whatever v1 to v2 shim we end up with. The Controller only covers HTTP.
* it is where the resource is loaded. Rules that depend on the resource
  (ownership, room-scoped roles, which fields may change) cannot be expressed
  before the row exists.
* it makes the invariant checkable: every `execute()` opens with an `authorize`,
  so a missing one is visible.

The Controller keeps transport only (see above). If a check genuinely needs
something that never reaches the UseCase, pass it in as an argument rather than
moving the decision back up.
