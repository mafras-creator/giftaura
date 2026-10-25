<?php
/**
 * GiftAura - the e-mails the shop sends, and the e-mail log.
 *
 * Every attempt is recorded in email_log (sent / saved / skipped / failed).
 * Sending NEVER breaks the main action: an order is saved even when the
 * e-mail fails; the failure is logged and visible on Admin -> E-mail log.
 */
declare(strict_types=1);

final class EmailService
{
    public const TEMPLATES = [
        'verify_email'   => 'E-mail verification',
        'reset_password' => 'Password reset',
        'order_placed'   => 'Order confirmation',
        'order_status'   => 'Order status update',
        'payment'        => 'Payment update',
        'inquiry_reply'  => 'Reply to an inquiry',
        'test'           => 'Test e-mail',
    ];

    /** Order statuses that trigger an e-mail to the customer. */
    private const STATUS_MESSAGES = [
        'confirmed'          => ['Your order is confirmed', 'Good news — we have confirmed your order and will start preparing it soon.'],
        'processing'         => ['We are preparing your order', 'Your gift is being prepared.'],
        'ready_for_delivery' => ['Your order is ready for delivery', 'Your order is packed and will be handed to the courier shortly.'],
        'delivered'          => ['Your order has been delivered', 'Your order has been delivered. We hope you enjoy it — thank you for shopping with us!'],
        'cancelled'          => ['Your order has been cancelled', 'Your order has been cancelled. If you paid by bank transfer, the store will contact you about the refund.'],
    ];

    // -----------------------------------------------------------------
    // Account e-mails
    // -----------------------------------------------------------------

    /** Send (or resend) the "verify your e-mail" link. Returns false if the hourly limit is reached. */
    public static function sendVerification(int $userId): bool
    {
        $user = self::user($userId);
        if ($user === null || $user['email_verified_at'] !== null) {
            return true;
        }
        if (TokenService::tooMany($userId, TokenService::VERIFY)) {
            return false;
        }
        $secret = TokenService::create($userId, TokenService::VERIFY);
        self::deliver('verify_email', $user, null, 'Please verify your e-mail address', 'emails/verify-email', [
            'name' => $user['full_name'],
            'link' => absolute_url('verify-email.php', ['token' => $secret]),
        ]);
        return true;
    }

    /**
     * Send a password-reset link to a CUSTOMER account with this e-mail.
     * Does nothing (silently) for unknown e-mails, administrators and inactive
     * accounts, so the page never reveals who has an account.
     */
    public static function sendPasswordReset(string $email): void
    {
        $user = Database::fetchOne(
            "SELECT id, full_name, email, email_verified_at FROM users
              WHERE email = :e AND role = 'customer' AND is_active = 1",
            ['e' => mb_strtolower(trim($email))]
        );
        if ($user === null || TokenService::tooMany((int) $user['id'], TokenService::RESET)) {
            return;
        }
        $secret = TokenService::create((int) $user['id'], TokenService::RESET);
        self::deliver('reset_password', $user, null, 'Reset your GiftAura password', 'emails/reset-password', [
            'name' => $user['full_name'],
            'link' => absolute_url('reset-password.php', ['token' => $secret]),
        ]);
    }

    // -----------------------------------------------------------------
    // Order e-mails
    // -----------------------------------------------------------------

    public static function orderPlaced(string $orderNumber): void
    {
        self::safely(static function () use ($orderNumber): void {
            $order = Database::fetchOne('SELECT * FROM orders WHERE order_number = :n', ['n' => $orderNumber]);
            if ($order === null) {
                return;
            }
            $payment = PaymentService::forOrder((int) $order['id']);
            self::deliver('order_placed', self::recipientFor($order), (int) $order['id'],
                'Order ' . $order['order_number'] . ' received — thank you!', 'emails/order-placed', [
                    'order'   => $order,
                    'items'   => OrderService::items((int) $order['id']),
                    'payment' => $payment,
                    'bank'    => PaymentService::bankDetails(),
                    'link'    => absolute_url('account/order.php', ['order' => $order['order_number']]),
                ]);
        });
    }

    public static function orderStatusChanged(int $orderId, string $status, ?string $note = null): void
    {
        if (!isset(self::STATUS_MESSAGES[$status])) {
            return;
        }
        self::safely(static function () use ($orderId, $status, $note): void {
            $order = Database::fetchOne('SELECT * FROM orders WHERE id = :id', ['id' => $orderId]);
            if ($order === null) {
                return;
            }
            [$headline, $message] = self::STATUS_MESSAGES[$status];
            self::deliver('order_status', self::recipientFor($order), $orderId,
                $headline . ' (' . $order['order_number'] . ')', 'emails/order-status', [
                    'order'    => $order,
                    'status'   => $status,
                    'headline' => $headline,
                    'message'  => $message,
                    'note'     => $note,
                    'link'     => absolute_url('account/order.php', ['order' => $order['order_number']]),
                    'reviewLink' => $status === 'delivered' ? absolute_url('account/reviews.php') : null,
                ]);
        });
    }

    public static function paymentChanged(int $orderId, string $paymentStatus): void
    {
        $texts = [
            'paid'     => ['Payment received', 'We have received your payment. Thank you!'],
            'refunded' => ['Payment refunded', 'Your payment has been refunded.'],
            'failed'   => ['We could not verify your payment', 'We could not find your bank transfer. Please check the details and send the reference again from your order page, or contact the store.'],
        ];
        if (!isset($texts[$paymentStatus])) {
            return;
        }
        self::safely(static function () use ($orderId, $paymentStatus, $texts): void {
            $order = Database::fetchOne('SELECT * FROM orders WHERE id = :id', ['id' => $orderId]);
            if ($order === null) {
                return;
            }
            [$headline, $message] = $texts[$paymentStatus];
            self::deliver('payment', self::recipientFor($order), $orderId,
                $headline . ' (' . $order['order_number'] . ')', 'emails/payment', [
                    'order'    => $order,
                    'headline' => $headline,
                    'message'  => $message,
                    'payment'  => PaymentService::forOrder($orderId),
                    'link'     => absolute_url('account/order.php', ['order' => $order['order_number']]),
                ]);
        });
    }

