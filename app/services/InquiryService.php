<?php
/**
 * GiftAura - customer inquiries (messages from the Contact page) and the
 * store's replies.
 *
 * - Anyone can send a message; a logged-in customer's message is linked to
 *   their account and shown in My account -> My inquiries.
 * - Spam protection: a hidden "website" field that people never fill in,
 *   and at most 5 messages per hour from one IP address or e-mail address.
 * - The super administrator and staff with the "inquiries" permission read
 *   and answer them in Admin -> Inquiries. Each reply is saved and e-mailed
 *   to the customer.
 */
declare(strict_types=1);

final class InquiryService
{
    public const SUBJECTS = [
        'order'           => 'A question about my order',
        'product'         => 'A question about a product',
        'personalisation' => 'Personalisation / custom gift',
        'delivery'        => 'Delivery',
        'other'           => 'Something else',
    ];
    public const STATUSES = ['new' => 'New', 'in_progress' => 'In progress', 'resolved' => 'Resolved'];
    public const MESSAGE_MAX = 2000;
    public const REPLY_MAX = 3000;
    public const PER_HOUR = 5;
    public const ADMIN_PER_PAGE = 25;

    /**
     * Save a message from the Contact page.
     * Returns ['id' => int] on success, ['errors' => [...], 'old' => [...]] on
     * failure, or ['id' => 0] when the spam trap was filled in (treated as sent).
     */
    public static function submit(array $input, ?array $user): array
    {
        $old = [
            'name'         => trim((string) ($input['name'] ?? '')),
            'email'        => mb_strtolower(trim((string) ($input['email'] ?? ''))),
            'phone'        => trim((string) ($input['phone'] ?? '')),
            'subject'      => (string) ($input['subject'] ?? ''),
            'order_number' => mb_strtoupper(trim((string) ($input['order_number'] ?? ''))),
            'message'      => CartService::cleanMessage((string) ($input['message'] ?? '')),
        ];
        if (trim((string) ($input['website'] ?? '')) !== '') {
            Logger::info('Contact form spam trap filled', ['ip' => client_ip()]);
            return ['id' => 0];
        }

        $v = new Validator($old, ['name' => 'Your name', 'email' => 'Your e-mail', 'phone' => 'Phone number']);
        $v->rules(['name' => ['required', 'min:2', 'max:100'], 'email' => ['required', 'email', 'max:190'], 'phone' => ['phone']]);
        $errors = $v->errors();
        if (!isset(self::SUBJECTS[$old['subject']])) {
            $errors['subject'] = 'Choose what your message is about.';
        }
        if ($old['order_number'] !== '' && !OrderService::isOrderNumber($old['order_number'])) {
            $errors['order_number'] = 'Order references look like GA261018-KLH46R. Leave it empty if you do not have one.';
        }
        $len = mb_strlen($old['message']);
        if ($len < 10 || $len > self::MESSAGE_MAX) {
            $errors['message'] = 'Please write your message (10 to ' . self::MESSAGE_MAX . ' characters' . ($len > 0 ? ', you typed ' . $len : '') . ').';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'old' => $old];
        }

        $since = date('Y-m-d H:i:s', time() - 3600);
        $recent = (int) Database::fetchValue(
            'SELECT COUNT(*) FROM customer_inquiries WHERE created_at > :since AND (ip_address = :ip OR email = :email)',
            ['since' => $since, 'ip' => client_ip(), 'email' => $old['email']]
        );
        if ($recent >= self::PER_HOUR) {
            return ['errors' => ['message' => 'You have sent several messages in the last hour. Please wait a little before sending another.'], 'old' => $old];
        }

