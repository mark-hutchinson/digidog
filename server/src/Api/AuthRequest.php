<?php

namespace Api;

/**
 * Body of POST /v1/auth/login. The constructor validates the input, so any
 * AuthRequest that exists is well formed. Bad input throws a 400
 */
class AuthRequest
{
    private const MAX_EXTERNAL_ID_LENGTH = 4096; // room for a Firebase ID token later
    private const MAX_PLAYER_NAME_LENGTH = 30;

    public readonly AuthProvider $provider;
    public readonly string $externalId; // device UUID; provider-specific checks happen in App
    public readonly ?string $playerName; // optional; only used for new players

    public function __construct(array $data)
    {
        $this->provider = self::parseProvider($data['provider'] ?? null);
        $this->externalId = self::parseExternalId($data['externalId'] ?? null);
        $this->playerName = self::parsePlayerName($data['playerName'] ?? null);
    }

    private static function parseProvider(mixed $value): AuthProvider
    {
        // is_int first: tryFrom() would throw a TypeError on a string like "abc".
        $provider = is_int($value) ? AuthProvider::tryFrom($value) : null;
        if ($provider === null) {
            throw HttpException::badRequest('invalid provider');
        }
        return $provider;
    }

    private static function parseExternalId(mixed $value): string
    {
        if (!is_string($value) || $value === '' || strlen($value) > self::MAX_EXTERNAL_ID_LENGTH) {
            throw HttpException::badRequest('externalId is required');
        }
        return $value;
    }

    private static function parsePlayerName(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw HttpException::badRequest('playerName must be a string');
        }

        $name = trim($value);
        // mb_strlen counts characters; strlen would count bytes ("Zoë" is 4 bytes).
        $length = mb_strlen($name);
        if ($length === 0 || $length > self::MAX_PLAYER_NAME_LENGTH) {
            throw HttpException::badRequest('playerName must be 1-' . self::MAX_PLAYER_NAME_LENGTH . ' characters');
        }
        // \p{C} = control and invisible characters (newlines, zero-width spaces, ...).
        // The u flag makes the regex work on UTF-8 characters rather than bytes.
        if (preg_match('/\p{C}/u', $name)) {
            throw HttpException::badRequest('playerName contains invalid characters');
        }
        return $name;
    }
}
