<?php
namespace Tos\Module\TawasulCore\Support;

use Tos\Module\TawasulCore\Http\ApiException;

/**
 * Field-level validation against the resource definition.
 *
 * TawasulOS's schema has no foreign keys and very few checks, so the database
 * accepts "banana" in a date column and silently truncates a name that is too
 * long. Catching that here means an integrator gets a precise, per-field
 * message with a 422 instead of a corrupted row or an opaque 500.
 *
 * Errors are collected, not thrown one at a time, so a client sees everything
 * wrong with a payload in a single response:
 *
 *   { "errors": [ { "field": "dateStart", "message": "..." } ] }
 */
class Validator
{
    /**
     * @param array $resource the resource definition
     * @param array $data     writable fields only, already whitelisted
     * @return array the coerced values
     */
    public static function validate(array $resource, array $data, bool $requireAll): array
    {
        $types = $resource['types'] ?? [];
        $enums = $resource['enums'] ?? [];
        $errors = [];

        if ($requireAll) {
            foreach ($resource['required'] as $field) {
                if (!array_key_exists($field, $data) || $data[$field] === '' || $data[$field] === null) {
                    $errors[] = ['field' => $field, 'code' => 'required', 'message' => $field.' is required.'];
                }
            }
        }

        $clean = [];
        foreach ($data as $field => $value) {
            $type = $types[$field] ?? 'string';

            if ($value === null || $value === '') {
                $clean[$field] = in_array($field, $resource['required'], true) && $value === '' ? '' : $value;
                continue;
            }

            if (!is_scalar($value)) {
                $clean[$field] = json_encode($value);
                continue;
            }

            $result = self::coerce($field, (string) $value, $type, $enums[$field] ?? null);
            if (isset($result['error'])) {
                $errors[] = $result['error'];
                continue;
            }

            $clean[$field] = $result['value'];
        }

        if (!empty($errors)) {
            throw ApiException::unprocessable(
                count($errors) === 1
                    ? $errors[0]['message']
                    : 'There are '.count($errors).' problems with this request.',
                ['errors' => $errors, 'writableFields' => $resource['writable']]
            );
        }

        return $clean;
    }

    protected static function coerce(string $field, string $value, string $type, ?array $enum): array
    {
        $fail = function (string $message, string $code = 'invalid') use ($field) {
            return ['error' => ['field' => $field, 'code' => $code, 'message' => $message]];
        };

        if (!empty($enum) && !in_array($value, $enum, true)) {
            return $fail($field.' must be one of: '.implode(', ', $enum).'.', 'not_in_enum');
        }

        switch ($type) {
            case 'integer':
                if (!preg_match('/^-?\d+$/', trim($value))) {
                    return $fail($field.' must be a whole number.', 'not_an_integer');
                }
                return ['value' => (int) $value];

            case 'number':
                if (!is_numeric($value)) {
                    return $fail($field.' must be a number.', 'not_a_number');
                }
                return ['value' => $value + 0];

            case 'boolean':
                $truthy = ['Y', 'y', '1', 'true', 'TRUE', 'on'];
                $falsy = ['N', 'n', '0', 'false', 'FALSE', 'off', ''];
                if (in_array($value, $truthy, true)) {
                    return ['value' => 'Y'];
                }
                if (in_array($value, $falsy, true)) {
                    return ['value' => 'N'];
                }
                return $fail($field.' must be Y or N.', 'not_a_boolean');

            case 'date':
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || !self::isRealDate($value)) {
                    return $fail($field.' must be a date in YYYY-MM-DD format.', 'not_a_date');
                }
                return ['value' => $value];

            case 'time':
                if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) {
                    return $fail($field.' must be a time in HH:MM or HH:MM:SS format.', 'not_a_time');
                }
                return ['value' => strlen($value) === 5 ? $value.':00' : $value];

            case 'datetime':
                $normalised = str_replace('T', ' ', preg_replace('/(Z|[+-]\d{2}:?\d{2})$/', '', trim($value)));
                if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $normalised)) {
                    return $fail($field.' must be a date and time in YYYY-MM-DD HH:MM:SS format.', 'not_a_datetime');
                }
                return ['value' => strlen($normalised) === 16 ? $normalised.':00' : $normalised];

            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return $fail($field.' must be a valid email address.', 'not_an_email');
                }
                return ['value' => $value];

            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    return $fail($field.' must be a valid URL.', 'not_a_url');
                }
                return ['value' => $value];
        }

        return ['value' => $value];
    }

    protected static function isRealDate(string $value): bool
    {
        [$y, $m, $d] = array_map('intval', explode('-', $value));

        return checkdate($m, $d, $y);
    }

    /**
     * Applies declared maximum lengths, where the definition knows them.
     */
    public static function checkLengths(array $resource, array $data): void
    {
        $lengths = $resource['maxLength'] ?? [];
        $errors = [];

        foreach ($data as $field => $value) {
            if (isset($lengths[$field]) && is_string($value) && mb_strlen($value) > $lengths[$field]) {
                $errors[] = [
                    'field' => $field,
                    'code' => 'too_long',
                    'message' => $field.' may be at most '.$lengths[$field].' characters.',
                ];
            }
        }

        if (!empty($errors)) {
            throw ApiException::unprocessable('One or more fields are too long.', ['errors' => $errors]);
        }
    }
}
