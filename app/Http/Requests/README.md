# FormRequests

FormRequests are used to validate incoming requests. 

- Request (HTTP/FormRequest)
  - Where created: Laravel receives HTTP request; use FormRequest for validation (app/Http/Requests).
  - Purpose: validate transport concerns (JSON shape, basic auth tokens, etc.), convert raw HTTP into typed input.
  - Who maps/uses it: Controller receives validated FormRequest -> maps to Input DTO (or passes primitive array) -> calls UseCase.

