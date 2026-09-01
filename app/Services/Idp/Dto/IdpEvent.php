<?php

declare(strict_types=1);

namespace App\Services\Idp\Dto;

/**
 * One directory change, normalised across providers.
 */
final readonly class IdpEvent
{
    public const string ENTITY_USER = 'user';

    public const string ENTITY_GROUP = 'group';

    public const string ENTITY_SCHOOL = 'school';

    public const string ACTION_CREATE = 'create';

    public const string ACTION_UPDATE = 'update';

    public const string ACTION_DELETE = 'delete';

    public const string ACTION_RESTORE = 'restore';

    /**
     * @param  list<string>  $updatedProperties
     * @param  array<string, mixed>  $payload  the delivery as received
     */
    public function __construct(
        public string $entityType,
        public string $action,
        public string $entityId,
        public array $updatedProperties = [],
        public array $payload = [],
    ) {}

    /**
     * @return list<string>
     */
    public static function entityTypes(): array
    {
        return [self::ENTITY_USER, self::ENTITY_GROUP, self::ENTITY_SCHOOL];
    }

    /**
     * @return list<string>
     */
    public static function actions(): array
    {
        return [self::ACTION_CREATE, self::ACTION_UPDATE, self::ACTION_DELETE, self::ACTION_RESTORE];
    }
}
