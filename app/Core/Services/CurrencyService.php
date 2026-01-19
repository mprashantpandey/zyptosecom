<?php

namespace App\Core\Services;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Cache;

class CurrencyService
{
    protected const CACHE_KEY_ACTIVE = 'currencies:active';
    protected const CACHE_KEY_DEFAULT = 'currency:default';
    protected const CACHE_TTL = 3600;

    /**
     * Format amount with currency symbol
     */
    public function format(float $amount, ?string $currencyCode = null): string
    {
        $currency = $currencyCode ? $this->getByCode($currencyCode) : $this->getDefault();
        
        if (!$currency) {
            return number_format($amount, 2, '.', ',');
        }

        $formatted = number_format(
            $amount,
            $currency->decimals,
            $currency->decimal_separator,
            $currency->thousand_separator
        );

        if ($currency->symbol_position === 'before') {
            return $currency->symbol . $formatted;
        }

        return $formatted . ' ' . $currency->symbol;
    }

    /**
     * Convert amount from one currency to another
     */
    public function convert(float $amount, string $from, string $to): float
    {
        if ($from === $to) {
            return $amount;
        }

        $rate = $this->getRate($from, $to);
        return round($amount * $rate, 8);
    }

    /**
     * Get exchange rate between two currencies
     */
    public function getRate(string $from, string $to): float
    {
        return ExchangeRate::getRate($from, $to);
    }

    /**
     * Get active currencies
     */
    public function getActiveCurrencies(): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember(self::CACHE_KEY_ACTIVE, self::CACHE_TTL, function () {
            return Currency::getActive();
        });
    }

    /**
     * Get default currency
     */
    public function getDefaultCurrency(): ?Currency
    {
        return Cache::remember(self::CACHE_KEY_DEFAULT, self::CACHE_TTL, function () {
            return Currency::getDefault();
        });
    }

    /**
     * Get currency by code
     */
    public function getByCode(string $code): ?Currency
    {
        return Currency::where('code', $code)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Clear currency cache
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_ACTIVE);
        Cache::forget(self::CACHE_KEY_DEFAULT);
    }
}

