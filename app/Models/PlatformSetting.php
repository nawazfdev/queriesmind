<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : json_decode($value, true),
            set: fn ($value) => json_encode($value),
        );
    }
}
