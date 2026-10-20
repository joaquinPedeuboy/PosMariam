<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingSetting extends Model
{
    protected $fillable = [
        'motomensajeria_tarifa',
        'motomensajeria_minimo_gratis',
        'motomensajeria_habilitada',
    ];

    protected $casts = [
        'motomensajeria_tarifa' => 'decimal:2',
        'motomensajeria_minimo_gratis' => 'decimal:2',
        'motomensajeria_habilitada' => 'boolean',
    ];

    public static function actual(): self
    {
        return self::query()->firstOrCreate(
            ['id' => 1],
            [
                'motomensajeria_tarifa' => 3000,
                'motomensajeria_minimo_gratis' => 50000,
                'motomensajeria_habilitada' => true,
            ]
        );
    }
}
