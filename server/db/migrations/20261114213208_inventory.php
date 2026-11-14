<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Items (the shop catalogue, plus money) and each player's inventory of them.
 *
 * Money is an item like any other, so every gain and spend in the game is one kind
 * of change: an inventory quantity going up or down.
 */
final class Inventory extends AbstractMigration
{
    public function up(): void
    {
        $sql = <<<SQL
-- item_id is set by hand, not generated: code refers to some items by id (money = 1),
-- so the ids must be the same in every environment.
-- price: null for items the shop doesn't sell (money).
-- effects: per-item numbers the game rules read, e.g. {"fun": 25, "break_chance": 0.05}
CREATE TABLE item (
    item_id    SMALLINT    PRIMARY KEY,
    slug       TEXT        NOT NULL UNIQUE,
    name       TEXT        NOT NULL,
    category   TEXT        NOT NULL CHECK (category IN ('currency', 'food', 'treat', 'toy', 'supply')),
    price      INTEGER     CHECK (price > 0),
    effects    JSONB       NOT NULL DEFAULT '{}',
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Placeholder prices and effects, to be tuned by playing.
INSERT INTO item (item_id, slug, name, category, price, effects) VALUES
    (1, 'money',     'Money',     'currency', NULL, '{}'),
    (2, 'food',      'Food',      'food',     5,    '{"fullness": 25}'),
    (3, 'treat',     'Treat',     'treat',    10,   '{"fun": 15, "energy": -20}'),
    (4, 'poop_bag',  'Poop bag',  'supply',   2,    '{}'),
    (5, 'toy_ball',  'Ball',      'toy',      15,   '{"fun": 20, "energy": -15, "break_chance": 0.15}'),
    (6, 'toy_bear',  'Bear',      'toy',      30,   '{"fun": 30, "energy": -15, "break_chance": 0.10}'),
    (7, 'toy_kong',  'Kong',      'toy',      60,   '{"fun": 25, "energy": -15, "break_chance": 0.02}');

-- The CHECK is the overdraft guard: the database refuses any change that would
-- take a quantity below zero, even if application code gets it wrong.
CREATE TABLE inventory (
    player_id  BIGINT      NOT NULL REFERENCES player (player_id) ON DELETE CASCADE,
    item_id    SMALLINT    NOT NULL REFERENCES item (item_id),
    quantity   INTEGER     NOT NULL CHECK (quantity >= 0),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (player_id, item_id)
);

-- Every player has a money row, so code never has to handle it missing.
-- Backfill it for players created before this migration.
INSERT INTO inventory (player_id, item_id, quantity)
SELECT player_id, 1, 100 FROM player;
SQL;
        $this->execute($sql);
    }

    public function down(): void
    {
        $sql = <<<SQL
    DROP TABLE inventory;
    DROP TABLE item;
SQL;
        $this->execute($sql);
    }
}
