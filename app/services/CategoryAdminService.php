<?php
/**
 * GiftAura - category management for administrators.
 * Rules:
 *  - one level of subcategories (a subcategory's parent must be top-level);
 *  - a category cannot be its own parent, and a category that has
 *    subcategories cannot itself become a subcategory;
 *  - slugs are unique and URL-safe (generated from the name if left empty);
 *  - deleting is allowed only when the category has no products and no
 *    subcategories; otherwise deactivate it.
 */
declare(strict_types=1);

final class CategoryAdminService
{
    /** All categories (active and inactive) as a flat, tree-ordered list. */
    public static function all(): array
    {
        $rows = Database::fetchAll(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count,
                    (SELECT COUNT(*) FROM categories s WHERE s.parent_id = c.id) AS child_count
               FROM categories c
              ORDER BY c.sort_order, c.name'
        );
        $ordered = [];
        foreach ($rows as $row) {
            if ($row['parent_id'] === null) {
                $ordered[] = $row;
                foreach ($rows as $child) {
                    if ($child['parent_id'] !== null && (int) $child['parent_id'] === (int) $row['id']) {
                        $ordered[] = $child;
                    }
                }
            }
        }
        return $ordered;
    }

    /** Top-level categories (possible parents), excluding $exceptId. */
    public static function parentOptions(?int $exceptId = null): array
    {
        return Database::fetchAll(
            'SELECT id, name, is_active FROM categories WHERE parent_id IS NULL AND id <> :except ORDER BY sort_order, name',
            ['except' => $exceptId ?? 0]
        );
    }

    /** Every category for product forms: "Parent › Child" labels. */
    public static function options(): array
    {
        $options = [];
        foreach (self::all() as $c) {
            $label = $c['name'];
            if ($c['parent_id'] !== null) {
                $parent = Database::fetchValue('SELECT name FROM categories WHERE id = :id', ['id' => (int) $c['parent_id']]);
                $label = $parent . ' › ' . $c['name'];
            }
            $options[] = ['id' => (int) $c['id'], 'label' => $label . ((int) $c['is_active'] === 1 ? '' : ' (inactive)')];
        }
        return $options;
    }

    public static function find(int $id): ?array
    {
        return Database::fetchOne('SELECT * FROM categories WHERE id = :id', ['id' => $id]);
    }

    /**
     * Create ($id = null) or update a category.
     * Returns ['id' => int] or ['errors' => [...], 'old' => [...]].
     */
    public static function save(?int $id, array $input, int $adminId): array
    {
        $v = new Validator($input, ['name' => 'Name', 'slug' => 'Web address (slug)', 'sort_order' => 'Display order']);
        $v->rules([
            'name'        => ['required', 'min:2', 'max:100'],
            'slug'        => ['max:120', 'slug'],
            'description' => ['max:500'],
            'sort_order'  => ['integer', 'min_value:-999', 'max_value:9999'],
        ]);
        $slug = $v->value('slug') !== '' ? $v->value('slug') : slugify($v->value('name'));
        if ($v->passes() && $slug === '') {
            $v->addError('slug', 'Enter a web address using English letters and numbers, e.g. flower-bouquets.');
        }

        $parentId = ctype_digit((string) ($input['parent_id'] ?? '')) && (int) $input['parent_id'] > 0 ? (int) $input['parent_id'] : null;
        if ($parentId !== null) {
            $parent = self::find($parentId);
            if ($parent === null || $parent['parent_id'] !== null) {
                $v->addError('parent_id', 'Choose a top-level category as the parent.');
            } elseif ($id !== null && $parentId === $id) {
                $v->addError('parent_id', 'A category cannot be its own parent.');
            } elseif ($id !== null && (int) Database::fetchValue('SELECT COUNT(*) FROM categories WHERE parent_id = :id', ['id' => $id]) > 0) {
                $v->addError('parent_id', 'This category has subcategories, so it must stay a top-level category.');
            }
        }

        if ($v->passes() && Database::fetchValue(
            'SELECT 1 FROM categories WHERE slug = :slug AND id <> :id', ['slug' => $slug, 'id' => $id ?? 0]
        ) !== null) {
            $v->addError('slug', 'Another category already uses this web address.');
        }

        $old = $v->old() + ['parent_id' => (string) ($parentId ?? ''), 'is_active' => ($input['is_active'] ?? '') === '1' ? '1' : '0'];
        if (!$v->passes()) {
            return ['errors' => $v->errors(), 'old' => $old];
        }

        $params = [
            'parent' => $parentId, 'name' => $v->value('name'), 'slug' => $slug,
            'description' => $v->value('description') !== '' ? $v->value('description') : null,
            'sort' => $v->value('sort_order') !== '' ? (int) $v->value('sort_order') : 0,
            'active' => ($input['is_active'] ?? '') === '1' ? 1 : 0,
        ];
        if ($id === null) {
            $id = Database::insert(
                'INSERT INTO categories (parent_id, name, slug, description, sort_order, is_active)
                 VALUES (:parent, :name, :slug, :description, :sort, :active)',
                $params
            );
            ActivityLogService::log($adminId, 'category.create', 'category', $id, $params['name']);
        } else {
            Database::execute(
                'UPDATE categories SET parent_id = :parent, name = :name, slug = :slug, description = :description,
                        sort_order = :sort, is_active = :active WHERE id = :id',
                $params + ['id' => $id]
            );
            ActivityLogService::log($adminId, 'category.update', 'category', $id, $params['name'] . ($params['active'] ? '' : ' (inactive)'));
        }
        return ['id' => $id];
    }

    public static function setActive(int $id, bool $active, int $adminId): bool
    {
        $done = Database::execute('UPDATE categories SET is_active = :a WHERE id = :id', ['a' => $active ? 1 : 0, 'id' => $id]) > 0;
        if ($done) {
            ActivityLogService::log($adminId, $active ? 'category.activate' : 'category.deactivate', 'category', $id);
        }
        return $done;
    }

    /** Delete an empty category. Returns an error message or null. */
    public static function delete(int $id, int $adminId): ?string
    {
        $category = self::find($id);
        if ($category === null) {
            return 'Category not found.';
        }
        $products = (int) Database::fetchValue('SELECT COUNT(*) FROM products WHERE category_id = :id', ['id' => $id]);
        $children = (int) Database::fetchValue('SELECT COUNT(*) FROM categories WHERE parent_id = :id', ['id' => $id]);
        if ($products > 0 || $children > 0) {
            return 'This category still contains ' . $products . ' product(s) and ' . $children
                . ' subcategory(ies). Move them first, or deactivate the category instead.';
        }
        Database::execute('DELETE FROM categories WHERE id = :id', ['id' => $id]);
        ActivityLogService::log($adminId, 'category.delete', 'category', $id, $category['name']);
        return null;
    }
}
