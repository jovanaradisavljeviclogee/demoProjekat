<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Authenticated user.
 *
 * This table carries exactly five columns - id, name, email, password,
 * created_at (ADR-13). Three framework defaults assume columns it does not
 * have, so each is switched off below: the updated_at timestamp and the
 * remember-me token, both harmless once disabled, and email verification,
 * which is the silent one. Authenticatable brings the MustVerifyEmail trait
 * along unconditionally, so hasVerifiedEmail() now returns false forever
 * rather than failing loudly - do not add the "verified" middleware or
 * implement MustVerifyEmail without restoring that column first.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const UPDATED_AT = null;

    /**
     * Blanking the name makes the trait's accessors no-ops, so the session
     * guard never writes the column this table does not have. Remember-me
     * therefore does not work rather than half-working: the guard still
     * queues a recaller cookie, but its empty token segment never validates.
     *
     * @var string
     */
    protected $rememberTokenName = '';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * The log entries written by this user.
     *
     * @return HasMany<Log, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(Log::class);
    }
}
