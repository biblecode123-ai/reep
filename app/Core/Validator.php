<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Input validation helper (spec §87). Returns errors keyed by field.
 * Usage: [$ok, $errors, $clean] = Validator::check($_POST, [
 *     'title' => 'required|max:250', 'budget' => 'numeric', 'email' => 'email',
 * ]);
 */
final class Validator
{
    /** Validate an array of rules; returns [ok(bool), errors(array), data(array)]. */
    public static function check(array $input, array $rules): array
    {
        $errors = [];
        $clean  = [];
        foreach ($rules as $field => $ruleString) {
            $label   = ucfirst(str_replace('_', ' ', $field));
            $value   = $input[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }
            $rulesArr = is_array($ruleString) ? $ruleString : explode('|', $ruleString);

            foreach ($rulesArr as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                switch ($name) {
                    case 'required':
                        if ($value === null || $value === '' || (is_array($value) && count($value) === 0)) {
                            $errors[$field] = $label . ' is required.';
                            continue 3;
                        }
                        break;

                    case 'max':
                        if (is_string($value) && mb_strlen($value) > (int) $param) {
                            $errors[$field] = $label . ' may not exceed ' . $param . ' characters.';
                            continue 3;
                        }
                        break;

                    case 'min':
                        if (is_string($value) && mb_strlen($value) < (int) $param) {
                            $errors[$field] = $label . ' must be at least ' . $param . ' characters.';
                            continue 3;
                        }
                        break;

                    case 'numeric':
                        if ($value !== null && $value !== '' && !is_numeric($value)) {
                            $errors[$field] = $label . ' must be a number.';
                            continue 3;
                        }
                        break;

                    case 'integer':
                        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                            $errors[$field] = $label . ' must be a whole number.';
                            continue 3;
                        }
                        break;

                    case 'email':
                        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = $label . ' must be a valid email address.';
                            continue 3;
                        }
                        break;

                    case 'date':
                        if ($value !== null && $value !== '') {
                            $d = \DateTime::createFromFormat('Y-m-d', (string) $value);
                            if (!$d || $d->format('Y-m-d') !== $value) {
                                $errors[$field] = $label . ' must be a valid date (YYYY-MM-DD).';
                                continue 3;
                            }
                        }
                        break;

                    case 'in':
                        $allowed = explode(',', (string) $param);
                        if ($value !== null && $value !== '' && !in_array((string) $value, $allowed, true)) {
                            $errors[$field] = $label . ' has an invalid value.';
                            continue 3;
                        }
                        break;

                    case 'phone':
                        if ($value !== null && $value !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', (string) $value)) {
                            $errors[$field] = $label . ' is not a valid phone number.';
                            continue 3;
                        }
                        break;
                }
            }
            $clean[$field] = $value;
        }
        return [$errors === [], $errors, $clean];
    }
}

