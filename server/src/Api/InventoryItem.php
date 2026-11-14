<?php

namespace Api;

/** One line of a player's inventory, as returned by the API. */
class InventoryItem implements \JsonSerializable
{
    public function __construct(
        public readonly int $itemId,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $category,
        public readonly int $quantity,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            itemId: (int) $row['item_id'],
            slug: $row['slug'],
            name: $row['name'],
            category: $row['category'],
            quantity: (int) $row['quantity'],
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'itemId'   => $this->itemId,
            'slug'     => $this->slug,
            'name'     => $this->name,
            'category' => $this->category,
            'quantity' => $this->quantity,
        ];
    }
}
