<?php

namespace Api;

/**
 * Body of POST /v1/purchases. The constructor validates the input, so any
 * PurchaseRequest that exists is well formed. Bad input throws a 400.
 * Whether the item is for sale, and affordable, is checked in App.
 */
class PurchaseRequest
{
    private const MAX_QUANTITY = 99;

    public readonly int $itemId;
    public readonly int $quantity;

    public function __construct(array $data)
    {
        $itemId = $data['itemId'] ?? null;
        if (!is_int($itemId) || $itemId <= 0) {
            throw HttpException::badRequest('itemId is required');
        }

        $quantity = $data['quantity'] ?? 1;
        if (!is_int($quantity) || $quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw HttpException::badRequest('quantity must be 1-' . self::MAX_QUANTITY);
        }

        $this->itemId = $itemId;
        $this->quantity = $quantity;
    }
}
