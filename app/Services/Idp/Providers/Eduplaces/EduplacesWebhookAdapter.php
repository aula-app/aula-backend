<?php

declare(strict_types=1);

namespace App\Services\Idp\Providers\Eduplaces;

use App\Services\Idp\Contracts\WebhookAdapter;
use App\Services\Idp\Dto\IdpEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Eduplaces IDM webhooks — https://developer.eduplaces.de/idm/webhooks
 *
 * Signature: `X-EP-Signature-Sha256`, an HMAC-SHA256 of the raw body. The
 * documented check produces lowercase hex; base64 is accepted too, since the
 * docs do not pin the encoding and the two are trivially distinguishable.
 *
 * Envelope: `{event, action, personId|groupId|schoolId, updatedProperties}`.
 * Eduplaces calls its people "person"; aula calls them users.
 */
final class EduplacesWebhookAdapter implements WebhookAdapter
{
    public const string SIGNATURE_HEADER = 'X-EP-Signature-Sha256';

    public const string EVENT_TYPE_HEADER = 'X-EP-Event-Type';

    private const array ENTITY_TYPES = [
        'person' => IdpEvent::ENTITY_USER,
        'group' => IdpEvent::ENTITY_GROUP,
        'school' => IdpEvent::ENTITY_SCHOOL,
    ];

    private const array ID_KEYS = [
        IdpEvent::ENTITY_USER => 'personId',
        IdpEvent::ENTITY_GROUP => 'groupId',
        IdpEvent::ENTITY_SCHOOL => 'schoolId',
    ];

    public function verify(Request $request, string $secret): bool
    {
        $provided = trim((string) $request->header(self::SIGNATURE_HEADER, ''));

        if ($provided === '') {
            return false;
        }

        // The raw body: a decode/re-encode round trip would not reproduce the
        // bytes that were signed.
        $body = $request->getContent();

        if (preg_match('/^[0-9a-f]{64}$/i', $provided) === 1) {
            return hash_equals(hash_hmac('sha256', $body, $secret), strtolower($provided));
        }

        $decoded = base64_decode($provided, true);

        if ($decoded === false || strlen($decoded) !== 32) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $body, $secret, true), $decoded);
    }

    public function parse(Request $request): ?IdpEvent
    {
        $payload = $request->json()->all();

        if (! is_array($payload) || $payload === []) {
            return null;
        }

        $entityType = $this->entityType($request, $payload);

        if ($entityType === null) {
            return null;
        }

        $action = is_string($payload['action'] ?? null) ? strtolower($payload['action']) : '';

        if (! in_array($action, IdpEvent::actions(), true)) {
            return null;
        }

        $entityId = $payload[self::ID_KEYS[$entityType]] ?? null;

        if (! is_string($entityId) || $entityId === '') {
            return null;
        }

        return new IdpEvent(
            entityType: $entityType,
            action: $action,
            entityId: $entityId,
            updatedProperties: $this->updatedProperties($payload),
            payload: $payload,
        );
    }

    /**
     * The event type appears in both the header and the body. Requiring them to
     * agree stops a mislabelled delivery being applied to the wrong kind of
     * entity.
     *
     * @param  array<string, mixed>  $payload
     */
    private function entityType(Request $request, array $payload): ?string
    {
        $header = strtolower(trim((string) $request->header(self::EVENT_TYPE_HEADER, '')));
        $body = is_string($payload['event'] ?? null) ? strtolower($payload['event']) : '';

        if ($header === '' || $body === '' || $header !== $body) {
            Log::warning('Eduplaces webhook: event type header and body disagree', [
                'header' => $header,
                'body' => $body,
            ]);

            return null;
        }

        return self::ENTITY_TYPES[$header] ?? null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function updatedProperties(array $payload): array
    {
        $properties = $payload['updatedProperties'] ?? null;

        return is_array($properties)
            ? array_values(array_filter($properties, 'is_string'))
            : [];
    }
}
