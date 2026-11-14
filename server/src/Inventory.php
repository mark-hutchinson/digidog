<?php

use Api\InventoryItem;

/**
 * Responsible for any modifications to the player's inventory. 
 *
 * These methods don't open transactions. A caller changing more than one row
 * (a purchase: money down, item up) wraps them in its own.
 */
class Inventory
{
    public const MONEY_ITEM_ID = 1;
    public const STARTING_MONEY = 100;

    public function __construct(private PDO $db) {}

    /** Add $quantity of an item, creating the row if needed. Returns the new quantity. */
    public function add(int $playerId, int $itemId, int $quantity): int
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException("quantity must be positive, got {$quantity}");
        }

        $stmt = $this->db->prepare(<<<SQL
            INSERT INTO inventory (player_id, item_id, quantity)
            VALUES (:player_id, :item_id, :quantity)
            ON CONFLICT (player_id, item_id) DO UPDATE
            SET quantity = inventory.quantity + EXCLUDED.quantity, updated_at = now()
            RETURNING quantity
        SQL);
        $stmt->execute(['player_id' => $playerId, 'item_id' => $itemId, 'quantity' => $quantity]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Take $quantity of an item away. Returns the new quantity, or null if the player
     * doesn't have enough, in which case nothing changes.
     */
    public function remove(int $playerId, int $itemId, int $quantity): ?int
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException("quantity must be positive, got {$quantity}");
        }

        // The quantity >= :quantity condition makes check-and-spend one statement,
        // so two requests can't both spend the same money.
        $stmt = $this->db->prepare(<<<SQL
            UPDATE inventory
            SET quantity = quantity - :quantity, updated_at = now()
            WHERE player_id = :player_id AND item_id = :item_id AND quantity >= :quantity
            RETURNING quantity
        SQL);
        $stmt->execute(['player_id' => $playerId, 'item_id' => $itemId, 'quantity' => $quantity]);

        $remaining = $stmt->fetchColumn(); // false when no row matched
        return $remaining === false ? null : (int) $remaining;
    }

    /** @return InventoryItem[] everything the player holds, money first */
    public function list(int $playerId): array
    {
        $stmt = $this->db->prepare(<<<SQL
            SELECT i.item_id, i.slug, i.name, i.category, inv.quantity
            FROM inventory inv
            JOIN item i USING (item_id)
            WHERE inv.player_id = :player_id AND inv.quantity > 0
            ORDER BY i.item_id
        SQL);
        $stmt->execute(['player_id' => $playerId]);
        return array_map(InventoryItem::fromRow(...), $stmt->fetchAll());
    }
}
