<?php
/**
 * GiftAura - customer address book and delivery-address validation.
 * Every query includes the owner's user_id, so a customer can never read
 * or change another customer's address by changing an id in the URL.
 */
declare(strict_types=1);

final class AddressService
{
    /** The 25 administrative districts of Sri Lanka. */
    public const DISTRICTS = [
        'Ampara', 'Anuradhapura', 'Badulla', 'Batticaloa', 'Colombo', 'Galle', 'Gampaha',
        'Hambantota', 'Jaffna', 'Kalutara', 'Kandy', 'Kegalle', 'Kilinochchi', 'Kurunegala',
        'Mannar', 'Matale', 'Matara', 'Monaragala', 'Mullaitivu', 'Nuwara Eliya', 'Polonnaruwa',
        'Puttalam', 'Ratnapura', 'Trincomalee', 'Vavuniya',
    ];

    public const LABELS = [
        'label'          => 'Address name',
        'recipient_name' => 'Recipient name',
        'phone'          => 'Recipient phone',
        'address_line1'  => 'Address line 1',
        'address_line2'  => 'Address line 2',
        'city'           => 'City / town',
        'district'       => 'District',
        'postal_code'    => 'Postal code',
    ];

    /**
     * Validate a delivery address. Returns [errors, cleanValues].
     * Field names: recipient_name, phone, address_line1, address_line2,
     * city, district, postal_code, and optionally label.
     */
    public static function validate(array $input, bool $withLabel = false): array
    {
        $v = new Validator($input, self::LABELS);
        $rules = [
            'recipient_name' => ['required', 'min:2', 'max:100'],
            'phone'          => ['required', 'phone'],
            'address_line1'  => ['required', 'min:3', 'max:150'],
            'address_line2'  => ['max:150'],
            'city'           => ['required', 'min:2', 'max:80'],
            'district'       => ['required', 'in:' . implode(',', self::DISTRICTS)],
            'postal_code'    => ['max:10'],
        ];
        if ($withLabel) {
            $rules['label'] = ['max:50'];
        }
        $v->rules($rules);
        if ($v->value('postal_code') !== '' && !preg_match('/^\d{5}$/', $v->value('postal_code'))) {
            $v->addError('postal_code', 'Postal code must be 5 digits, e.g. 10100.');
        }
        $clean = [];
        foreach (array_keys($rules) as $field) {
            $clean[$field] = $v->value($field);
        }
        if ($withLabel && $clean['label'] === '') {
            $clean['label'] = 'Home';
        }
        return [$v->errors(), $clean];
    }

    /** Saved addresses of a customer, default first. */
    public static function forUser(int $userId): array
    {
        return Database::fetchAll(
            'SELECT * FROM customer_addresses WHERE user_id = :uid ORDER BY is_default DESC, id ASC',
            ['uid' => $userId]
        );
    }

    /** One address, only if it belongs to the customer. */
    public static function find(int $userId, int $addressId): ?array
    {
        return Database::fetchOne(
            'SELECT * FROM customer_addresses WHERE id = :id AND user_id = :uid',
            ['id' => $addressId, 'uid' => $userId]
        );
    }

    public static function defaultFor(int $userId): ?array
    {
        return Database::fetchOne(
            'SELECT * FROM customer_addresses WHERE user_id = :uid ORDER BY is_default DESC, id ASC LIMIT 1',
            ['uid' => $userId]
        );
    }

    /** Create an address. Returns ['id' => int] or ['errors' => [...]]. */
    public static function create(int $userId, array $input, bool $makeDefault = false): array
    {
        [$errors, $a] = self::validate($input, true);
        if ($errors !== []) {
            return ['errors' => $errors];
        }
        if (count(self::forUser($userId)) >= 10) {
            return ['errors' => ['label' => 'You can save up to 10 addresses. Please delete one first.']];
        }
        $id = Database::insert(
            'INSERT INTO customer_addresses
                (user_id, label, recipient_name, phone, address_line1, address_line2, city, district, postal_code)
             VALUES (:uid, :label, :name, :phone, :l1, :l2, :city, :district, :postal)',
            [
                'uid' => $userId, 'label' => $a['label'], 'name' => $a['recipient_name'], 'phone' => $a['phone'],
                'l1' => $a['address_line1'], 'l2' => $a['address_line2'] !== '' ? $a['address_line2'] : null,
                'city' => $a['city'], 'district' => $a['district'],
                'postal' => $a['postal_code'] !== '' ? $a['postal_code'] : null,
            ]
        );
        // The first address automatically becomes the default.
        if ($makeDefault || self::defaultId($userId) === null) {
            self::setDefault($userId, $id);
        }
        return ['id' => $id];
    }

    /** Update an address the customer owns. Returns errors (empty = success). */
    public static function update(int $userId, int $addressId, array $input): array
    {
        if (self::find($userId, $addressId) === null) {
            return ['label' => 'Address not found.'];
        }
        [$errors, $a] = self::validate($input, true);
        if ($errors !== []) {
            return $errors;
        }
        Database::execute(
            'UPDATE customer_addresses
                SET label = :label, recipient_name = :name, phone = :phone, address_line1 = :l1, address_line2 = :l2,
                    city = :city, district = :district, postal_code = :postal
              WHERE id = :id AND user_id = :uid',
            [
                'label' => $a['label'], 'name' => $a['recipient_name'], 'phone' => $a['phone'],
                'l1' => $a['address_line1'], 'l2' => $a['address_line2'] !== '' ? $a['address_line2'] : null,
                'city' => $a['city'], 'district' => $a['district'],
                'postal' => $a['postal_code'] !== '' ? $a['postal_code'] : null,
                'id' => $addressId, 'uid' => $userId,
            ]
        );
        return [];
    }

    public static function delete(int $userId, int $addressId): bool
    {
        $wasDefault = (int) (self::find($userId, $addressId)['is_default'] ?? 0) === 1;
        $deleted = Database::execute(
            'DELETE FROM customer_addresses WHERE id = :id AND user_id = :uid',
            ['id' => $addressId, 'uid' => $userId]
        ) > 0;
        if ($deleted && $wasDefault) {
            $next = Database::fetchValue(
                'SELECT id FROM customer_addresses WHERE user_id = :uid ORDER BY id ASC LIMIT 1',
                ['uid' => $userId]
            );
            if ($next !== null) {
                self::setDefault($userId, (int) $next);
            }
        }
        return $deleted;
    }

    /** Make one address the default (exactly one default per customer). */
    public static function setDefault(int $userId, int $addressId): bool
    {
        if (self::find($userId, $addressId) === null) {
            return false;
        }
        Database::transaction(static function () use ($userId, $addressId): void {
            Database::execute('UPDATE customer_addresses SET is_default = 0 WHERE user_id = :uid', ['uid' => $userId]);
            Database::execute(
                'UPDATE customer_addresses SET is_default = 1 WHERE id = :id AND user_id = :uid',
                ['id' => $addressId, 'uid' => $userId]
            );
        });
        return true;
    }

    private static function defaultId(int $userId): ?int
    {
        $id = Database::fetchValue(
            'SELECT id FROM customer_addresses WHERE user_id = :uid AND is_default = 1',
            ['uid' => $userId]
        );
        return $id === null ? null : (int) $id;
    }
}
