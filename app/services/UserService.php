<?php
/**
 * GiftAura - user accounts: registration, profile, password, admin creation.
 * All methods validate on the server and return an array of errors
 * (field => message). An empty array means success.
 */
declare(strict_types=1);

final class UserService
{
    public const LABELS = [
        'full_name'        => 'Full name',
        'email'            => 'Email',
        'phone'            => 'Phone number',
        'password'         => 'Password',
        'password_confirm' => 'Password confirmation',
        'current_password' => 'Current password',
        'new_password'     => 'New password',
        'new_password_confirm' => 'New password confirmation',
    ];

    /**
     * Register a customer. On success returns ['id' => new user id].
     * On failure returns ['errors' => [...], 'old' => [...]].
     */
    public static function registerCustomer(array $input): array
    {
        return self::create($input, 'customer');
    }

    /** Create an administrator (used only by scripts/create_admin.php). */
    public static function createAdmin(array $input): array
    {
        return self::create($input, 'admin');
    }

    private static function create(array $input, string $role): array
    {
        $v = new Validator($input, self::LABELS);
        $v->rules([
            'full_name'        => ['required', 'min:2', 'max:100'],
            'email'            => ['required', 'email', 'max:190'],
            'phone'            => $role === 'customer' ? ['required', 'phone'] : ['phone'],
            'password'         => ['required', 'password'],
            'password_confirm' => ['required', 'same:password'],
        ]);

        if ($v->passes() && self::emailExists($v->value('email'))) {
            $v->addError('email', 'An account with this email already exists. Try logging in instead.');
        }
        if (!$v->passes()) {
            return ['errors' => $v->errors(), 'old' => $v->old()];
        }

        try {
            $id = Database::insert(
                'INSERT INTO users (role, full_name, email, phone, password_hash)
                 VALUES (:role, :name, :email, :phone, :hash)',
                [
                    'role'  => $role,
                    'name'  => $v->value('full_name'),
                    'email' => $v->value('email'),
                    'phone' => $v->value('phone') !== '' ? $v->value('phone') : null,
                    'hash'  => password_hash($v->value('password'), PASSWORD_DEFAULT),
                ]
            );
        } catch (PDOException $e) {
            // 23000 = duplicate key: two registrations with the same email at the same moment.
            if ($e->getCode() === '23000') {
                return ['errors' => ['email' => 'An account with this email already exists.'], 'old' => $v->old()];
            }
            throw $e;
        }

        Logger::info('Account created', ['user_id' => $id, 'role' => $role]);
        return ['id' => $id];
    }

    public static function emailExists(string $email): bool
    {
        return Database::fetchValue('SELECT 1 FROM users WHERE email = :email', ['email' => mb_strtolower($email)]) !== null;
    }

    /** Update name and phone. Email is the login name and cannot be changed here. */
    public static function updateProfile(int $userId, array $input): array
    {
        $v = new Validator($input, self::LABELS);
        $v->rules([
            'full_name' => ['required', 'min:2', 'max:100'],
            'phone'     => ['required', 'phone'],
        ]);
        if (!$v->passes()) {
            return $v->errors();
        }
        Database::execute(
            'UPDATE users SET full_name = :name, phone = :phone WHERE id = :id',
            ['name' => $v->value('full_name'), 'phone' => $v->value('phone'), 'id' => $userId]
        );
        return [];
    }

    /** Change password after checking the current one. */
    public static function changePassword(int $userId, array $input): array
    {
        $v = new Validator($input, self::LABELS);
        $v->rules([
            'current_password'     => ['required'],
            'new_password'         => ['required', 'password'],
            'new_password_confirm' => ['required', 'same:new_password'],
        ]);
        if (!$v->passes()) {
            return $v->errors();
        }

        $hash = Database::fetchValue('SELECT password_hash FROM users WHERE id = :id', ['id' => $userId]);
        if (!is_string($hash) || !password_verify($v->value('current_password'), $hash)) {
            return ['current_password' => 'Your current password is not correct.'];
        }
        if (password_verify($v->value('new_password'), $hash)) {
            return ['new_password' => 'Choose a password different from your current one.'];
        }

        Database::execute('UPDATE users SET password_hash = :h WHERE id = :id', [
            'h' => password_hash($v->value('new_password'), PASSWORD_DEFAULT), 'id' => $userId,
        ]);
        // New session id so any stolen copy of the old session stops working.
        Session::regenerate();
        Logger::info('Password changed', ['user_id' => $userId]);
        return [];
    }
}
