<?php

namespace App\Support;

use Carbon\Carbon;
use InvalidArgumentException;

class LegacyDateParser
{
    /**
     * @var list<string>
     */
    protected static array $formats = [
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d',
        'd/m/Y H:i:s',
        'd/m/Y H:i',
        'd/m/Y',
    ];

    public static function parse(?string $value, string $fieldLabel, int $rowNumber): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        foreach (static::$formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value);

                if ($parsed !== false) {
                    return $parsed;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException(
                "Fila {$rowNumber}: fecha inválida en {$fieldLabel} ({$value}).",
                previous: $exception,
            );
        }
    }
}
