<?php

namespace Api;

/**
 * Throw from anywhere in a route to return a specific HTTP error.
 * The message is sent to the client, so keep it safe to show.
 */
class HttpException extends \RuntimeException
{

    public function __construct(
        public readonly int $status,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public static function badRequest(string $message = 'Bad request'): self
    {
        return new self(400, $message);
    }

    public static function notFound(string $message = 'Not found'): self
    {
        return new self(404, $message);
    }

    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return new self(401, $message);
    }
}
