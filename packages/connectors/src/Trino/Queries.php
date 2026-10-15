<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Trino;

use Clearsoft\EasySQL\Connectors\Helpers;

/**
 * Catalog queries for the Trino engine.
 *
 * Introspection reads `information_schema`: `tables` lists the base tables of
 * the current schema and `columns` lists the column metadata of one table.
 * Trino's `current_schema` is a keyword-like session function without
 * parentheses, so it is emitted verbatim rather than called.
 */
final class Queries
{
    /** Lists the base tables in the current schema. */
    public const TABLES = "SELECT table_name FROM information_schema.tables
         WHERE table_schema = current_schema
           AND table_type = 'BASE TABLE'
         ORDER BY table_name";

    /** Lists the columns of one table, inlined as a literal. */
    public static function columns(string $table): string
    {
        return 'SELECT column_name, data_type, is_nullable, column_default, ordinal_position
         FROM information_schema.columns
         WHERE table_schema = current_schema
           AND table_name = ' . Helpers::quoteLiteral($table) . '
         ORDER BY ordinal_position';
    }
}
