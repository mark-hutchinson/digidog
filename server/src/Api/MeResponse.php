<?php

namespace Api;

/** GET /v1/me: everything the main screen needs on page load. Pets get added here later. */
class MeResponse implements \JsonSerializable
{
    /** @param InventoryItem[] $inventory */
    public function __construct(
        public readonly Player $player,
        public readonly array $inventory,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'player'    => $this->player,
            'inventory' => $this->inventory,
        ];
    }
}
