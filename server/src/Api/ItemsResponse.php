<?php

namespace Api;

/**
 * GET /v1/items: the shop catalogue. Wrapped in an object rather than a bare array,
 * so fields can be added later without breaking clients.
 */
class ItemsResponse implements \JsonSerializable
{
    /** @param ShopItem[] $items */
    public function __construct(
        public readonly array $items,
    ) {}

    public function jsonSerialize(): array
    {
        return ['items' => $this->items];
    }
}
