<?php
/**
 * GiftAura - product categories.
 * Phase 3: read-only list used by the homepage.
 * Phase 4 adds the category tree, product counts and links;
 * Phase 6 adds create/edit/deactivate for administrators.
 */
declare(strict_types=1);

final class CategoryService
{
    /** Active top-level categories in display order. */
    public static function topLevel(): array
    {
        return Database::fetchAll(
            'SELECT id, name, slug, description
               FROM categories
              WHERE parent_id IS NULL AND is_active = 1
              ORDER BY sort_order, name'
        );
    }
}
