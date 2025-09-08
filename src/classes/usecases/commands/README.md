# UseCase - Commands handling

Each `Command` is handled by its own `CommandHandler` implementation, found in this folder.

Each `CommandHandler` implementation needs to implement these two methods:

```php
abstract protected function isValid(mixed $command): bool;
abstract protected function execute(mixed $command): mixed;
```

The Commands are supposed to be dispatched by the `CommandDispatcher` which orchestrates the `Command` to appropriate `CommandHandler` implementation based on the Command's `cmd_id` field.
