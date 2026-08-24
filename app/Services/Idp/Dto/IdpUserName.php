<?php

declare(strict_types=1);

namespace App\Services\Idp\Dto;

/**
 * A provider can split a name into the full first name, the first name in use,
 * and the last name. "Wilma Johanna Sophie" may go by "Johanna", so firstCall
 * is what belongs in `displayname`.
 */
final readonly class IdpUserName
{
    public function __construct(
        public ?string $firstFull = null,
        public ?string $firstCall = null,
        public ?string $last = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            firstFull: self::string($data, 'firstFull'),
            firstCall: self::string($data, 'firstCall'),
            last: self::string($data, 'last'),
        );
    }

    /**
     * The name for `displayname`.
     */
    public function display(): string
    {
        return $this->join($this->firstCall ?? $this->firstFull, $this->last);
    }

    /**
     * The legal name, for `realname`.
     */
    public function real(): string
    {
        return $this->join($this->firstFull ?? $this->firstCall, $this->last);
    }

    private function join(?string $first, ?string $last): string
    {
        return trim(implode(' ', array_filter([$first, $last])));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
