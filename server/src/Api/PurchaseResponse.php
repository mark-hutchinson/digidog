<?php

namespace Api;

/** POST /v1/purchases: the player's inventory after the purchase. */
class PurchaseResponse implements \JsonSerializable
{
    /** @param InventoryItem[] $inventory */
    public function __construct(
        public readonly array $inventory,
    ) {}

    public function jsonSerialize(): array
    {
        return ['inventory' => $this->inventory];
    }
}
