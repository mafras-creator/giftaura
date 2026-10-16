<?php
/**
 * GiftAura - product, image and stock management for administrators.
 * Products are never hard-deleted (past orders and the stock ledger refer
 * to them); they are deactivated instead. Every stock change goes through
 * InventoryService so the ledger always matches the stock level.
 */
declare(strict_types=1);

final class ProductAdminService
{
    public const PER_PAGE = 20;
    public const MAX_IMAGES = 8;

    public const LABELS = [
        'name' => 'Product name', 'slug' => 'Web address (slug)', 'sku' => 'SKU / product code',
        'category_id' => 'Category', 'short_description' => 'Short description', 'description' => 'Description',
        'price' => 'Price', 'low_stock_threshold' => 'Low-stock alert level', 'initial_stock' => 'Opening stock',
    ];

    /**
     * Product list for the admin table.
     * Filters: q (name/SKU), category (id), status (active|inactive), stock (low|out), page.
     */
    public static function list(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (($filters['q'] ?? '') !== '') {
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']) . '%';
            $where[] = "(p.name LIKE :q1 ESCAPE '!' OR p.sku LIKE :q2 ESCAPE '!')";
            $params['q1'] = $like;
            $params['q2'] = $like;
        }
        if (!empty($filters['category'])) {
            $where[] = '(p.category_id = :cat OR c.parent_id = :cat2)';
            $params['cat'] = (int) $filters['category'];
            $params['cat2'] = (int) $filters['category'];
        }
        if (($filters['status'] ?? '') === 'active') {
            $where[] = 'p.is_active = 1';
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $where[] = 'p.is_active = 0';
        }
        if (($filters['stock'] ?? '') === 'low') {
            $where[] = 'p.stock_quantity > 0 AND p.stock_quantity <= p.low_stock_threshold';
        } elseif (($filters['stock'] ?? '') === 'out') {
            $where[] = 'p.stock_quantity = 0';
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) Database::fetchValue(
            "SELECT COUNT(*) FROM products p JOIN categories c ON c.id = p.category_id WHERE {$whereSql}", $params
        );
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, (int) ($filters['page'] ?? 1)), $pages);
        $items = Database::fetchAll(
            "SELECT p.id, p.name, p.slug, p.sku, p.price, p.stock_quantity, p.low_stock_threshold, p.is_active, p.is_featured,
                    p.updated_at, c.name AS category_name,
                    (SELECT pi.file_name FROM product_images pi WHERE pi.product_id = p.id
                      ORDER BY pi.is_primary DESC, pi.sort_order, pi.id LIMIT 1) AS image
               FROM products p JOIN categories c ON c.id = p.category_id
              WHERE {$whereSql}
              ORDER BY p.is_active DESC, p.name ASC
              LIMIT :limit OFFSET :offset",
            $params + ['limit' => self::PER_PAGE, 'offset' => ($page - 1) * self::PER_PAGE]
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public static function find(int $id): ?array
    {
        return Database::fetchOne('SELECT * FROM products WHERE id = :id', ['id' => $id]);
    }

    /**
     * Create ($id = null) or update a product.
     * Stock is NOT edited here (use adjustStock), except the opening stock of a new product.
     * Returns ['id' => int] or ['errors' => [...], 'old' => [...]].
     */
    public static function save(?int $id, array $input, int $adminId): array
    {
        $v = new Validator($input, self::LABELS);
        $v->rules([
            'name'                => ['required', 'min:2', 'max:150'],
            'slug'                => ['max:170', 'slug'],
            'sku'                 => ['max:40'],
            'category_id'         => ['required', 'integer'],
            'short_description'   => ['max:255'],
            'description'         => ['max:5000'],
            'price'               => ['required', 'decimal', 'min_value:0', 'max_value:9999999.99'],
            'low_stock_threshold' => ['required', 'integer', 'min_value:0', 'max_value:9999'],
        ]);
        if ($id === null) {
            $v->rules(['initial_stock' => ['required', 'integer', 'min_value:0', 'max_value:99999']]);
        }
        if ($v->value('sku') !== '' && !preg_match('/^[A-Za-z0-9][A-Za-z0-9\-_.]*$/', $v->value('sku'))) {
            $v->addError('sku', 'SKU may contain letters, numbers, - _ and . only.');
        }
        $slug = $v->value('slug') !== '' ? $v->value('slug') : slugify($v->value('name'));
        if ($v->passes() && $slug === '') {
            $v->addError('slug', 'Enter a web address using English letters and numbers, e.g. red-rose-bouquet.');
        }
        if ($v->passes() && CategoryAdminService::find((int) $v->value('category_id')) === null) {
            $v->addError('category_id', 'Choose a category.');
        }
        if ($v->passes() && Database::fetchValue('SELECT 1 FROM products WHERE slug = :s AND id <> :id', ['s' => $slug, 'id' => $id ?? 0]) !== null) {
            $v->addError('slug', 'Another product already uses this web address.');
        }
        if ($v->passes() && $v->value('sku') !== ''
            && Database::fetchValue('SELECT 1 FROM products WHERE sku = :s AND id <> :id', ['s' => $v->value('sku'), 'id' => $id ?? 0]) !== null) {
            $v->addError('sku', 'Another product already uses this SKU.');
        }

        $flags = [
            'is_active'   => ($input['is_active'] ?? '') === '1' ? 1 : 0,
            'is_featured' => ($input['is_featured'] ?? '') === '1' ? 1 : 0,
        ];
        if (!$v->passes()) {
            return ['errors' => $v->errors(), 'old' => $v->old() + array_map('strval', $flags)];
        }

        $params = [
            'category' => (int) $v->value('category_id'),
            'sku' => $v->value('sku') !== '' ? $v->value('sku') : null,
            'name' => $v->value('name'),
            'slug' => $slug,
            'short' => $v->value('short_description') !== '' ? $v->value('short_description') : null,
            'description' => $v->value('description') !== '' ? $v->value('description') : null,
            'price' => CartService::fromCents(CartService::toCents($v->value('price'))),
            'threshold' => (int) $v->value('low_stock_threshold'),
            'featured' => $flags['is_featured'],
            'active' => $flags['is_active'],
        ];

        if ($id === null) {
            $initial = (int) $v->value('initial_stock');
            $id = Database::transaction(static function () use ($params, $initial, $adminId): int {
                $newId = Database::insert(
                    'INSERT INTO products (category_id, sku, name, slug, short_description, description, price,
                                           stock_quantity, low_stock_threshold, is_featured, is_active)
                     VALUES (:category, :sku, :name, :slug, :short, :description, :price, 0, :threshold, :featured, :active)',
                    $params
                );
                if ($initial > 0) {
                    InventoryService::adjust($newId, $initial, 'initial_stock', null, $adminId, 'Opening stock');
                }
                return $newId;
            });
            ActivityLogService::log($adminId, 'product.create', 'product', $id, $params['name'] . ' @ ' . $params['price']);
        } else {
            $before = self::find($id);
            Database::execute(
                'UPDATE products SET category_id = :category, sku = :sku, name = :name, slug = :slug,
                        short_description = :short, description = :description, price = :price,
                        low_stock_threshold = :threshold, is_featured = :featured, is_active = :active
                  WHERE id = :id',
                $params + ['id' => $id]
            );
            $changes = [];
            if ($before !== null && CartService::toCents((string) $before['price']) !== CartService::toCents($params['price'])) {
                $changes[] = 'price ' . CartService::fromCents(CartService::toCents((string) $before['price'])) . ' -> ' . $params['price'];
            }
            if ($before !== null && (int) $before['is_active'] !== $params['active']) {
                $changes[] = $params['active'] ? 'activated' : 'deactivated';
            }
            ActivityLogService::log($adminId, 'product.update', 'product', $id, $params['name'] . ($changes ? ' (' . implode(', ', $changes) . ')' : ''));
        }
        return ['id' => $id];
    }

    public static function setActive(int $id, bool $active, int $adminId): bool
    {
        $done = Database::execute('UPDATE products SET is_active = :a WHERE id = :id', ['a' => $active ? 1 : 0, 'id' => $id]) > 0;
        if ($done) {
            ActivityLogService::log($adminId, $active ? 'product.activate' : 'product.deactivate', 'product', $id);
        }
        return $done;
    }

    // -----------------------------------------------------------------
    // Stock
    // -----------------------------------------------------------------

    /**
     * Add or remove stock. $mode: 'restock' (add N) or 'adjust' (+/- N with a reason).
     * Returns an error message or null.
     */
    public static function adjustStock(int $productId, string $mode, string $quantity, string $note, int $adminId): ?string
    {
        if (self::find($productId) === null) {
            return 'Product not found.';
        }
        if (!preg_match('/^-?\d{1,5}$/', trim($quantity)) || (int) $quantity === 0) {
            return 'Enter a whole number that is not zero.';
        }
        $change = (int) $quantity;
        $note = trim(mb_substr($note, 0, 255));
        if ($mode === 'restock') {
            if ($change < 0) {
                return 'A restock must add stock. Use "Correct stock" to remove items.';
            }
            $reason = 'restock';
        } elseif ($mode === 'adjust') {
            if ($note === '') {
                return 'Please give a reason for the correction (e.g. damaged, counted again).';
            }
            $reason = 'manual_adjustment';
        } else {
            return 'Unknown stock action.';
        }
        try {
            $after = Database::transaction(static fn() => InventoryService::adjust($productId, $change, $reason, null, $adminId, $note !== '' ? $note : null));
        } catch (RuntimeException $e) {
            return 'Stock cannot go below zero.';
        }
        ActivityLogService::log($adminId, 'stock.' . $reason, 'product', $productId, sprintf('%+d, now %d. %s', $change, $after, $note));
        return null;
    }

    /** Stock movements, newest first (optionally for one product). */
    public static function movements(?int $productId, int $page = 1, int $perPage = 30): array
    {
        $where = $productId ? 'm.product_id = :pid' : '1 = 1';
        $params = $productId ? ['pid' => $productId] : [];
        $total = (int) Database::fetchValue("SELECT COUNT(*) FROM inventory_movements m WHERE {$where}", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $items = Database::fetchAll(
            "SELECT m.*, p.name AS product_name, p.sku, o.order_number, u.full_name AS user_name, u.role AS user_role
               FROM inventory_movements m
               JOIN products p ON p.id = m.product_id
               LEFT JOIN orders o ON o.id = m.order_id
               LEFT JOIN users u ON u.id = m.user_id
              WHERE {$where}
              ORDER BY m.created_at DESC, m.id DESC
              LIMIT :limit OFFSET :offset",
            $params + ['limit' => $perPage, 'offset' => ($page - 1) * $perPage]
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    // -----------------------------------------------------------------
    // Images
    // -----------------------------------------------------------------

    public static function images(int $productId): array
    {
        return Database::fetchAll(
            'SELECT * FROM product_images WHERE product_id = :id ORDER BY is_primary DESC, sort_order ASC, id ASC',
            ['id' => $productId]
        );
    }

    /** Upload an image for a product. Returns an error message or null. */
    public static function addImage(int $productId, array $file, string $altText, int $adminId, bool $skipUploadCheck = false): ?string
    {
        if (self::find($productId) === null) {
            return 'Product not found.';
        }
        $count = (int) Database::fetchValue('SELECT COUNT(*) FROM product_images WHERE product_id = :id', ['id' => $productId]);
        if ($count >= self::MAX_IMAGES) {
            return 'A product can have up to ' . self::MAX_IMAGES . ' images. Delete one first.';
        }
        $result = ImageUploader::store($file, $skipUploadCheck);
        if (isset($result['error'])) {
            return $result['error'];
        }
        try {
            $imageId = Database::insert(
                'INSERT INTO product_images (product_id, file_name, alt_text, sort_order, is_primary)
                 VALUES (:pid, :file, :alt, :sort, :primary)',
                [
                    'pid' => $productId, 'file' => $result['file'],
                    'alt' => trim($altText) !== '' ? mb_substr(trim($altText), 0, 150) : null,
                    'sort' => $count, 'primary' => $count === 0 ? 1 : 0,
                ]
            );
        } catch (Throwable $e) {
            ImageUploader::delete($result['file']); // do not leave an orphan file behind
            throw $e;
        }
        ActivityLogService::log($adminId, 'image.upload', 'product', $productId, 'image #' . $imageId);
        return null;
    }

    public static function setPrimaryImage(int $productId, int $imageId, int $adminId): bool
    {
        $exists = Database::fetchValue('SELECT 1 FROM product_images WHERE id = :id AND product_id = :pid', ['id' => $imageId, 'pid' => $productId]);
        if ($exists === null) {
            return false;
        }
        Database::transaction(static function () use ($productId, $imageId): void {
            Database::execute('UPDATE product_images SET is_primary = 0 WHERE product_id = :pid', ['pid' => $productId]);
            Database::execute('UPDATE product_images SET is_primary = 1 WHERE id = :id AND product_id = :pid', ['id' => $imageId, 'pid' => $productId]);
        });
        ActivityLogService::log($adminId, 'image.primary', 'product', $productId, 'image #' . $imageId);
        return true;
    }

    public static function deleteImage(int $productId, int $imageId, int $adminId): bool
    {
        $image = Database::fetchOne('SELECT * FROM product_images WHERE id = :id AND product_id = :pid', ['id' => $imageId, 'pid' => $productId]);
        if ($image === null) {
            return false;
        }
        Database::execute('DELETE FROM product_images WHERE id = :id', ['id' => $imageId]);
        ImageUploader::delete($image['file_name']);
        // If the main image was deleted, the next image becomes the main one.
        if ((int) $image['is_primary'] === 1) {
            $next = Database::fetchValue('SELECT id FROM product_images WHERE product_id = :pid ORDER BY sort_order, id LIMIT 1', ['pid' => $productId]);
            if ($next !== null) {
                Database::execute('UPDATE product_images SET is_primary = 1 WHERE id = :id', ['id' => (int) $next]);
            }
        }
        ActivityLogService::log($adminId, 'image.delete', 'product', $productId, 'image #' . $imageId);
        return true;
    }

    public static function updateAltText(int $productId, int $imageId, string $alt): void
    {
        Database::execute(
            'UPDATE product_images SET alt_text = :alt WHERE id = :id AND product_id = :pid',
            ['alt' => trim($alt) !== '' ? mb_substr(trim($alt), 0, 150) : null, 'id' => $imageId, 'pid' => $productId]
        );
    }
}
