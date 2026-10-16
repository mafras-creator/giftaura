<?php
/**
 * GiftAura - server-side input validation.
 *
 * Example:
 *   $v = new Validator($_POST, ['full_name' => 'Full name']);
 *   $v->rules([
 *       'full_name' => ['required', 'min:2', 'max:100'],
 *       'email'     => ['required', 'email', 'max:190'],
 *       'phone'     => ['phone'],                 // optional; validated if filled
 *   ]);
 *   if ($v->passes()) { $email = $v->value('email'); }
 *
 * Values are trimmed (except fields whose name contains "password").
 * Emails are lower-cased; Sri Lankan phone numbers are normalised to
 * the form 0771234567. Only the first error per field is kept.
 */
declare(strict_types=1);

final class Validator
{
    private array $values = [];
    private array $errors = [];

    public function __construct(private array $input, private array $labels = [])
    {
    }

    /** Apply rules. Can be called more than once. Returns $this. */
    public function rules(array $rules): self
    {
        foreach ($rules as $field => $fieldRules) {
            $raw = $this->input[$field] ?? '';
            $value = is_string($raw) ? $raw : (is_int($raw) || is_float($raw) ? (string) $raw : '');
            if (!str_contains($field, 'password')) {
                $value = trim($value);
            }
            $this->values[$field] = $value;

            $isRequired = in_array('required', $fieldRules, true);
            if ($value === '') {
                if ($isRequired) {
                    $this->addError($field, $this->label($field) . ' is required.');
                }
                continue; // optional and empty: nothing else to check
            }

            foreach ($fieldRules as $rule) {
                if ($rule === 'required') {
                    continue;
                }
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $error = $this->check($field, $name, $arg);
                if ($error !== null) {
                    $this->addError($field, $error);
                    break;
                }
            }
        }
        return $this;
    }

    private function check(string $field, string $rule, ?string $arg): ?string
    {
        $value = $this->values[$field];
        $label = $this->label($field);

        switch ($rule) {
            case 'email':
                $value = mb_strtolower($value);
                $this->values[$field] = $value;
                return filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid email address.';

            case 'min':
                return mb_strlen($value) >= (int) $arg ? null : "{$label} must be at least {$arg} characters.";

            case 'max':
                return mb_strlen($value) <= (int) $arg ? null : "{$label} must be {$arg} characters or fewer.";

            case 'phone':
                $normalised = self::normalisePhone($value);
                if ($normalised === null) {
                    return 'Enter a valid Sri Lankan phone number, e.g. 0771234567.';
                }
                $this->values[$field] = $normalised;
                return null;

            case 'password':
                if (strlen($value) < 8) {
                    return 'Password must be at least 8 characters.';
                }
                if (strlen($value) > 72) {
                    return 'Password must be 72 characters or fewer.';
                }
                if (!preg_match('/[A-Za-z]/', $value) || !preg_match('/\d/', $value)) {
                    return 'Password must contain at least one letter and one number.';
                }
                return null;

            case 'same':
                return hash_equals((string) ($this->input[$arg] ?? ''), $value) ? null : "{$label} does not match.";

            case 'in':
                return in_array($value, explode(',', (string) $arg), true) ? null : "Choose a valid {$label}.";

            case 'integer':
                return preg_match('/^-?\d+$/', $value) ? null : "{$label} must be a whole number.";

            case 'decimal':
                return preg_match('/^\d+(\.\d{1,2})?$/', $value) ? null : "{$label} must be an amount like 1500 or 1500.50.";

            case 'min_value':
                return is_numeric($value) && (float) $value >= (float) $arg ? null : "{$label} must be at least {$arg}.";

            case 'max_value':
                return is_numeric($value) && (float) $value <= (float) $arg ? null : "{$label} must be {$arg} or less.";

            case 'slug':
                return preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $value)
                    ? null : "{$label} may contain only lowercase letters, numbers and single hyphens.";

            default:
                throw new InvalidArgumentException("Unknown validation rule: {$rule}");
        }
    }

    /**
     * Normalise a Sri Lankan phone number to 10 digits starting with 0.
     * Accepts 0771234567, 077 123 4567, +94771234567, 94771234567.
     */
    public static function normalisePhone(string $phone): ?string
    {
        $digits = preg_replace('/[\s\-()]/', '', $phone) ?? '';
        if (preg_match('/^\+?94(\d{9})$/', $digits, $m)) {
            $digits = '0' . $m[1];
        }
        return preg_match('/^0\d{9}$/', $digits) ? $digits : null;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /** Cleaned value of a field (after trimming/normalising). */
    public function value(string $field): string
    {
        return $this->values[$field] ?? '';
    }

    /** All cleaned values except passwords, for re-filling a form. */
    public function old(): array
    {
        return array_filter(
            $this->values,
            static fn(string $key): bool => !str_contains($key, 'password'),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function label(string $field): string
    {
        return $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }
}
