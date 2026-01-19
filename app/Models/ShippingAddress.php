<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'landmark',
        'address_type',
        'is_default',
        'cod_available',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'cod_available' => 'boolean',
        ];
    }

    /**
     * Get user relationship
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

