<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Organization;

class SearchRequest extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'created_by',
        'assigned_to',
        'organization_id',
        'title',
        'customer_name',
        'location',
        'provinces',
        'property_type',
        'surface_area',
        'parking',
        'availability',
        'accessibility',
        'acquisitions',
        'notes',
        'status',
    ];

    protected $casts = [
        'provinces' => 'array',
        'acquisitions' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function mailings(): HasMany
    {
        return $this->hasMany(SearchRequestMailing::class);
    }
}
