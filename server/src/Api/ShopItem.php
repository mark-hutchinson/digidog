<?php

namespace Api;

/** One item in the shop catalogue (GET /v1/items). */
class ShopItem implements \JsonSerializable
{
    public function __construct(
        public readonly int $itemId,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $category,
        public readonly int $price,
        public readonly array $effects,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            itemId: (int) $row['item_id'],
            slug: $row['slug'],
            name: $row['name'],
            category: $row['category'],
            price: (int) $row['price'],
            effects: json_decode($row['effects'], true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'itemId'   => $this->itemId,
            'slug'     => $this->slug,
            'name'     => $this->name,
            'category' => $this->category,
            'price'    => $this->price,
            // An object even when empty, so the client always gets {} rather than [].
            'effects'  => (object) $this->effects,
        ];
    }
}