    /** The store's answer to a message sent from the Contact page. */
    public static function inquiryReply(array $inquiry, string $reply): void
    {
        self::safely(static function () use ($inquiry, $reply): void {
            $ref = InquiryService::reference((int) $inquiry['id']);
            self::deliver('inquiry_reply',
                ['id' => $inquiry['user_id'] !== null ? (int) $inquiry['user_id'] : null, 'full_name' => $inquiry['name'], 'email' => $inquiry['email']],
                null, 'Re: your message to ' . setting('store_name', 'GiftAura') . ' (' . $ref . ')', 'emails/inquiry-reply', [
                    'inquiry' => $inquiry,
                    'reply'   => $reply,
                    'ref'     => $ref,
                    'link'    => $inquiry['user_id'] !== null ? absolute_url('account/inquiries.php') : absolute_url('contact.php'),
                ]);
        });
    }

    /** Used by scripts/test_mail.php. */
    public static function sendTest(string $email): array
    {
        return self::deliver('test', ['id' => null, 'full_name' => '', 'email' => $email], null,
            'GiftAura test e-mail', 'emails/test', ['time' => date('Y-m-d H:i:s')]);
    }

    // -----------------------------------------------------------------
    // E-mail log (admin)
    // -----------------------------------------------------------------

    public static function log(array $filters, int $perPage = 30): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($filters['status'] ?? '', ['sent', 'saved', 'skipped', 'failed'], true)) {
            $where[] = 'l.status = :status';
            $params['status'] = $filters['status'];
        }
        if (isset(self::TEMPLATES[$filters['template'] ?? ''])) {
            $where[] = 'l.template = :tpl';
            $params['tpl'] = $filters['template'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = "l.recipient LIKE :q ESCAPE '!'";
            $params['q'] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']) . '%';
        }
        $whereSql = implode(' AND ', $where);
        $total = (int) Database::fetchValue("SELECT COUNT(*) FROM email_log l WHERE {$whereSql}", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, (int) ($filters['page'] ?? 1)), $pages);
        $items = Database::fetchAll(
            "SELECT l.*, o.order_number FROM email_log l LEFT JOIN orders o ON o.id = l.order_id
              WHERE {$whereSql} ORDER BY l.created_at DESC, l.id DESC LIMIT :limit OFFSET :offset",
            $params + ['limit' => $perPage, 'offset' => ($page - 1) * $perPage]
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public static function failedCount(): int
    {
        return (int) Database::fetchValue(
            "SELECT COUNT(*) FROM email_log WHERE status = 'failed' AND created_at > :since",
            ['since' => date('Y-m-d H:i:s', time() - 7 * 86400)]
        );
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    private static function user(int $userId): ?array
    {
        return Database::fetchOne('SELECT id, full_name, email, email_verified_at FROM users WHERE id = :id', ['id' => $userId]);
    }

    /** Order e-mails go to the address of the customer account. */
    private static function recipientFor(array $order): array
    {
        return ['id' => (int) $order['user_id'], 'full_name' => $order['customer_name'], 'email' => $order['customer_email']];
    }

    /** Render, send and log one e-mail. */
    private static function deliver(string $template, array $to, ?int $orderId, string $subject, string $view, array $data): array
    {
        try {
            $html = View::fetch($view, $data + ['subject' => $subject, 'layout' => 'email']);
            $text = self::toText($html);
            $result = Mailer::send($to['email'], (string) $to['full_name'], $subject, $html, $text);
        } catch (Throwable $e) {
            $result = ['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 250)];
        }
        try {
            Database::execute(
                'INSERT INTO email_log (user_id, order_id, template, recipient, subject, transport, status, error)
                 VALUES (:uid, :oid, :tpl, :to, :subject, :transport, :status, :error)',
                [
                    'uid' => $to['id'] ?? null, 'oid' => $orderId, 'tpl' => $template, 'to' => mb_substr($to['email'], 0, 190),
                    'subject' => mb_substr($subject, 0, 200), 'transport' => Mailer::transport(),
                    'status' => $result['status'], 'error' => $result['error'],
                ]
            );
        } catch (Throwable $e) {
            Logger::error('Could not write e-mail log', ['error' => $e->getMessage()]);
        }
        if ($result['status'] === 'failed') {
            Logger::warning('E-mail failed', ['template' => $template, 'error' => $result['error']]);
        }
        return $result;
    }

    /** Run an e-mail action without ever letting it break the caller. */
    private static function safely(callable $action): void
    {
        try {
            $action();
        } catch (Throwable $e) {
            Logger::error('E-mail could not be prepared', ['error' => $e->getMessage()]);
        }
    }

    /** Plain-text alternative made from the HTML. */
    private static function toText(string $html): string
    {
        $html = preg_replace('#<head\b.*?</head>#is', '', $html) ?? $html;
        $html = preg_replace('#</t[dh]>#i', '   ', $html) ?? $html;
        $html = preg_replace('#<(br|/p|/tr|/h1|/h2|/li|/div)[^>]*>#i', "\n", $html) ?? $html;
        $html = preg_replace('#<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>#is', '$2 ($1)', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        return trim(preg_replace("/\n\s*\n+/", "\n\n", $text) ?? $text);
    }
}
