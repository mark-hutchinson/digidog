<?php

namespace Api;

/** A player's public details, as returned by the API. */
class Player implements \JsonSerializable
{
    public function __construct(
        public readonly int $playerId,
        public readonly string $name,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            playerId: (int) $row['player_id'],
            name: $row['name'],
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'playerId' => $this->playerId,
            'name'     => $this->name,
        ];
    }
}
