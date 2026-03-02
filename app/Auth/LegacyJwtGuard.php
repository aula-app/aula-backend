<?php

namespace App\Auth;

use App\Models\LegacyUser;
use App\Services\LegacyJwtService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;

class LegacyJwtGuard implements Guard
{
    protected ?Authenticatable $user = null;
    protected bool $validated = false;

    public function __construct(
        protected LegacyJwtService $jwtService,
        protected Request $request
    ) {}

    /**
     * Determine if the current user is authenticated.
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Determine if the current user is a guest.
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * Get the currently authenticated user.
     */
    public function user(): ?Authenticatable
    {
        if ($this->validated) {
            return $this->user;
        }

        $this->validated = true;

        // Try to get user from request attributes (set by middleware)
        $user = $this->request->attributes->get('authenticated_user');

        if ($user instanceof LegacyUser) {
            $this->user = $user;
            return $this->user;
        }

        // Fallback: validate token directly
        $authHeader = $this->request->header('Authorization');
        $token = $this->jwtService->extractBearerToken($authHeader);

        if ($token === null) {
            return null;
        }

        $validation = $this->jwtService->validateToken($token);

        if (!$validation['success']) {
            return null;
        }

        $payload = $validation['payload'];
        $user = LegacyUser::find($payload->user_id);

        if ($user === null || !$user->isActive()) {
            return null;
        }

        if ($user->hash_id !== $payload->user_hash) {
            return null;
        }

        if ($user->needsRefresh()) {
            return null;
        }

        $this->user = $user;
        return $this->user;
    }

    /**
     * Get the ID for the currently authenticated user.
     */
    public function id(): int|string|null
    {
        $user = $this->user();
        return $user?->getAuthIdentifier();
    }

    /**
     * Validate a user's credentials.
     */
    public function validate(array $credentials = []): bool
    {
        $username = $credentials['username'] ?? null;
        $password = $credentials['password'] ?? null;

        if ($username === null || $password === null) {
            return false;
        }

        $user = LegacyUser::where('username', $username)->first();

        if ($user === null) {
            return false;
        }

        return $user->checkPassword($password);
    }

    /**
     * Determine if the guard has a user instance.
     */
    public function hasUser(): bool
    {
        return $this->user !== null;
    }

    /**
     * Set the current user.
     */
    public function setUser(Authenticatable $user): static
    {
        $this->user = $user;
        $this->validated = true;
        return $this;
    }
}
