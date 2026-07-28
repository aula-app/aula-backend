<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessIdpWebhookEvent;
use App\Models\IdpWebhookEvent;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Covers the webhook entry point: signature verification, envelope validation
 * and durable capture. Processing is a separate concern that runs on the queue.
 */
class IdpWebhookIntakeTest extends TestCase
{
    private const string SECRET = 'webhook-secret-for-tests';

    private const string URL = '/api/v2/webhooks/idp/eduplaces';

    protected function setUp(): void
    {
        parent::setUp();

        config(['idp.providers.eduplaces.webhook_secret' => self::SECRET]);

        // Intake is about capture and acknowledgement; what the job then does
        // with the event is covered by the sync tests.
        Queue::fake();

        IdpWebhookEvent::query()->delete();
    }

    protected function tearDown(): void
    {
        IdpWebhookEvent::query()->delete();
        parent::tearDown();
    }

    // =========================================================
    // Signature verification
    // =========================================================

    public function test_accepts_a_correctly_signed_delivery(): void
    {
        $payload = $this->personPayload();

        $response = $this->postSigned($payload, 'person');

        $response->assertStatus(202)->assertJsonPath('status', 'accepted');
        $this->assertSame(1, IdpWebhookEvent::count());
    }

    public function test_accepts_a_base64_encoded_signature(): void
    {
        $payload = $this->personPayload();
        $body = (string) json_encode($payload);

        $response = $this->call('POST', self::URL, [], [], [], $this->headers('person', base64_encode(
            hash_hmac('sha256', $body, self::SECRET, true),
        )), $body);

        $response->assertStatus(202);
    }

    public function test_rejects_a_missing_signature(): void
    {
        $body = (string) json_encode($this->personPayload());

        $response = $this->call('POST', self::URL, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_EP_EVENT_TYPE' => 'person',
        ], $body);

