# UseCase

## Architecture around UseCases

Each UseCase is concerned by a single functionality, the separation of responsibilities is per class (per file). Each UseCase class orchestrates and binds together business rules across multiple Domains.

UseCases — one class per user action (CreateIdea, CastVote, DelegateVote, AddComment, UpdateRole, ConfigureRoom, etc.). Orchestrates repositories, domain services, transactions, and side effects; not low-level persistence or pure domain logic.
