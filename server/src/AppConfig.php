<?php

/**
 * Settings that change between environments (secrets, credentials, URLs).
 * Readonly: set once by fromEnv(), then readable anywhere but never changed.
 */
final class AppConfig
{
    public function __construct(
        public readonly string $jwtSecret,
    ) {}

    public static function fromEnv(): self
    {
        return new self(
            jwtSecret: self::mustGetEnv('JWT_SECRET'),
        );
    }

    public static function mustGetEnv(string $key): string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            throw new RuntimeException("Missing required environment variable: {$key}");
        }

        return (string) $value;
    }
}
