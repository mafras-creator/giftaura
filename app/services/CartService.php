<?php
/**
 * GiftAura - shopping cart.
 *
 * Guests: the cart lives in the session as [product_id => quantity].
 * Customers: the cart lives in carts / cart_items, so it survives logout
 * and works on any device. When a guest logs in, the session cart is
 * merged into their saved cart.
 *
 * Personalised products also keep the customer's message with the line
 * (session 'cart_msg' for guests, cart_items.customization_text for
 * customers). One message per product: adding the product again with a
 * new message replaces it.
 *
 * Only product ids, quantities and messages are stored. Prices are ALWAYS read
 * from the products table when the cart is shown or an order is placed,
 * so a price sent by the browser is never trusted.
 */
declare(strict_types=1);

final class CartService
{
    /** Hard upper limit (also enforced by a CHECK constraint on cart_items). */
    public const ABSOLUTE_MAX = 99;

    /** Largest quantity a customer may order of one product (setting, 1-99). */
    public static function maxPerItem(): int
    {
        $max = (int) setting('max_quantity_per_item', '10');
        return max(1, min(self::ABSOLUTE_MAX, $max));
    }

    // -----------------------------------------------------------------
    // Reading the cart
    // -----------------------------------------------------------------

    /** Raw contents: [product_id => quantity]. */
    public static function raw(): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            $cart = $_SESSION['cart'] ?? [];
            return is_array($cart) ? array_map('intval', $cart) : [];
        }
        $rows = Database::fetchAll(
            'SELECT ci.product_id, ci.quantity
               FROM cart_items ci JOIN carts c ON c.id = ci.cart_id
              WHERE c.user_id = :uid
              ORDER BY ci.added_at, ci.id',
            ['uid' => $userId]
        );
        $cart = [];
        foreach ($rows as $row) {
            $cart[(int) $row['product_id']] = (int) $row['quantity'];
        }
        return $cart;
    }

    /** Personalisation messages: [product_id => text]. */
    public static function messages(): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            $messages = $_SESSION['cart_msg'] ?? [];
            return is_array($messages) ? array_map('strval', $messages) : [];
        }
        $rows = Database::fetchAll(
            'SELECT ci.product_id, ci.customization_text
               FROM cart_items ci JOIN carts c ON c.id = ci.cart_id
              WHERE c.user_id = :uid AND ci.customization_text IS NOT NULL',
            ['uid' => $userId]
        );
        $messages = [];
        foreach ($rows as $row) {
            $messages[(int) $row['product_id']] = (string) $row['customization_text'];
        }
        return $messages;
    }

    /**
     * Tidy a message typed by the customer: Windows/Mac line breaks become
     * "\n", other control characters are removed, at most 2 empty lines in a
     * row, no spaces at the start or end. The length is checked separately.
     */
    public static function cleanMessage(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[^\P{Cc}\n]/u', '', $text) ?? ''; // keep Sinhala/Tamil joiners (format characters)
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        return trim($text);
    }

    /**
     * Check a message for a product. Returns [cleanText|null, error|null].
     * Products that cannot be personalised never keep a message.
     */
    public static function checkMessage(array $product, string $text): array
    {
        if ((int) ($product['is_customizable'] ?? 0) !== 1) {
            return [null, null];
        }
        $text = self::cleanMessage($text);
        $max = max(10, min(500, (int) ($product['custom_max_length'] ?? 100)));
        if ($text === '') {
            return [null, (int) ($product['custom_required'] ?? 1) === 1
                ? 'Please type your personalisation for ' . $product['name'] . ' before adding it to the cart.' : null];
        }
        if (mb_strlen($text) > $max) {
            return [null, 'Your message is too long. Use up to ' . $max . ' characters (you typed ' . mb_strlen($text) . ').'];
        }
        return [$text, null];
    }

    /** Total number of units, for the header badge. */
    public static function count(): int
    {
        return array_sum(self::raw());
    }

    /**
     * Full cart with live product data and totals:
     * [
     *   'lines'    => [[product_id, name, slug, sku, price, quantity, line_total,
     *                   stock_quantity, image, problem (null|string)], ...],
     *   'subtotal' => '4500.00', 'delivery_fee' => '400.00', 'total' => '4900.00',
     *   'units' => 3, 'has_problems' => bool, 'fingerprint' => string
     * ]
     * A line has a "problem" when the product is no longer available or
     * there is not enough stock; such carts cannot be checked out.
     */
    public static function summary(): array
    {
        $raw = self::raw();
        $messages = $raw !== [] ? self::messages() : [];
        $lines = [];
        $subtotalCents = 0;
        $hasProblems = false;

        if ($raw !== []) {
            $visible = CategoryService::visibleIds();
            $params = [];
            $in = [];
            foreach (array_keys($raw) as $i => $id) {
                $in[] = ':p' . $i;
                $params['p' . $i] = $id;
            }
            $products = Database::fetchAll(
                'SELECT p.id, p.name, p.slug, p.sku, p.price, p.stock_quantity, p.is_active, p.category_id,
                        p.is_customizable, p.custom_label, p.custom_max_length, p.custom_required,
                        (SELECT pi.file_name FROM product_images pi WHERE pi.product_id = p.id
                          ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS image
                   FROM products p
                  WHERE p.id IN (' . implode(', ', $in) . ')',
                $params
            );
            $byId = [];
            foreach ($products as $product) {
                $byId[(int) $product['id']] = $product;
            }

            foreach ($raw as $productId => $quantity) {
                $product = $byId[$productId] ?? null;
                if ($product === null) {
                    // Product row no longer exists: drop it silently.
                    self::remove($productId);
                    continue;
                }
                $available = (int) $product['is_active'] === 1 && in_array((int) $product['category_id'], $visible, true);
                $stock = (int) $product['stock_quantity'];
                $customizable = (int) $product['is_customizable'] === 1;
                $message = $customizable ? ($messages[$productId] ?? '') : '';
                $problem = null;
                if (!$available) {
                    $problem = 'This product is no longer available. Please remove it.';
                } elseif ($stock <= 0) {
                    $problem = 'Out of stock. Please remove it to continue.';
                } elseif ($quantity > $stock) {
                    $problem = 'Only ' . $stock . ' left in stock. Please lower the quantity.';
                } elseif ($customizable && $message === '' && (int) $product['custom_required'] === 1) {
                    $problem = 'Please add your personalisation message below.';
                } elseif ($customizable && mb_strlen($message) > (int) $product['custom_max_length']) {
                    $problem = 'Your message is longer than ' . (int) $product['custom_max_length'] . ' characters. Please shorten it.';
                }
                $hasProblems = $hasProblems || $problem !== null;

                $priceCents = self::toCents((string) $product['price']);
                $lineCents = $priceCents * $quantity;
                $subtotalCents += $lineCents;

                $lines[] = [
                    'product_id'     => $productId,
                    'name'           => $product['name'],
                    'slug'           => $product['slug'],
                    'sku'            => $product['sku'],
                    'price'          => self::fromCents($priceCents),
                    'quantity'       => $quantity,
                    'line_total'     => self::fromCents($lineCents),
                    'stock_quantity' => $stock,
                    'image'          => $product['image'],
                    'available'      => $available,
                    'problem'        => $problem,
                    'customizable'   => $customizable,
                    'custom_label'   => self::customLabel($product),
                    'custom_max'     => (int) $product['custom_max_length'],
                    'custom_required'=> (int) $product['custom_required'] === 1,
                    'message'        => $message,
                ];
            }
        }

        $deliveryCents = $lines === [] ? 0 : self::deliveryFeeCents($subtotalCents);
        $fingerprintSource = array_map(
            static fn(array $l): string => self::fingerprintPart($l['product_id'], $l['quantity'], $l['price'], $l['message']),
            $lines
        );

        return [
            'lines'        => $lines,
            'subtotal'     => self::fromCents($subtotalCents),
            'delivery_fee' => self::fromCents($deliveryCents),
            'total'        => self::fromCents($subtotalCents + $deliveryCents),
            'units'        => array_sum(array_column($lines, 'quantity')),
            'has_problems' => $hasProblems,
            'fingerprint'  => hash('sha256', implode('|', $fingerprintSource) . '|' . $deliveryCents),
        ];
    }

    /**
     * One cart line in the checkout fingerprint. The message is included, so
     * the order is refused if it was changed in another tab after the
     * customer opened the checkout page.
     */
    public static function fingerprintPart(int $productId, int $quantity, string $price, string $message): string
    {
        return $productId . ':' . $quantity . ':' . $price . ($message !== '' ? ':' . hash('sha256', $message) : '');
    }

    /** The question shown above the message box. */
    public static function customLabel(array $product): string
    {
        $label = trim((string) ($product['custom_label'] ?? ''));
        return $label !== '' ? $label : ProductAdminService::DEFAULT_CUSTOM_LABEL;
    }

    /**
     * Delivery fee for a subtotal (in cents), from the settings table:
     * free when a threshold is set and reached, otherwise the standard fee.
     */
    public static function deliveryFeeCents(int $subtotalCents): int
    {
        $fee = self::toCents(setting('delivery_fee', '0'));
        $threshold = self::toCents(setting('free_delivery_threshold', '0'));
        if ($threshold > 0 && $subtotalCents >= $threshold) {
            return 0;
        }
        return max(0, $fee);
    }

    // -----------------------------------------------------------------
    // Changing the cart. Each method returns a message for the customer.
    // -----------------------------------------------------------------

    /** Add a product (or increase its quantity). Returns ['ok' => bool, 'message' => string]. */
    public static function add(int $productId, int $quantity, string $customText = ''): array
    {
        $product = self::sellableProduct($productId);
        if ($product === null) {
            return ['ok' => false, 'message' => 'Sorry, this product is not available.'];
        }
        if ((int) $product['stock_quantity'] <= 0) {
            return ['ok' => false, 'message' => $product['name'] . ' is out of stock.'];
        }
        [$text, $error] = self::checkMessage($product, $customText);
        if ($error !== null) {
            return ['ok' => false, 'message' => $error];
        }
        $quantity = max(1, $quantity);
        $current = self::raw()[$productId] ?? 0;
        $wanted = $current + $quantity;
        $limit = min(self::maxPerItem(), (int) $product['stock_quantity']);
        $final = min($wanted, $limit);
        $isCustom = (int) $product['is_customizable'] === 1;
        $oldText = self::messages()[$productId] ?? '';

        if ($final <= $current) {
            if ($isCustom && $current > 0 && $text !== null && $text !== $oldText) {
                self::store($productId, $current, $text);
                return ['ok' => true, 'message' => 'Your personalisation for ' . $product['name'] . ' was updated.'];
            }
            return ['ok' => false, 'message' => 'You already have the maximum available quantity (' . $limit . ') of ' . $product['name'] . ' in your cart.'];
        }
        self::store($productId, $final, $isCustom ? ($text ?? ($current > 0 ? $oldText : '')) : null);

        $message = $product['name'] . ' was added to your cart.';
        if ($isCustom && $current > 0 && $text !== null && $oldText !== '' && $text !== $oldText) {
            $message .= ' Your personalisation was replaced with the new message (one message per product).';
        }
        if ($final < $wanted) {
            $message .= ' The quantity was limited to ' . $final . '.';
        }
        return ['ok' => true, 'message' => $message];
    }

    /** Set an exact quantity (0 removes the line). */
    public static function update(int $productId, int $quantity): array
    {
        if (!array_key_exists($productId, self::raw())) {
            return ['ok' => false, 'message' => 'That item is not in your cart.'];
        }
        if ($quantity <= 0) {
            self::remove($productId);
            return ['ok' => true, 'message' => 'The item was removed from your cart.'];
        }
        $product = self::sellableProduct($productId);
        if ($product === null) {
            return ['ok' => false, 'message' => 'This product is no longer available. Please remove it.'];
        }
        $limit = min(self::maxPerItem(), max(0, (int) $product['stock_quantity']));
        if ($limit === 0) {
            return ['ok' => false, 'message' => $product['name'] . ' is out of stock. Please remove it.'];
        }
        $final = min($quantity, $limit);
        self::store($productId, $final);
        return [
            'ok' => true,
            'message' => $final < $quantity
                ? 'Only ' . $final . ' of ' . $product['name'] . ' can be ordered, so the quantity was set to ' . $final . '.'
                : 'Your cart was updated.',
        ];
    }

    /** Change the personalisation message of a cart line. */
    public static function setMessage(int $productId, string $customText): array
    {
        if (!array_key_exists($productId, self::raw())) {
            return ['ok' => false, 'message' => 'That item is not in your cart.'];
        }
        $product = self::sellableProduct($productId);
        if ($product === null) {
            return ['ok' => false, 'message' => 'This product is no longer available. Please remove it.'];
        }
        if ((int) $product['is_customizable'] !== 1) {
            return ['ok' => false, 'message' => $product['name'] . ' cannot be personalised.'];
        }
        [$text, $error] = self::checkMessage($product, $customText);
        if ($error !== null) {
            return ['ok' => false, 'message' => $error];
        }
        self::store($productId, self::raw()[$productId], $text ?? '');
        return ['ok' => true, 'message' => $text === null ? 'Your personalisation was removed.' : 'Your personalisation was saved.'];
    }

    public static function remove(int $productId): void
    {
        $userId = Auth::id();
        if ($userId === null) {
            unset($_SESSION['cart'][$productId], $_SESSION['cart_msg'][$productId]);
            return;
        }
        Database::execute(
            'DELETE FROM cart_items
              WHERE product_id = :pid AND cart_id = (SELECT id FROM carts WHERE user_id = :uid)',
            ['uid' => $userId, 'pid' => $productId]
        );
    }

    /** Empty the customer's saved cart (used inside the order transaction). */
    public static function clearForUser(int $userId): void
    {
        Database::execute(
            'DELETE FROM cart_items WHERE cart_id = (SELECT id FROM carts WHERE user_id = :uid)',
            ['uid' => $userId]
        );
    }

    /**
     * Called right after login/registration: move the guest's session cart
     * into the saved cart. Quantities are added together and capped.
     */
    public static function mergeSessionCart(int $userId): void
    {
        $guest = $_SESSION['cart'] ?? [];
        $guestMessages = is_array($_SESSION['cart_msg'] ?? null) ? $_SESSION['cart_msg'] : [];
        unset($_SESSION['cart'], $_SESSION['cart_msg']);
        if (!is_array($guest) || $guest === []) {
            return;
        }
        $saved = self::raw();
        foreach ($guest as $productId => $quantity) {
            $productId = (int) $productId;
            $product = self::sellableProduct($productId);
            if ($product === null || (int) $product['stock_quantity'] <= 0) {
                continue;
            }
            $limit = min(self::maxPerItem(), (int) $product['stock_quantity']);
            $total = min(($saved[$productId] ?? 0) + (int) $quantity, $limit);
            if ($total > 0) {
                // A message typed as a guest wins over an older saved one.
                $text = (int) $product['is_customizable'] === 1 && isset($guestMessages[$productId]) ? (string) $guestMessages[$productId] : null;
                self::storeForUser($userId, $productId, $total, $text);
            }
        }
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    /** Active product in a visible category, or null. */
    private static function sellableProduct(int $productId): ?array
    {
        if ($productId <= 0) {
            return null;
        }
        $product = Database::fetchOne(
            'SELECT id, name, stock_quantity, category_id, is_customizable, custom_label, custom_max_length, custom_required
               FROM products WHERE id = :id AND is_active = 1',
            ['id' => $productId]
        );
        if ($product === null || !in_array((int) $product['category_id'], CategoryService::visibleIds(), true)) {
            return null;
        }
        return $product;
    }

    /** $text: null = leave the message as it is, '' = no message, otherwise the new message. */
    private static function store(int $productId, int $quantity, ?string $text = null): void
    {
        $userId = Auth::id();
        if ($userId === null) {
            $_SESSION['cart'][$productId] = $quantity;
            if ($text !== null) {
                if ($text === '') {
                    unset($_SESSION['cart_msg'][$productId]);
                } else {
                    $_SESSION['cart_msg'][$productId] = $text;
                }
            }
            return;
        }
        self::storeForUser($userId, $productId, $quantity, $text);
    }

    private static function storeForUser(int $userId, int $productId, int $quantity, ?string $text = null): void
    {
        $cartId = self::cartIdFor($userId);
        $params = ['qty' => $quantity, 'cart' => $cartId, 'pid' => $productId];
        $setText = '';
        if ($text !== null) {
            $setText = ', customization_text = :txt';
            $params['txt'] = $text !== '' ? $text : null;
        }
        $updated = Database::execute(
            'UPDATE cart_items SET quantity = :qty' . $setText . ' WHERE cart_id = :cart AND product_id = :pid',
            $params
        );
        if ($updated === 0 && !self::lineExists($cartId, $productId)) {
            Database::execute(
                'INSERT INTO cart_items (cart_id, product_id, quantity, customization_text) VALUES (:cart, :pid, :qty, :txt)',
                ['cart' => $cartId, 'pid' => $productId, 'qty' => $quantity, 'txt' => $text !== null && $text !== '' ? $text : null]
            );
        }
    }

    private static function lineExists(int $cartId, int $productId): bool
    {
        return Database::fetchValue(
            'SELECT 1 FROM cart_items WHERE cart_id = :cart AND product_id = :pid',
            ['cart' => $cartId, 'pid' => $productId]
        ) !== null;
    }

    private static function cartIdFor(int $userId): int
    {
        $id = Database::fetchValue('SELECT id FROM carts WHERE user_id = :uid', ['uid' => $userId]);
        if ($id !== null) {
            return (int) $id;
        }
        return Database::insert('INSERT INTO carts (user_id) VALUES (:uid)', ['uid' => $userId]);
    }

    /** Money is calculated in whole cents to avoid floating-point rounding errors. */
    public static function toCents(string $amount): int
    {
        if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', trim($amount), $m)) {
            return 0;
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }

    public static function fromCents(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
