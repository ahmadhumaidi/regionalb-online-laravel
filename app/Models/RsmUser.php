<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * The RSM dashboard's own auth model (table rsm_users). Distinct from
 * LegacyUser (table `users`), an unrelated pre-existing app's accounts
 * that happen to live in the same database.
 */
class RsmUser extends Authenticatable
{
    use Notifiable;

    protected $table = 'rsm_users';

    public const ROLE_SUPER_USER = 'super_user';
    public const ROLE_EXECUTIVE_DIRECTOR = 'executive_director';
    public const ROLE_DIRECTOR = 'director';
    public const ROLE_SENIOR = 'senior';
    public const ROLE_MENTOR = 'mentor';
    public const ROLE_KOORDINATOR = 'koordinator';
    public const ROLE_STAFF = 'staff';

    public const ROLES = [
        self::ROLE_SUPER_USER,
        self::ROLE_EXECUTIVE_DIRECTOR,
        self::ROLE_DIRECTOR,
        self::ROLE_SENIOR,
        self::ROLE_MENTOR,
        self::ROLE_KOORDINATOR,
        self::ROLE_STAFF,
    ];

    protected $fillable = [
        'name',
        'nik',
        'username',
        'password_hash',
        'role',
        'jabatan',
        'regional',
        'area',
        'campus_name',
        'phone_number',
        'work_duration',
        'bio_text',
        'photo_path',
        'must_change_password',
        'is_active',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'must_change_password' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /** Keep Laravel's optional password rehash on the legacy column name. */
    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    /**
     * New uploads live under Laravel's own public disk (photo_path
     * "profiles/..."); legacy accounts have a photo_path pointing at
     * production's runtime/uploads/profile/ instead, which isn't
     * web-reachable directly — those go through users.photo, which falls
     * back to filesystems.legacy_public_root (mirrors ReportFormController::attachment()).
     */
    public function photoUrl(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return str_starts_with($this->photo_path, 'profiles/')
            ? \Illuminate\Support\Facades\Storage::url($this->photo_path)
            : route('users.photo', $this);
    }
}
