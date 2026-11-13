<?php

use Api\HealthResponse;
use Api\AuthProvider;
use Api\AuthRequest;
use Api\AuthResponse;
use Api\HttpException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class App
{
    private const DEFAULT_PLAYER_NAME = 'New Player';

    private const JWT_ALGORITHM = 'HS256';
    private const JWT_ISSUER = 'digidog';
    private const JWT_LIFETIME_SECONDS = 24 * 60 * 60; // 1 day

    private PDO $db;
    private AppConfig $config;

    public function __construct(PDO $db, AppConfig $appConfig)
    {
        $this->db = $db;
        $this->config = $appConfig;
    }

    public function healthCheck(): HealthResponse
    {
        $response  = new HealthResponse();
        return $response;
    }

    public function handleAuth(AuthRequest $request): AuthResponse
    {
        $provider = $request->provider;
        switch ($provider) {
            case AuthProvider::Device:
                // Any string is allowed for now to make testing easy.
                $externalId = $request->externalId;
                break;
            // TODO - firebase - verify the ID token and use its UID
            default:
                throw HttpException::badRequest('auth provider not supported yet');
        }

        $playerId = $this->recordLogin($provider, $externalId);

        if ($playerId === null) {
            try {
                $playerName = $request->playerName ?? self::DEFAULT_PLAYER_NAME;
                $playerId = $this->createPlayer($provider, $externalId, $playerName);
            } catch (PDOException $e) {
                // 23505 = unique violation. Another request created this login between our
                // lookup and insert (e.g. a double-tapped button), so use the player it made.
                if ($e->getCode() !== '23505') {
                    throw $e;
                }
                $playerId = $this->recordLogin($provider, $externalId) ?? throw $e;
            }
        }

        $token = $this->createJwt($playerId);
        $response = new AuthResponse($token);
        return $response;
    }

    /**
     * Look up an existing login and record the login time, in one statement.
     * Returns the player ID, or null if this login has never been seen.
     */
    private function recordLogin(AuthProvider $provider, string $externalId): ?int
    {
        $stmt = $this->db->prepare(<<<SQL
            UPDATE player_auth
            SET last_logged_in_at = now()
            WHERE provider = :provider AND external_id = :external_id
            RETURNING player_id
        SQL);
        $stmt->execute(['provider' => $provider->value, 'external_id' => $externalId]);

        $playerId = $stmt->fetchColumn(); // false when no row matched
        return $playerId === false ? null : (int) $playerId;
    }

    /**
     * Create a player and their first login together. The transaction means
     * we never end up with a player that has no way to log in.
     */
    private function createPlayer(AuthProvider $provider, string $externalId, string $name): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('INSERT INTO player (name) VALUES (:name) RETURNING player_id');
            $stmt->execute(['name' => $name]);
            $playerId = (int) $stmt->fetchColumn();

            $stmt = $this->db->prepare(<<<SQL
                INSERT INTO player_auth (provider, external_id, player_id)
                VALUES (:provider, :external_id, :player_id)
            SQL);
            $stmt->execute([
                'provider'    => $provider->value,
                'external_id' => $externalId,
                'player_id'   => $playerId,
            ]);

            $this->db->commit();
            return $playerId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function createJwt(int $playerId): string
    {
        $now = time();
        $payload = [
            'iss' => self::JWT_ISSUER,
            'sub' => (string) $playerId,
            'iat' => $now,
            'exp' => $now + self::JWT_LIFETIME_SECONDS,
        ];
        return JWT::encode($payload, $this->config->jwtSecret, self::JWT_ALGORITHM);
    }

    /** validate JWT token, and returns the player_id. throws an Unauthorized HTTPException if invalid token */
    public function validateToken(string $token): int
    {
        try {
            // Checks the signature and rejects expired tokens (exp).
            $claims = JWT::decode($token, new Key($this->config->jwtSecret, self::JWT_ALGORITHM));
        } catch (\UnexpectedValueException | \DomainException $e) {
            // Bad signature, expired, or malformed. Don't tell the client which one.
            throw HttpException::unauthorized('invalid token');
        }

        // decode() doesn't check these, so check them ourselves.
        if (($claims->iss ?? null) !== self::JWT_ISSUER || !ctype_digit($claims->sub ?? '')) {
            throw HttpException::unauthorized('invalid token');
        }

        return (int) $claims->sub;
    }
}
