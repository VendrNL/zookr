<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Notifications\ResetPasswordNotification;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_active',
        'is_admin',
        'organization_id',
        'specialism_types',
        'specialism_provinces',
        'avatar_path',
        'linkedin_url',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'specialism_types' => 'array',
            'specialism_provinces' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected $appends = [
        'avatar_url',
    ];

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path
            ? \Storage::disk('public')->url($this->avatar_path)
            : null;
    }

    public function createdSearchRequests(): HasMany
    {
        return $this->hasMany(SearchRequest::class, 'created_by');
    }

    public function assignedSearchRequests(): HasMany
    {
        return $this->hasMany(SearchRequest::class, 'assigned_to');
    }

    public function organization(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
