<?php

namespace App\Core\Contracts;

interface AuthProviderInterface
{
    /**
     * Send OTP
     */
    public function sendOtp(string $phone, ?array $options = []): array;

    /**
     * Verify OTP
     */
    public function verifyOtp(string $phone, string $otp, ?string $verificationId = null): array;

    /**
     * Create user from token (for Firebase, etc.)
     */
    public function createUserFromToken(string $token): array;

    /**
     * Authenticate user
     */
    public function authenticate(array $credentials): array;
}

