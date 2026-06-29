<?php

namespace Tests\Unit;

use App\Services\IdTokenVerification\IdTokenVerificationException;
use App\Services\IdTokenVerifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\SignsIdTokens;
use Tests\TestCase;

class IdTokenVerifierTest extends TestCase
{
    use SignsIdTokens;

    private IdTokenVerifier $verifier;

    private string $expectedIssuer;

    private string $expectedClientId;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.keycloak.base_url'  => 'https://sso.test.local',
            'services.keycloak.realms'    => 'aula-test',
            'services.keycloak.client_id' => 'aula-backend-test',
        ]);

        $this->expectedIssuer   = 'https://sso.test.local/realms/aula-test';
        $this->expectedClientId = 'aula-backend-test';

        Cache::flush();

        $this->verifier = $this->app->make(IdTokenVerifier::class);
    }

    public function test_verifies_a_well_formed_token(): void
    {
        $this->fakeJwksEndpoint();
        $token = $this->signIdToken($this->validClaims());

        $claims = $this->verifier->verify($token);

        $this->assertEquals('user-sub-001', $claims['sub']);
        $this->assertEquals('user@example.test', $claims['email']);
        $this->assertTrue($claims['email_verified']);
    }

    public function test_rejects_malformed_token(): void
    {
        $this->fakeJwksEndpoint();
        $this->expectExceptionReason('malformed');
        $this->verifier->verify('not.a.jwt');
    }

    public function test_rejects_tampered_signature(): void
    {
        $this->fakeJwksEndpoint();
        $token = $this->signIdToken($this->validClaims());
        [$h, $p] = explode('.', $token);
        $tampered = "{$h}.{$p}." . strtr(base64_encode('tampered-bytes-here'), '+/', '-_');

        $this->expectExceptionReason('signature_invalid');
        $this->verifier->verify($tampered);
    }

    public function test_rejects_expired_token(): void
    {
        $this->fakeJwksEndpoint();
        $token = $this->signIdToken($this->validClaims(['exp' => time() - 600]));

        $this->expectExceptionReason('expired');
        $this->verifier->verify($token);
    }

    public function test_rejects_wrong_issuer(): void
    {
        $this->fakeJwksEndpoint();
        $token = $this->signIdToken($this->validClaims(['iss' => 'https://impostor.example/realms/aula-test']));

        $this->expectExceptionReason('issuer_mismatch');
        $this->verifier->verify($token);
    }

    public function test_rejects_wrong_audience_string(): void
    {
        $this->fakeJwksEndpoint();
        $token = $this->signIdToken($this->validClaims(['aud' => 'some-other-client']));

        $this->expectExceptionReason('audience_mismatch');
        $this->verifier->verify($token);
    }

    public function test_accepts_audience_array_containing_client_id(): void
    {
        $this->fakeJwksEndpoint();
        $token = $this->signIdToken($this->validClaims([
            'aud' => ['some-other-client', $this->expectedClientId],
            'azp' => $this->expectedClientId,
        ]));

        $claims = $this->verifier->verify($token);
        $this->assertEquals('user-sub-001', $claims['sub']);
    }

    public function test_rejects_audience_array_not_containing_client_id(): void
    {
        $this->fakeJwksEndpoint();
        $token = $this->signIdToken($this->validClaims(['aud' => ['client-a', 'client-b']]));

        $this->expectExceptionReason('audience_mismatch');
        $this->verifier->verify($token);
    }

    public function test_rejects_azp_mismatch(): void
    {
        $this->fakeJwksEndpoint();
        $token = $this->signIdToken($this->validClaims([
            'aud' => [$this->expectedClientId, 'other'],
            'azp' => 'not-us',
        ]));

        $this->expectExceptionReason('azp_mismatch');
        $this->verifier->verify($token);
    }

    public function test_accepts_azp_absent_when_single_audience_matches(): void
    {
        $this->fakeJwksEndpoint();
        $claims = $this->validClaims();
        unset($claims['azp']);

        $token = $this->signIdToken($claims);

        $verified = $this->verifier->verify($token);
        $this->assertEquals('user-sub-001', $verified['sub']);
    }

    public function test_rejects_unknown_kid(): void
    {
        $this->fakeJwksEndpoint();
        $token = $this->signIdToken($this->validClaims(), 'no-such-kid-in-jwks');

        $this->expectExceptionReason('kid_unknown');
        $this->verifier->verify($token);
    }

    public function test_rejects_when_jwks_endpoint_returns_error(): void
    {
        Http::fake([
            '*/protocol/openid-connect/certs' => Http::response('upstream down', 502),
        ]);

        $token = $this->signIdToken($this->validClaims());

        $this->expectExceptionReason('jwks_unavailable');
        $this->verifier->verify($token);
    }

    public function test_jwks_response_is_cached(): void
    {
        $this->fakeJwksEndpoint();
        $token = $this->signIdToken($this->validClaims());

        $this->verifier->verify($token);
        $this->verifier->verify($token);
        $this->verifier->verify($token);

        Http::assertSentCount(1);
    }

    private function validClaims(array $overrides = []): array
    {
        return array_merge([
            'iss'            => $this->expectedIssuer,
            'aud'            => $this->expectedClientId,
            'azp'            => $this->expectedClientId,
            'sub'            => 'user-sub-001',
            'email'          => 'user@example.test',
            'email_verified' => true,
            'iat'            => time() - 30,
            'exp'            => time() + 600,
        ], $overrides);
    }

    private function expectExceptionReason(string $reason): void
    {
        $this->expectException(IdTokenVerificationException::class);
        $this->expectExceptionMessageMatches("/{$reason}/");
    }
}
