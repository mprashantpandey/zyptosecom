<?php

namespace App\Core\Traits;

use Illuminate\Support\Facades\Crypt;

trait Encryptable
{
    /**
     * Encrypt value before saving
     */
    public function setEncryptedAttribute($key, $value): void
    {
        if (!empty($value)) {
            $this->attributes[$key] = Crypt::encryptString($value);
        }
    }

    /**
     * Decrypt value when accessing
     */
    public function getEncryptedAttribute($key): ?string
    {
        if (isset($this->attributes[$key]) && !empty($this->attributes[$key])) {
            try {
                return Crypt::decryptString($this->attributes[$key]);
            } catch (\Exception $e) {
                return null;
            }
        }
        
        return null;
    }
}

