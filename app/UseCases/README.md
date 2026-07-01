# UseCase

## Architecture around UseCases

Each UseCase is concerned by a single functionality, the separation of responsibilities is per class (per file). Each UseCase class orchestrates and binds together business rules across multiple Domains.

UseCases — one class per user action (CreateIdea, CastVote, DelegateVote, AddComment, UpdateRole, ConfigureRoom, etc.).

Orchestrates repositories, domain services, transactions, and side effects; uses low-level persistence by directly manipulating Eloquent model.

We'll try to avoid introducing another layer of abstraction between UseCases and persistence layer models, as long as possible.
