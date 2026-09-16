<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Postgres;

/**
 * Maps PostgreSQL type names (as returned by format_type()) into the schema
 * vocabulary, keeping length/precision modifiers and array suffixes.
 *
 * PostgreSQL has no JSON equivalent for several types; those are preserved by
 * their canonical PostgreSQL name and documented in the package README.
 * Types not listed here are returned verbatim.
 */
final class TypeMapper
{
    /**
     * Canonical name normalization for types whose PostgreSQL spelling is
     * ambiguous or verbose.
     *
     * @var array<string, string>
     */
    private const BASE_MAP = [
        'character varying' => 'varchar',
        'character' => 'char',
        'double precision' => 'double',
        'timestamp without time zone' => 'timestamp',
        'timestamp with time zone' => 'timestamptz',
        'time without time zone' => 'time',
        'time with time zone' => 'timetz',
        'bit varying' => 'varbit',
    ];

    /**
     * @param string $pgType Raw value from format_type(), e.g. "character varying(255)" or "integer[]".
     */
    public static function map(string $pgType): string
    {
        $arraySuffix = '';
        if (str_ends_with($pgType, '[]')) {
            $arraySuffix = '[]';
            $pgType = substr($pgType, 0, -2);
        }

        $modifier = '';
        if (preg_match('/^(.*?)(\(.*\))$/', $pgType, $matches) === 1) {
            $base = $matches[1];
            $modifier = $matches[2];
        } else {
            $base = $pgType;
        }

        $base = self::BASE_MAP[$base] ?? $base;

        return $base . $modifier . $arraySuffix;
    }
}
