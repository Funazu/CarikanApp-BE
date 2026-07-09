<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class JwtService
{
    protected string $secret;
    protected string $algorithm = 'HS256';

    public function __construct()
    {
        $this->secret = env('JWT_SECRET', 'carikan_fallback_secret_key_1234567890');
    }

    /**
     * Generate Access Token (expires in 15 minutes)
     */
    public function generateAccessToken(User $user): string
    {
        $payload = [
            'iss' => env('APP_URL', 'http://localhost'),
            'sub' => $user->id,
            'role' => $user->role,
            'email' => $user->email,
            'name' => $user->name,
            'iat' => time(),
            'exp' => time() + (15 * 60) // 15 minutes
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /**
     * Generate Refresh Token (expires in 7 days)
     */
    public function generateRefreshToken(User $user): string
    {
        $payload = [
            'iss' => env('APP_URL', 'http://localhost'),
            'sub' => $user->id,
            'type' => 'refresh',
            'iat' => time(),
            'exp' => time() + (7 * 24 * 60 * 60) // 7 days
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /**
     * Decode and validate token
     */
    public function decodeToken(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key($this->secret, $this->algorithm));
        } catch (Exception $e) {
            return null;
        }
    }
}
