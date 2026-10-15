<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors;

/**
 * SQL construction helpers shared by the connectors.
 *
 * The small, engine-agnostic pieces every introspection query needs: quoting
 * identifiers and string literals, and building a stable key for a
 * (table, column) pair. Engine-specific catalog SQL lives in each engine's
 * `Queries` class.
 */
final class Helpers
{
    /**
     * Quotes an identifier with double quotes, doubling any embedded quotes.
     */
    public static function quoteIdent(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }

    /**
     * Single-quotes a SQL string literal, doubling any embedded quotes.
     */
    public static function quoteLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * A stable, collision-free key for a (table, column) pair.
     */
    public static function columnKey(string $table, string $column): string
    {
        return $table . "\0" . $column;
    }
}
