<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    public function requiredString(array $data, string $field, int $minLen = 1, int $maxLen = 255): string
    {
        $raw = $data[$field] ?? null;

        if (!is_string($raw) || trim($raw) === '') {
            $this->errors[$field] = "El campo '{$field}' es obligatorio.";
            return '';
        }

        $value = trim($raw);
        $length = mb_strlen($value);

        if ($length < $minLen || $length > $maxLen) {
            $this->errors[$field] = "El campo '{$field}' debe tener entre {$minLen} y {$maxLen} caracteres.";
        }

        return $value;
    }

    public function optionalString(array $data, string $field, int $maxLen = 255): ?string
    {
        if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
            return null;
        }

        if (!is_string($data[$field])) {
            $this->errors[$field] = "El campo '{$field}' debe ser una cadena de texto.";
            return null;
        }

        $value = trim($data[$field]);
        if (mb_strlen($value) > $maxLen) {
            $this->errors[$field] = "El campo '{$field}' no puede superar los {$maxLen} caracteres.";
        }

        return $value;
    }

    public function email(array $data, string $field = 'email'): string
    {
        $value = strtolower($this->requiredString($data, $field, 5, 150));

        if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->errors[$field] = 'El formato del correo electrónico no es válido.';
        }

        return $value;
    }

    public function password(array $data, string $field = 'password', int $minLen = 8, int $maxLen = 72): string
    {
        $raw = $data[$field] ?? null;

        if (!is_string($raw) || $raw === '') {
            $this->errors[$field] = "El campo '{$field}' es obligatorio.";
            return '';
        }

        $length = mb_strlen($raw);
        if ($length < $minLen || $length > $maxLen) {
            $this->errors[$field] = "La contraseña debe tener entre {$minLen} y {$maxLen} caracteres.";
        }

        return $raw;
    }

    public function positiveInt(array $data, string $field, int $max = 100000): int
    {
        $raw = $data[$field] ?? null;

        if (filter_var($raw, FILTER_VALIDATE_INT) === false) {
            $this->errors[$field] = "El campo '{$field}' debe ser un número entero.";
            return 0;
        }

        $value = (int) $raw;
        if ($value <= 0 || $value > $max) {
            $this->errors[$field] = "El campo '{$field}' debe estar entre 1 y {$max}.";
        }

        return $value;
    }

    public function nonNegativeInt(array $data, string $field, int $max = 100000): int
    {
        $raw = $data[$field] ?? null;

        if (filter_var($raw, FILTER_VALIDATE_INT) === false) {
            $this->errors[$field] = "El campo '{$field}' debe ser un número entero.";
            return 0;
        }

        $value = (int) $raw;
        if ($value < 0 || $value > $max) {
            $this->errors[$field] = "El campo '{$field}' debe estar entre 0 y {$max}.";
        }

        return $value;
    }

    public function decimal(array $data, string $field, float $min = 0.0, float $max = 99999.99): float
    {
        $raw = $data[$field] ?? null;

        if (!is_numeric($raw)) {
            $this->errors[$field] = "El campo '{$field}' debe ser un valor numérico.";
            return 0.0;
        }

        $value = (float) $raw;
        if ($value < $min || $value > $max) {
            $this->errors[$field] = "El campo '{$field}' debe estar entre {$min} y {$max}.";
        }

        return round($value, 2);
    }

    public function inList(array $data, string $field, array $allowed): string
    {
        $value = $this->requiredString($data, $field, 1, 50);

        if ($value !== '' && !in_array($value, $allowed, true)) {
            $this->errors[$field] = "El campo '{$field}' debe ser uno de: " . implode(', ', $allowed) . '.';
        }

        return $value;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] = $message;
    }

    public function failIfErrors(): void
    {
        if ($this->errors !== []) {
            throw new HttpException(
                400,
                'Los datos enviados contienen errores de validación.',
                'VALIDATION_ERROR',
                $this->errors
            );
        }
    }
}