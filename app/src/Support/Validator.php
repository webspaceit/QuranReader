<?php
declare(strict_types=1);

namespace Quran\Support;

/**
 * Small, explicit input validator.
 *
 * Only the fields you list are read; nothing else from the request is trusted.
 */
final class Validator
{
    /** @var array<string,mixed> */
    private array $data;

    /** @var array<string,string> */
    private array $errors = [];

    /** @param array<string,mixed> $data */
    private function __construct(array $data)
    {
        $this->data = $data;
    }

    /** @param array<string,mixed> $data */
    public static function make(array $data): self
    {
        return new self($data);
    }

    public function required(string $field, string $label): self
    {
        $value = $this->raw($field);
        if ($value === null || trim($value) === '') {
            $this->fail($field, $label . ' is required.');
        }
        return $this;
    }

    public function email(string $field, string $label): self
    {
        $value = $this->raw($field);
        if ($value === null || trim($value) === '') {
            return $this;
        }
        if (!filter_var(trim($value), FILTER_VALIDATE_EMAIL) || strlen(trim($value)) > 254) {
            $this->fail($field, $label . ' must be a valid email address.');
        }
        return $this;
    }

    public function min(string $field, int $length, string $label): self
    {
        $value = $this->raw($field);
        if ($value === null || $value === '') {
            return $this;
        }
        if (mb_strlen($value, 'UTF-8') < $length) {
            $this->fail($field, sprintf('%s must be at least %d characters.', $label, $length));
        }
        return $this;
    }

    public function max(string $field, int $length, string $label): self
    {
        $value = $this->raw($field);
        if ($value === null || $value === '') {
            return $this;
        }
        if (mb_strlen($value, 'UTF-8') > $length) {
            $this->fail($field, sprintf('%s must be %d characters or fewer.', $label, $length));
        }
        return $this;
    }

    /** @param array<int,string|int> $allowed */
    public function in(string $field, array $allowed, string $label): self
    {
        $value = $this->raw($field);
        if ($value === null || $value === '') {
            return $this;
        }
        $allowed = array_map('strval', $allowed);
        if (!in_array((string) $value, $allowed, true)) {
            $this->fail($field, $label . ' is not a supported value.');
        }
        return $this;
    }

    public function integer(string $field, int $min, int $max, string $label): self
    {
        $value = $this->raw($field);
        if ($value === null || $value === '') {
            return $this;
        }
        if (!preg_match('/^-?\d+$/', trim($value))) {
            $this->fail($field, $label . ' must be a whole number.');
            return $this;
        }
        $int = (int) $value;
        if ($int < $min || $int > $max) {
            $this->fail($field, sprintf('%s must be between %d and %d.', $label, $min, $max));
        }
        return $this;
    }

    public function confirm(string $field, string $confirmationField, string $label): self
    {
        $a = $this->raw($field);
        $b = $this->raw($confirmationField);
        if ($a !== null && $b !== null && $a !== $b) {
            $this->fail($confirmationField, $label . ' does not match.');
        }
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $message) {
            return $message;
        }
        return null;
    }

    /** Trimmed string value, or null when absent. */
    public function value(string $field): ?string
    {
        $value = $this->raw($field);
        if ($value === null) {
            return null;
        }
        return trim($value);
    }

    /** @param mixed $default */
    public function valueOr(string $field, $default)
    {
        $value = $this->value($field);
        return ($value === null || $value === '') ? $default : $value;
    }

    private function raw(string $field): ?string
    {
        $value = $this->data[$field] ?? null;
        if (is_array($value)) {
            return null;
        }
        return $value === null ? null : (string) $value;
    }

    private function fail(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }
}
