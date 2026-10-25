<?php

declare(strict_types=1);

namespace Nova\Validation;

use InvalidArgumentException;

final class Validator
{
    public function validate(array $data, array $rules): array
    {
        $errors = [];
        $validated = [];
        foreach ($rules as $field => $definition) {
            $fieldRules = is_string($definition) ? explode('|', $definition) : $definition;
            foreach ($fieldRules as $rule) {
                if (!is_string($rule) || !preg_match('/^(required|nullable|string|email|integer|boolean|array|min:\d+|max:\d+)$/D', $rule)) {
                    throw new InvalidArgumentException('Unsupported validation rule.');
                }
            }
            $exists = array_key_exists($field, $data);
            $value = $data[$field] ?? null;
            $empty = $value === null || (is_string($value) && trim($value) === '') || $value === [];
            if (in_array('required', $fieldRules, true) && (!$exists || $empty)) {
                $errors[$field][] = "$field is required.";
                continue;
            }
            if (!$exists) { continue; }
            if ($value === null && in_array('nullable', $fieldRules, true)) { $validated[$field] = null; continue; }
            foreach ($fieldRules as $rule) {
                [$name, $limit] = array_pad(explode(':', $rule, 2), 2, null);
                $valid = match ($name) {
                    'required', 'nullable' => true,
                    'string' => is_string($value),
                    'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                    'integer' => (is_int($value) || is_string($value)) && filter_var($value, FILTER_VALIDATE_INT) !== false,
                    'boolean' => in_array($value, [true, false, 0, 1, '0', '1'], true),
                    'array' => is_array($value),
                    'min', 'max' => $this->sizeValid($value, (int) $limit, $name, in_array('integer', $fieldRules, true)),
                };
                if (!$valid) { $errors[$field][] = "$field failed $rule validation."; }
            }
            $validated[$field] = $value;
        }
        if ($errors !== []) { throw new ValidationException($errors); }
        return $validated;
    }

    private function sizeValid(mixed $value, int $limit, string $rule, bool $numeric): bool
    {
        if ($numeric) {
            if (!(is_int($value) || is_string($value)) || filter_var($value, FILTER_VALIDATE_INT) === false) { return false; }
            $size = (int) $value;
        } elseif (is_string($value)) {
            $size = strlen($value);
        } elseif (is_array($value)) {
            $size = count($value);
        } else { return false; }
        return $rule === 'min' ? $size >= $limit : $size <= $limit;
    }
}
