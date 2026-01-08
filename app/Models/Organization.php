<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'website',
        'logo_path',
        'is_active',
    ];

    protected $appends = [
        'logo_url',
    ];

    protected $casts = [
        'is_active' => 'bool',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path
            ? \Storage::disk('public')->url($this->logo_path)
            : null;
    }
}