        $id = Database::insert(
            'INSERT INTO customer_inquiries (user_id, name, email, phone, subject, order_number, message, ip_address, created_at)
             VALUES (:uid, :name, :email, :phone, :subject, :order, :message, :ip, :now)',
            [
                'uid' => $user !== null && $user['role'] === 'customer' ? $user['id'] : null,
                'name' => $old['name'], 'email' => $old['email'], 'phone' => $v->value('phone') !== '' ? $v->value('phone') : null,
                'subject' => $old['subject'], 'order' => $old['order_number'] !== '' ? $old['order_number'] : null,
                'message' => $old['message'], 'ip' => client_ip(), 'now' => date('Y-m-d H:i:s'),
            ]
        );
        Logger::info('Inquiry received', ['inquiry_id' => $id]);
        return ['id' => $id];
    }

    /** A customer's own inquiries with the store's replies, newest first. */
    public static function forUser(int $userId): array
    {
        $items = Database::fetchAll(
            'SELECT * FROM customer_inquiries WHERE user_id = :uid ORDER BY created_at DESC, id DESC',
            ['uid' => $userId]
        );
        foreach ($items as &$item) {
            $item['replies'] = self::replies((int) $item['id']);
        }
        return $items;
    }

    public static function find(int $id): ?array
    {
        return Database::fetchOne('SELECT * FROM customer_inquiries WHERE id = :id', ['id' => $id]);
    }

    public static function replies(int $inquiryId): array
    {
        return Database::fetchAll(
            'SELECT r.*, u.full_name AS admin_name, u.role AS admin_role
               FROM inquiry_replies r LEFT JOIN users u ON u.id = r.admin_id
              WHERE r.inquiry_id = :id ORDER BY r.created_at ASC, r.id ASC',
            ['id' => $inquiryId]
        );
    }

    public static function countNew(): int
    {
        try {
            return (int) Database::fetchValue("SELECT COUNT(*) FROM customer_inquiries WHERE status = 'new'");
        } catch (Throwable) {
            return 0; // table not created yet (update script not run): never break the admin panel
        }
    }

    /** Filters: status, subject, q (name, e-mail, order reference or text), page. */
    public static function adminList(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (isset(self::STATUSES[$filters['status'] ?? ''])) {
            $where[] = 'i.status = :status';
            $params['status'] = $filters['status'];
        }
        if (isset(self::SUBJECTS[$filters['subject'] ?? ''])) {
            $where[] = 'i.subject = :subject';
            $params['subject'] = $filters['subject'];
        }
        if (($filters['q'] ?? '') !== '') {
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], (string) $filters['q']) . '%';
            $where[] = "(i.name LIKE :q1 ESCAPE '!' OR i.email LIKE :q2 ESCAPE '!' OR i.order_number LIKE :q3 ESCAPE '!' OR i.message LIKE :q4 ESCAPE '!')";
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }
        $whereSql = implode(' AND ', $where);
        $total = (int) Database::fetchValue("SELECT COUNT(*) FROM customer_inquiries i WHERE {$whereSql}", $params);
        $pages = max(1, (int) ceil($total / self::ADMIN_PER_PAGE));
        $page = min(max(1, (int) ($filters['page'] ?? 1)), $pages);
        $items = Database::fetchAll(
            "SELECT i.*, (SELECT COUNT(*) FROM inquiry_replies r WHERE r.inquiry_id = i.id) AS reply_count
               FROM customer_inquiries i WHERE {$whereSql}
              ORDER BY (i.status = 'new') DESC, i.created_at DESC, i.id DESC
              LIMIT :limit OFFSET :offset",
            $params + ['limit' => self::ADMIN_PER_PAGE, 'offset' => ($page - 1) * self::ADMIN_PER_PAGE]
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /**
     * Save a reply, e-mail it to the customer and set the status
     * (in_progress or resolved). Returns an error message or null.
     */
    public static function reply(int $id, int $adminId, string $message, string $status): ?string
    {
        $inquiry = self::find($id);
        if ($inquiry === null) {
            return 'Inquiry not found.';
        }
        $message = CartService::cleanMessage($message);
        if (mb_strlen($message) < 2 || mb_strlen($message) > self::REPLY_MAX) {
            return 'Write a reply of up to ' . self::REPLY_MAX . ' characters.';
        }
        if (!in_array($status, ['in_progress', 'resolved'], true)) {
            $status = 'in_progress';
        }
        $now = date('Y-m-d H:i:s');
        Database::transaction(static function () use ($id, $adminId, $message, $status, $now): void {
            Database::execute(
                'INSERT INTO inquiry_replies (inquiry_id, admin_id, message, created_at) VALUES (:id, :admin, :message, :now)',
                ['id' => $id, 'admin' => $adminId, 'message' => $message, 'now' => $now]
            );
            Database::execute(
                'UPDATE customer_inquiries SET status = :status, last_reply_at = :now WHERE id = :id',
                ['status' => $status, 'now' => $now, 'id' => $id]
            );
        });
        EmailService::inquiryReply($inquiry, $message);
        ActivityLogService::log($adminId, 'inquiry.reply', 'inquiry', $id, 'Reply to ' . $inquiry['email'] . ' (' . self::STATUSES[$status] . ')');
        return null;
    }

    public static function setStatus(int $id, string $status, int $adminId): bool
    {
        $inquiry = self::find($id);
        if ($inquiry === null || !isset(self::STATUSES[$status])) {
            return false;
        }
        Database::execute('UPDATE customer_inquiries SET status = :s WHERE id = :id', ['s' => $status, 'id' => $id]);
        ActivityLogService::log($adminId, 'inquiry.status', 'inquiry', $id, $inquiry['status'] . ' -> ' . $status);
        return true;
    }

    /** "INQ-0042" style reference shown to customers and staff. */
    public static function reference(int $id): string
    {
        return 'INQ-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }
}
