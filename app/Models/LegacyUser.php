<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class LegacyUser extends Model implements Authenticatable
{
    /**
     * The table associated with the model.
     */
    protected $table = 'au_users_basedata';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'pw',
        'hash_id',
        'refresh_token',
        'temp_pw',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'id' => 'integer',
        'userlevel' => 'integer',
        'status' => 'integer',
        'refresh_token' => 'boolean',
        'created' => 'datetime',
        'last_update' => 'datetime',
        'last_login' => 'datetime',
    ];

    /**
     * User status constants
     */
    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_SUSPENDED = 2;
    public const STATUS_ARCHIVED = 3;

    /**
     * Check if the user is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if the user needs to refresh their token.
     */
    public function needsRefresh(): bool
    {
        return (bool) $this->refresh_token;
    }

    /**
     * Verify the user's password.
     * Supports both bcrypt hashed passwords and temporary passwords.
     */
    public function checkPassword(string $password): bool
    {
        // Check temporary password first (plain text match)
        if (!empty($this->temp_pw) && $this->temp_pw !== '' && $this->temp_pw === $password) {
            return true;
        }

        // Check hashed password using PHP's password_verify (bcrypt)
        return password_verify($password, $this->pw);
    }

    /**
     * Get the payload data for JWT token generation.
     */
    public function getJwtPayload(): array
    {
        return [
            'id' => $this->id,
            'hash_id' => $this->hash_id,
            'userlevel' => $this->userlevel,
            'roles' => $this->roles,
            'temp_pw' => !empty($this->temp_pw) && $this->temp_pw !== '',
        ];
    }

    /**
     * Clear the refresh token flag.
     */
    public function clearRefreshToken(): bool
    {
        $this->refresh_token = false;
        return $this->save();
    }

    /**
     * Set the refresh token flag.
     */
    public function setRefreshToken(bool $value = true): bool
    {
        $this->refresh_token = $value;
        return $this->save();
    }

    // Authenticatable interface methods

    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    /**
     * Get the unique identifier for the user.
     */
    public function getAuthIdentifier(): mixed
    {
        return $this->id;
    }

    /**
     * Get the password for the user.
     */
    public function getAuthPassword(): string
    {
        return $this->pw;
    }

    /**
     * Get the token value for the "remember me" session.
     */
    public function getRememberToken(): ?string
    {
        return null;
    }

    /**
     * Set the token value for the "remember me" session.
     */
    public function setRememberToken($value): void
    {
        // Not used in JWT authentication
    }

    /**
     * Get the column name for the "remember me" token.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }

    /**
     * Get the password hash for the user (Laravel 11+).
     */
    public function getAuthPasswordName(): string
    {
        return 'pw';
    }
}
