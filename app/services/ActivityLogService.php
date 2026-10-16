<?php
/**
 * GiftAura - audit trail of administrator actions (admin_activity_log).
 * Never pass passwords, tokens or bank account numbers in $details.
 */
declare(strict_types=1);

final class ActivityLogService
{
    public static function log(int $adminId, string $action, string $entityType, ?int $entityId, string $details = ''): void
    {
        Database::execute(
            'INSERT INTO admin_activity_log (admin_id, action, entity_type, entity_id, details, ip_address)
             VALUES (:admin, :action, :type, :entity, :details, :ip)',
            [
                'admin' => $adminId, 'action' => mb_substr($action, 0, 50), 'type' => mb_substr($entityType, 0, 50),
                'entity' => $entityId, 'details' => $details !== '' ? mb_substr($details, 0, 500) : null,
                'ip' => PHP_SAPI === 'cli' ? 'cli' : client_ip(),
            ]
        );
    }

    /** Paginated log, newest first, optionally filtered by entity type. */
    public static function list(int $page = 1, string $entityType = '', int $perPage = 30): array
    {
        $where = '1 = 1';
        $params = [];
        if ($entityType !== '') {
            $where = 'l.entity_type = :type';
            $params['type'] = $entityType;
        }
        $total = (int) Database::fetchValue("SELECT COUNT(*) FROM admin_activity_log l WHERE {$where}", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $items = Database::fetchAll(
            "SELECT l.*, u.full_name AS admin_name
               FROM admin_activity_log l LEFT JOIN users u ON u.id = l.admin_id
              WHERE {$where}
              ORDER BY l.created_at DESC, l.id DESC
              LIMIT :limit OFFSET :offset",
            $params + ['limit' => $perPage, 'offset' => ($page - 1) * $perPage]
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** Entity types present in the log (for the filter). */
    public static function entityTypes(): array
    {
        return array_column(Database::fetchAll('SELECT DISTINCT entity_type FROM admin_activity_log ORDER BY entity_type'), 'entity_type');
    }
}
