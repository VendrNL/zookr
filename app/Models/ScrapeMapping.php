<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScrapeMapping extends Model
{
    protected $fillable = [
        'domain',
        'property_field',
        'selector',
    ];
}
