<?php
/**
 * GiftAura - every change to products.stock_quantity goes through here.
 *
 * adjust() must be called INSIDE a database transaction. It changes the
 * stock with a single conditional UPDATE (so stock can never go below
 * zero, even if two orders arrive at the same moment) and writes the
 * matching row to inventory_movements.
 */
declare(strict_types=1);

final class InventoryService
{
    public const REASONS = ['initial_stock', 'restock', 'order_placed', 'order_cancelled', 'manual_adjustment'];

    /**
     * Change stock by $change (negative = remove). Returns the new stock level.
     * Throws RuntimeException if there is not enough stock.
     */
    public static function adjust(
        int $productId,
        int $change,
        string $reason,
        ?int $orderId = null,
        ?int $userId = null,
        ?string $note = null
    ): int {
        if ($change === 0) {
            throw new InvalidArgumentException('Stock change cannot be zero.');
        }
        if (!in_array($reason, self::REASONS, true)) {
            throw new InvalidArgumentException('Unknown stock movement reason.');
        }
        if (!Database::pdo()->inTransaction()) {
            throw new LogicException('InventoryService::adjust() must run inside a transaction.');
        }

        $updated = Database::execute(
            'UPDATE products
                SET stock_quantity = stock_quantity + :change1
              WHERE id = :id AND stock_quantity + :change2 >= 0',
            ['change1' => $change, 'change2' => $change, 'id' => $productId]
        );
        if ($updated !== 1) {
            throw new RuntimeException('Not enough stock for product #' . $productId . '.');
        }

        $stockAfter = (int) Database::fetchValue('SELECT stock_quantity FROM products WHERE id = :id', ['id' => $productId]);

        Database::execute(
            'INSERT INTO inventory_movements (product_id, quantity_change, stock_after, reason, order_id, user_id, note)
             VALUES (:pid, :change, :after, :reason, :oid, :uid, :note)',
            [
                'pid' => $productId, 'change' => $change, 'after' => $stockAfter, 'reason' => $reason,
                'oid' => $orderId, 'uid' => $userId, 'note' => $note !== null ? mb_substr($note, 0, 255) : null,
            ]
        );
        return $stockAfter;
    }
}
