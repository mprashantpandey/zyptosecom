<?php

namespace App\Models;

use App\Core\Traits\HasAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerSegment extends Model
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'name',
        'description',
        'rules',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get users matching this segment
     */
    public function getMatchingUsers()
    {
        $query = User::where('type', 'customer')->where('is_active', true);

        $rules = $this->rules ?? [];

        // min_orders
        if (isset($rules['min_orders'])) {
            $query->has('orders', '>=', $rules['min_orders']);
        }

        // max_orders
        if (isset($rules['max_orders'])) {
            $query->has('orders', '<=', $rules['max_orders']);
        }

        // last_order_days
        if (isset($rules['last_order_days'])) {
            $query->whereHas('orders', function ($q) use ($rules) {
                $q->where('created_at', '>=', now()->subDays($rules['last_order_days']));
            });
        }

        // wallet_balance_gt
        if (isset($rules['wallet_balance_gt'])) {
            $query->whereHas('wallet', function ($q) use ($rules) {
                $q->where('balance', '>', $rules['wallet_balance_gt']);
            });
        }

        // status
        if (isset($rules['status'])) {
            $query->where('is_active', $rules['status'] === 'active');
        }

        return $query->get();
    }

    /**
     * Get count of matching users
     */
    public function getMatchingCount(): int
    {
        return $this->getMatchingUsers()->count();
    }
}
