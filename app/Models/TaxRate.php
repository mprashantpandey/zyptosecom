<?php

namespace App\Models;

use App\Core\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TaxRate extends Model
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'name',
        'rate',
        'country',
        'state',
        'category',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function taxRules()
    {
        return $this->hasMany(TaxRule::class, 'apply_rate_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
