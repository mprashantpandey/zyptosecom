<?php

namespace App\Core\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait HasAuditLog
{
    /**
     * Boot the trait
     */
    public static function bootHasAuditLog(): void
    {
        static::created(function (Model $model) {
            $model->logAuditEvent('created', $model->getAttributes());
        });

        static::updated(function (Model $model) {
            $model->logAuditEvent('updated', $model->getDirty(), $model->getOriginal());
        });

        static::deleted(function (Model $model) {
            $model->logAuditEvent('deleted', $model->getOriginal());
        });
    }

    /**
     * Log audit event
     */
    public function logAuditEvent(string $event, array $newValues = [], array $oldValues = []): void
    {
        $actionType = $this->getAuditActionType($event);
        
        AuditLog::create([
            'auditable_type' => get_class($this),
            'auditable_id' => $this->getKey(),
            'user_id' => Auth::id(),
            'event' => $event,
            'action_type' => $actionType,
            'description' => $this->getAuditDescription($event),
            'old_values' => !empty($oldValues) ? $oldValues : null,
            'new_values' => !empty($newValues) ? $newValues : null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'module' => $this->getAuditModule(),
        ]);
    }

    /**
     * Get audit action type based on model
     */
    protected function getAuditActionType(string $event): string
    {
        $className = class_basename($this);
        
        return match($className) {
            'Setting' => 'setting_change',
            'Secret' => 'credential_change',
            'Module' => 'module_toggle',
            'Product' => 'price_change',
            'Order' => 'order_change',
            default => 'model_change',
        };
    }

    /**
     * Get audit description
     */
    protected function getAuditDescription(string $event): string
    {
        $className = class_basename($this);
        
        return ucfirst($event) . ' ' . strtolower($className);
    }

    /**
     * Get module name for audit log
     */
    protected function getAuditModule(): ?string
    {
        // Extract module from namespace or use model name
        $namespace = get_class($this);
        if (preg_match('/App\\\Modules\\\([^\\\\]+)/', $namespace, $matches)) {
            return strtolower($matches[1]);
        }
        
        return null;
    }

    /**
     * Get audit logs for this model
     */
    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest();
    }
}

