<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Support;

/**
 * Whether a string reference is shaped like a bigint primary key — digits only, short
 * enough for a signed 64-bit integer — so a resolver may try it as an id.
 *
 * @internal
 */
final class KeyString
{
    public static function isKey(string $value): bool
    {
        return $value !== '' && ctype_digit($value) && strlen($value) <= 18;
    }
}
