<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'user_id',
        'contact_user_id',
        'search_request_id',
        'name',
        'address',
        'city',
        'surface_area',
        'parking_spots',
        'availability',
        'acquisition',
        'rent_price',
        'rent_price_per_m2',
        'rent_price_parking',
        'asking_price',
        'status',
        'images',
        'brochure_path',
        'drawings',
        'notes',
        'url',
    ];

    protected $casts = [
        'images' => 'array',
        'drawings' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contactUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contact_user_id');
    }

    public function searchRequest(): BelongsTo
    {
        return $this->belongsTo(SearchRequest::class);
    }
}
