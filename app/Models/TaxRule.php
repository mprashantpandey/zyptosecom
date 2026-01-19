<?php

namespace App\Models;

use App\Core\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TaxRule extends Model
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'name',
        'priority',
        'condition_type',
        'condition_value',
        'apply_rate_id',
        'apply_type',
        'starts_at',
        'ends_at',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function taxRate()
    {
        return $this->belongsTo(TaxRate::class, 'apply_rate_id');
    }
}
