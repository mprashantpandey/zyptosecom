<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentString extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'display_name',
        'locale',
        'value',
        'group',
        'platform',
        'variables',
        'usage_hint',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_system' => 'boolean',
        ];
    }

    /**
     * Get human-readable group labels
     */
    public static function getGroupLabels(): array
    {
        return [
            'general' => 'General',
            'authentication' => 'Authentication',
            'checkout' => 'Checkout',
            'orders' => 'Orders',
            'cart' => 'Cart',
            'errors' => 'Errors',
            'notifications' => 'Notifications',
        ];
    }
}
