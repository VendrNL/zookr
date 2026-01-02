<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SearchRequest extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'created_by',
        'assigned_to',
        'title',
        'description',
        'location',
        'budget_min',
        'budget_max',
        'due_date',
        'status',
    ];

    protected $casts = [
        'due_date' => 'date',
        'budget_min' => 'integer',
        'budget_max' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
