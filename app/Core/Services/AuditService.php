<?php

namespace App\Core\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Log an audit event
     *
     * @param string $action Action type (e.g., 'setting_change', 'credential_change')
     * @param Model|null $entity The entity being audited
     * @param array $before Old values (before change)
     * @param array $after New values (after change)
     * @param array $metadata Additional metadata
     * @return AuditLog
     */
    public static function log(
        string $action,
        ?Model $entity = null,
        array $before = [],
        array $after = [],
        array $metadata = []
    ): AuditLog {
        $user = Auth::user();

        // For settings changes without a specific entity, use Setting model as placeholder
        if ($entity) {
            $auditableType = get_class($entity);
        } elseif (str_starts_with($action, 'settings.') || str_starts_with($action, 'translation.') || str_starts_with($action, 'currency.') || str_starts_with($action, 'language.')) {
            $auditableType = \App\Models\Setting::class;
        } else {
            $auditableType = null;
        }

        // If still null, use a generic placeholder to satisfy NOT NULL constraint
        if ($auditableType === null) {
            $auditableType = \App\Models\Setting::class; // Fallback to Setting for any non-entity logs
        }

        // For settings/translation/currency/language changes without a specific entity, use 0 as placeholder ID
        $auditableId = $entity?->getKey();
        if ($auditableId === null && (str_starts_with($action, 'settings.') || str_starts_with($action, 'translation.') || str_starts_with($action, 'currency.') || str_starts_with($action, 'language.'))) {
            $auditableId = 0; // Use 0 as placeholder for bulk/settings changes without specific entity
        }
        
        // Final fallback: if still null, set to 0 to satisfy NOT NULL constraint
        if ($auditableId === null) {
            $auditableId = 0;
        }

        return AuditLog::create([
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'user_id' => $user?->id,
            'event' => self::extractEventFromAction($action),
            'action_type' => $action,
            'description' => self::generateDescription($action, $entity, $before, $after),
            'old_values' => !empty($before) ? $before : null,
            'new_values' => !empty($after) ? $after : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'module' => $entity ? self::extractModule($entity) : $metadata['module'] ?? null,
        ]);
    }

    /**
     * Log a setting change
     */
    public static function logSettingChange(string $key, $oldValue, $newValue, ?string $module = null): AuditLog
    {
        return self::log(
            'setting_change',
            null,
            [$key => $oldValue],
            [$key => $newValue],
            ['module' => $module ?? 'settings']
        );
    }

    /**
     * Log a credential change (sanitized - never logs actual credentials)
     */
    public static function logCredentialChange(
        Model $provider,
        string $credentialKey,
        bool $hasOldValue,
        bool $hasNewValue,
        ?string $environment = null
    ): AuditLog {
        return self::log(
            'credential_change',
            $provider,
            [$credentialKey => $hasOldValue ? '[REDACTED]' : null],
            [$credentialKey => $hasNewValue ? '[REDACTED]' : null],
            [
                'module' => 'providers',
                'environment' => $environment,
                'sanitized' => true
            ]
        );
    }

    /**
     * Log a price change
     */
    public static function logPriceChange(Model $product, float $oldPrice, float $newPrice, ?string $currency = null): AuditLog
    {
        return self::log(
            'price_change',
            $product,
            ['price' => $oldPrice, 'currency' => $currency],
            ['price' => $newPrice, 'currency' => $currency],
            ['module' => 'pricing']
        );
    }

    /**
     * Log a module toggle
     */
    public static function logModuleToggle(Model $module, bool $oldStatus, bool $newStatus): AuditLog
    {
        return self::log(
            'module_toggle',
            $module,
            ['is_enabled' => $oldStatus],
            ['is_enabled' => $newStatus],
            ['module' => 'modules']
        );
    }

    /**
     * Log an order status change
     */
    public static function logOrderChange(Model $order, string $oldStatus, string $newStatus, ?string $note = null): AuditLog
    {
        return self::log(
            'order_change',
            $order,
            ['status' => $oldStatus],
            ['status' => $newStatus, 'note' => $note],
            ['module' => 'orders']
        );
    }

    /**
     * Extract event from action type
     */
    protected static function extractEventFromAction(string $action): string
    {
        if (str_contains($action, '_change')) {
            return 'updated';
        }
        if (str_contains($action, '_toggle')) {
            return 'updated';
        }
        if (str_contains($action, 'created')) {
            return 'created';
        }
        if (str_contains($action, 'deleted')) {
            return 'deleted';
        }
        return 'updated';
    }

    /**
     * Generate description
     */
    protected static function generateDescription(string $action, ?Model $entity, array $before, array $after): string
    {
        $entityName = $entity ? class_basename($entity) : 'Item';
        $actionLabel = str_replace('_', ' ', $action);
        
        return ucfirst($actionLabel) . ' for ' . strtolower($entityName);
    }

    /**
     * Extract module name from entity
     */
    protected static function extractModule(Model $entity): ?string
    {
        $className = class_basename($entity);
        
        // Map common models to modules
        return match($className) {
            'Setting' => 'settings',
            'Secret' => 'providers',
            'Module' => 'modules',
            'Product' => 'catalog',
            'Order' => 'orders',
            'User' => 'users',
            'Provider' => 'providers',
            'Brand' => 'branding',
            'Theme' => 'branding',
            'AppVersion' => 'branding',
            default => strtolower($className),
        };
    }

    /**
     * Get audit logs for an entity
     */
    public static function getLogsFor(Model $entity, int $limit = 50)
    {
        return AuditLog::where('auditable_type', get_class($entity))
            ->where('auditable_id', $entity->getKey())
            ->latest()
            ->limit($limit)
            ->get();
    }
}

