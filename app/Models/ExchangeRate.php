<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'base_currency',
        'quote_currency',
        'rate',
        'source',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:8',
            'updated_at' => 'datetime',
        ];
    }

    public static function getRate(string $from, string $to): float
    {
        if ($from === $to) {
            return 1.0;
        }

        $rate = static::where('base_currency', $from)
            ->where('quote_currency', $to)
            ->value('rate');

        if ($rate) {
            return (float) $rate;
        }

        // Try reverse rate
        $reverseRate = static::where('base_currency', $to)
            ->where('quote_currency', $from)
            ->value('rate');

        if ($reverseRate) {
            return 1.0 / (float) $reverseRate;
        }

        return 1.0; // Fallback to 1:1 if no rate found
    }
}
