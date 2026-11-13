<?php

namespace Api;

class AuthResponse implements \JsonSerializable
{
    public string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function jsonSerialize(): array
    {
        return ['token' => $this->token];
    }
}
