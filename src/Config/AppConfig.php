<?php
declare(strict_types=1);

namespace App\Config;

use Dotenv\Dotenv;

/**
 * Application configuration loader.
 * Loads environment variables from .env (local dev) or server environment (production).
 */
class AppConfig
{
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        // Load .env file if it exists (local development)
        if (file_exists(__DIR__ . '/../../.env')) {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
            $dotenv->load();
        }

        self::$loaded = true;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        self::load();
        return getenv($key) ?: $default;
    }

    public static function require(string $key): string
    {
        $value = self::get($key);
        if ($value === null) {
            throw new \RuntimeException("Required environment variable '{$key}' is not set");
        }
        return $value;
    }

    public static function isDev(): bool
    {
        return self::get('APP_ENV', 'development') === 'development';
    }

    public static function isProduction(): bool
    {
        return self::get('APP_ENV', 'development') === 'production';
    }
}
