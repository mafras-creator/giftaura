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
                'INSERT INTO users (role, full_name, email, phone, password_hash, email_verified_at)
                 VALUES (:role, :name, :email, :phone, :hash, :verified)',
                [
                    'role'  => $role,
                    'name'  => $v->value('full_name'),
                    'email' => $v->value('email'),
                    'phone' => $v->value('phone') !== '' ? $v->value('phone') : null,
                    'hash'  => password_hash($v->value('password'), PASSWORD_DEFAULT),
                    // Administrators are created on the server by the owner, so their address is trusted.
                    'verified' => $role === 'admin' ? date('Y-m-d H:i:s') : null,
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

        Database::execute('UPDATE users SET password_hash = :h, must_change_password = 0 WHERE id = :id', [
            'h' => password_hash($v->value('new_password'), PASSWORD_DEFAULT), 'id' => $userId,
        ]);
        // New session id so any stolen copy of the old session stops working.
        Session::regenerate();
        Logger::info('Password changed', ['user_id' => $userId]);
        return [];
    }

    /** Use a verification link. Returns true when the address is now verified. */
    public static function verifyEmail(string $secret): bool
    {
        return (bool) Database::transaction(static function () use ($secret): bool {
            $userId = TokenService::consume($secret, TokenService::VERIFY);
            if ($userId === null) {
                return false;
            }
            Database::execute(
                'UPDATE users SET email_verified_at = :now WHERE id = :id AND email_verified_at IS NULL',
                ['now' => date('Y-m-d H:i:s'), 'id' => $userId]
            );
            Logger::info('E-mail verified', ['user_id' => $userId]);
            return true;
        });
    }

    /** Administrator marks a customer's address as verified (e.g. confirmed by phone). */
    public static function markEmailVerified(int $userId): bool
    {
        return Database::execute(
            "UPDATE users SET email_verified_at = :now WHERE id = :id AND role = 'customer' AND email_verified_at IS NULL",
            ['now' => date('Y-m-d H:i:s'), 'id' => $userId]
        ) === 1;
    }

    /**
     * Set a new password with a reset link.
     * Returns [] on success, ['token' => msg] if the link is no longer valid,
     * or field errors.
     */
    public static function resetPassword(string $secret, array $input): array
    {
        $v = new Validator($input, self::LABELS);
        $v->rules([
            'new_password'         => ['required', 'password'],
            'new_password_confirm' => ['required', 'same:new_password'],
        ]);
        if (!$v->passes()) {
            return $v->errors();
        }
        $hash = password_hash($v->value('new_password'), PASSWORD_DEFAULT);
        $userId = Database::transaction(static function () use ($secret, $hash): ?int {
            $userId = TokenService::consume($secret, TokenService::RESET);
            if ($userId === null) {
                return null;
            }
            // The link proves the customer can read the inbox, so the address is verified too.
            Database::execute(
                'UPDATE users SET password_hash = :h, email_verified_at = COALESCE(email_verified_at, :now) WHERE id = :id',
                ['h' => $hash, 'now' => date('Y-m-d H:i:s'), 'id' => $userId]
            );
            // Other reset links for this account stop working.
            Database::execute(
                "UPDATE user_tokens SET used_at = :now WHERE user_id = :id AND purpose = 'reset_password' AND used_at IS NULL",
                ['now' => date('Y-m-d H:i:s'), 'id' => $userId]
            );
            return $userId;
        });
        if ($userId === null) {
            return ['token' => 'This password-reset link is invalid, expired or has already been used. Please ask for a new one.'];
        }
        Logger::info('Password reset by e-mail link', ['user_id' => $userId]);
        return [];
    }
}
