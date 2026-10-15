<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\SQLite;

use Clearsoft\EasySQL\Connectors\Helpers;

/**
 * Catalog queries for the SQLite engine.
 *
 * Introspection reads `sqlite_master` for the table listing and the
 * `PRAGMA table_info` / `PRAGMA foreign_key_list` pragmas for column and
 * foreign-key metadata.
 */
final class Queries
{
    /** Lists the user tables in the database, in name order. */
    public const TABLES = "SELECT name FROM sqlite_master
         WHERE type = 'table' AND name NOT LIKE 'sqlite_%'
         ORDER BY name";

    /** Builds the statement that reports the columns of `$table`. */
    public static function tableInfo(string $table): string
    {
        return 'PRAGMA table_info(' . Helpers::quoteIdent($table) . ')';
    }

    /** Builds the statement that reports the foreign keys of `$table`. */
    public static function foreignKeyList(string $table): string
    {
        return 'PRAGMA foreign_key_list(' . Helpers::quoteIdent($table) . ')';
    }

    /** Builds the statement that counts the rows of `$table`. */
    public static function rowCount(string $table): string
    {
        return 'SELECT COUNT(*) AS c FROM ' . Helpers::quoteIdent($table);
    }
}
