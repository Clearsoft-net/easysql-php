<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\D1;

use Clearsoft\EasySQL\Connectors\Helpers;

/**
 * Catalog queries for the Cloudflare D1 engine.
 *
 * D1 exposes the SQLite catalog over its REST API. The table listing reads
 * `sqlite_master`; column and foreign-key metadata use the `PRAGMA table_info`
 * and `PRAGMA foreign_key_list` statements Cloudflare documents as compatible,
 * with the table-valued functions as a fallback for endpoints that reject the
 * PRAGMA form.
 */
final class Queries
{
    /** Lists the user tables in the database, in name order. */
    public const TABLES = "SELECT name FROM sqlite_master
         WHERE type = 'table' AND name NOT LIKE 'sqlite_%'
         ORDER BY name";

    /** The documented `PRAGMA table_info` statement for `$table`. */
    public static function tableInfo(string $table): string
    {
        return 'PRAGMA table_info(' . Helpers::quoteIdent($table) . ')';
    }

    /** The documented `PRAGMA foreign_key_list` statement for `$table`. */
    public static function foreignKeyList(string $table): string
    {
        return 'PRAGMA foreign_key_list(' . Helpers::quoteIdent($table) . ')';
    }

    /** The table-valued fallback for the column pragma. */
    public const TABLE_INFO_FALLBACK = 'SELECT * FROM pragma_table_info(?)';

    /** The table-valued fallback for the foreign-key pragma. */
    public const FOREIGN_KEY_LIST_FALLBACK = 'SELECT * FROM pragma_foreign_key_list(?)';
}
