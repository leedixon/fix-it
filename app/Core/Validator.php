<?php
declare(strict_types=1);

namespace FixListed\Core;

/**
 * Form validation that keeps the submitted values.
 *
 * Deliberately small. The thing it exists to guarantee is that a rejected form
 * comes back with everything the person typed still in it — a validation error
 * that clears a ten-field form is how you lose an application you had already
 * won.
 */
final class Validator
{
    /** @var array<string,string> field => first error */
    private array $errors = [];

    /** @param array<string,mixed> $input */
    public function __construct(private readonly array $input)
    {
    }

    public function value(string $field, string $default = ''): string
    {
        $v = $this->input[$field] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    /** @return array<int,string> */
    public function values(string $field): array
    {
        $v = $this->input[$field] ?? [];
        if (!is_array($v)) {
            return $v === '' ? [] : [(string) $v];
        }
        return array_values(array_filter(array_map(
            static fn ($x): string => is_scalar($x) ? trim((string) $x) : '',
            $v,
        ), static fn (string $s): bool => $s !== ''));
    }

    public function int(string $field): ?int
    {
        $v = preg_replace('/[^0-9]/', '', $this->value($field)) ?? '';
        return $v === '' ? null : (int) $v;
    }

    /** Dollars typed by a person to integer cents. '75', '75.50', '$75' all work. */
    public function money(string $field): ?int
    {
        $raw = preg_replace('/[^0-9.]/', '', $this->value($field)) ?? '';
        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }
        return (int) round(((float) $raw) * 100);
    }

    public function required(string $field, string $label): self
    {
        if ($this->value($field) === '') {
            $this->fail($field, $label . ' is required.');
        }
        return $this;
    }

    public function requiredAny(string $field, string $label): self
    {
        if ($this->values($field) === []) {
            $this->fail($field, $label . ' is required.');
        }
        return $this;
    }

    public function email(string $field, string $label = 'Email'): self
    {
        $v = $this->value($field);
        if ($v !== '' && filter_var($v, FILTER_VALIDATE_EMAIL) === false) {
            $this->fail($field, $label . ' does not look like an email address.');
        }
        return $this;
    }

    public function max(string $field, int $length, string $label): self
    {
        if (mb_strlen($this->value($field)) > $length) {
            $this->fail($field, $label . ' must be ' . $length . ' characters or fewer.');
        }
        return $this;
    }

    public function min(string $field, int $length, string $label): self
    {
        $v = $this->value($field);
        if ($v !== '' && mb_strlen($v) < $length) {
            $this->fail($field, $label . ' needs to be at least ' . $length . ' characters.');
        }
        return $this;
    }

    public function in(string $field, array $allowed, string $label): self
    {
        $v = $this->value($field);
        if ($v !== '' && !in_array($v, $allowed, true)) {
            $this->fail($field, $label . ' is not one of the options.');
        }
        return $this;
    }

    public function fail(string $field, string $message): self
    {
        // First error per field only. A field with three complaints stacked
        // under it reads as the form shouting.
        $this->errors[$field] ??= $message;
        return $this;
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function error(string $field): string
    {
        return $this->errors[$field] ?? '';
    }
}
