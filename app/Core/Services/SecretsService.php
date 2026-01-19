<?php

namespace App\Core\Services;

use App\Models\Secret;
use Illuminate\Support\Facades\Crypt;

class SecretsService
{
    /**
     * Get decrypted secret value
     */
    public function get(string $providerType, string $providerName, string $key, string $environment = 'production'): ?string
    {
        $secret = Secret::where('provider_type', $providerType)
            ->where('provider_name', $providerName)
            ->where('key', $key)
            ->where('environment', $environment)
            ->where('is_active', true)
            ->first();
        
        if (!$secret) {
            return null;
        }
        
        try {
            return Crypt::decryptString($secret->encrypted_value);
        } catch (\Exception $e) {
            \Log::error("Failed to decrypt secret: {$providerType}/{$providerName}/{$key}", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get all secrets for a provider
     */
    public function getAllForProvider(string $providerType, string $providerName, string $environment = 'production'): array
    {
        $secrets = Secret::where('provider_type', $providerType)
            ->where('provider_name', $providerName)
            ->where('environment', $environment)
            ->where('is_active', true)
            ->get();
        
        $result = [];
        
        foreach ($secrets as $secret) {
            try {
                $result[$secret->key] = Crypt::decryptString($secret->encrypted_value);
            } catch (\Exception $e) {
                \Log::error("Failed to decrypt secret: {$secret->id}");
            }
        }
        
        return $result;
    }

    /**
     * Get public (non-secret) config for a provider
     */
    public function getPublicConfig(string $providerType, string $providerName, string $environment = 'production', array $schema = []): array
    {
        $all = $this->getAllForProvider($providerType, $providerName, $environment);
        $public = [];
        
        foreach ($schema as $field) {
            $key = $field['key'] ?? null;
            if ($key && isset($all[$key]) && !($field['is_secret'] ?? false)) {
                $public[$key] = $all[$key];
            }
        }
        
        return $public;
    }

    /**
     * Check if a credential key is configured
     */
    public function isConfigured(string $providerType, string $providerName, string $key, string $environment = 'production'): bool
    {
        return Secret::where('provider_type', $providerType)
            ->where('provider_name', $providerName)
            ->where('key', $key)
            ->where('environment', $environment)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Set encrypted secret
     */
    public function set(string $providerType, string $providerName, string $key, string $value, string $environment = 'production', bool $isActive = true): Secret
    {
        $encrypted = Crypt::encryptString($value);
        
        return Secret::updateOrCreate(
            [
                'provider_type' => $providerType,
                'provider_name' => $providerName,
                'key' => $key,
                'environment' => $environment,
            ],
            [
                'encrypted_value' => $encrypted,
                'is_active' => $isActive,
            ]
        );
    }

    /**
     * Set multiple credentials at once
     */
    public function setMany(string $providerType, string $providerName, array $credentials, string $environment = 'production'): void
    {
        foreach ($credentials as $key => $value) {
            if (!empty($value)) {
                $this->set($providerType, $providerName, $key, $value, $environment);
            }
        }
    }

    /**
     * Delete secret
     */
    public function delete(string $providerType, string $providerName, string $key, string $environment = 'production'): bool
    {
        return Secret::where('provider_type', $providerType)
            ->where('provider_name', $providerName)
            ->where('key', $key)
            ->where('environment', $environment)
            ->delete() > 0;
    }

    /**
     * Get all credentials for a provider (alias for getAllForProvider)
     */
    public function getCredentials(string $providerType, string $providerName, string $environment = 'production'): array
    {
        return $this->getAllForProvider($providerType, $providerName, $environment);
    }

    /**
     * Set credential (alias for set)
     */
    public function setCredential(string $providerType, string $providerName, string $key, string $value, string $environment = 'production', bool $isActive = true): Secret
    {
        return $this->set($providerType, $providerName, $key, $value, $environment, $isActive);
    }
}