        $response->assertStatus(401)->assertJsonPath('error', 'signature_invalid');
        $this->assertSame(0, IdpWebhookEvent::count());
    }

    public function test_rejects_a_signature_computed_with_the_wrong_secret(): void
    {
        $body = (string) json_encode($this->personPayload());

        $response = $this->call('POST', self::URL, [], [], [], $this->headers('person', hash_hmac(
            'sha256', $body, 'not-the-secret',
        )), $body);

        $response->assertStatus(401)->assertJsonPath('error', 'signature_invalid');
        $this->assertSame(0, IdpWebhookEvent::count());
    }

    public function test_rejects_a_signature_that_does_not_cover_the_delivered_body(): void
    {
        // Signature is valid, but for a different body: catches a replayed
        // header pasted onto tampered content.
        $signature = hash_hmac('sha256', (string) json_encode($this->personPayload()), self::SECRET);
        $tampered = (string) json_encode($this->personPayload('someone-elses-person-id'));

        $response = $this->call('POST', self::URL, [], [], [], $this->headers('person', $signature), $tampered);

        $response->assertStatus(401)->assertJsonPath('error', 'signature_invalid');
    }

    public function test_fails_closed_when_no_secret_is_configured(): void
    {
        config(['idp.providers.eduplaces.webhook_secret' => null]);

        $response = $this->postSigned($this->personPayload(), 'person');

        $response->assertStatus(500)->assertJsonPath('error', 'webhook_not_configured');
        $this->assertSame(0, IdpWebhookEvent::count());
    }

    // =========================================================
    // Envelope validation
    // =========================================================

    public function test_rejects_an_unknown_event_type(): void
    {
        $response = $this->postSigned(['event' => 'planet', 'action' => 'update', 'planetId' => 'x'], 'planet');

        $response->assertStatus(422)->assertJsonPath('error', 'payload_invalid');
    }

    public function test_rejects_a_header_that_disagrees_with_the_body(): void
    {
        $response = $this->postSigned($this->personPayload(), 'group');

        $response->assertStatus(422)->assertJsonPath('error', 'payload_invalid');
    }

    public function test_rejects_an_unknown_action(): void
    {
        $response = $this->postSigned([
            'event' => 'person',
            'action' => 'obliterate',
            'personId' => 'person-1',
        ], 'person');

        $response->assertStatus(422)->assertJsonPath('error', 'payload_invalid');
    }

    public function test_rejects_a_payload_without_an_entity_id(): void
    {
        $response = $this->postSigned(['event' => 'person', 'action' => 'update'], 'person');

        $response->assertStatus(422)->assertJsonPath('error', 'payload_invalid');
    }

    public function test_rejects_an_empty_body(): void
    {
        $response = $this->call('POST', self::URL, [], [], [], $this->headers('person', hash_hmac(
            'sha256', '', self::SECRET,
        )), '');

        $response->assertStatus(422)->assertJsonPath('error', 'payload_invalid');
    }

    // =========================================================
    // Capture
    // =========================================================

    public function test_records_the_delivery_verbatim(): void
    {
        $payload = [
            'event' => 'person',
            'action' => 'update',
            'personId' => '10adffa1-5ccd-481c-afc0-b5b8728d140d',
            'updatedProperties' => ['role', 'groups'],
        ];

        $this->postSigned($payload, 'person')->assertStatus(202);

        $event = IdpWebhookEvent::firstOrFail();

        // Normalised: Eduplaces says 'person', aula records 'user'.
        $this->assertSame('user', $event->entity_type);
        $this->assertSame('update', $event->action);
        $this->assertSame('10adffa1-5ccd-481c-afc0-b5b8728d140d', $event->entity_id);
        $this->assertSame(['role', 'groups'], $event->updated_properties);
        $this->assertSame($payload, $event->payload);
        $this->assertSame(IdpWebhookEvent::STATUS_PENDING, $event->status);
        $this->assertNotNull($event->received_at);
        $this->assertNull($event->tenant_id);
    }

    public function test_records_group_and_school_events_with_their_own_id_keys(): void
    {
        $this->postSigned(['event' => 'group', 'action' => 'create', 'groupId' => 'group-9'], 'group')
            ->assertStatus(202);
        $this->postSigned(['event' => 'school', 'action' => 'update', 'schoolId' => 'school-9'], 'school')
            ->assertStatus(202);

        $this->assertSame('group-9', IdpWebhookEvent::where('entity_type', 'group')->firstOrFail()->entity_id);
        $this->assertSame('school-9', IdpWebhookEvent::where('entity_type', 'school')->firstOrFail()->entity_id);
    }

    public function test_accepts_a_payload_without_updated_properties(): void
    {
        // The field is documented as optional and is absent on create/delete.
        $this->postSigned(['event' => 'person', 'action' => 'create', 'personId' => 'person-new'], 'person')
            ->assertStatus(202);

        $this->assertSame([], IdpWebhookEvent::firstOrFail()->updated_properties);
    }

    public function test_queues_a_job_for_an_accepted_delivery(): void
    {
        $this->postSigned($this->personPayload(), 'person')->assertStatus(202);

        $eventId = IdpWebhookEvent::firstOrFail()->id;

        Queue::assertPushed(
            ProcessIdpWebhookEvent::class,
            fn (ProcessIdpWebhookEvent $job): bool => $job->eventId === $eventId,
        );
    }

    public function test_queues_nothing_for_a_rejected_delivery(): void
    {
        $this->postSigned(['event' => 'person', 'action' => 'update'], 'person')->assertStatus(422);

        Queue::assertNothingPushed();
    }

    public function test_stores_repeated_deliveries_separately(): void
    {
        // Eduplaces documents no idempotency key, so a redelivery is a second
        // row. Convergent processing, not intake, is what makes replays safe.
        $payload = $this->personPayload();

        $this->postSigned($payload, 'person')->assertStatus(202);
        $this->postSigned($payload, 'person')->assertStatus(202);

        $this->assertSame(2, IdpWebhookEvent::count());
    }

    // =========================================================
    // Helpers
    // =========================================================

    /**
     * @return array<string, mixed>
     */
    private function personPayload(string $personId = 'person-1'): array
    {
        return [
            'event' => 'person',
            'action' => 'update',
            'personId' => $personId,
            'updatedProperties' => ['role'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSigned(array $payload, string $eventType): TestResponse
    {
        $body = (string) json_encode($payload);

        return $this->call('POST', self::URL, [], [], [], $this->headers($eventType, hash_hmac(
            'sha256', $body, self::SECRET,
        )), $body);
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $eventType, string $signature): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_EP_EVENT_TYPE' => $eventType,
            'HTTP_X_EP_SIGNATURE_SHA256' => $signature,
        ];
    }
}
