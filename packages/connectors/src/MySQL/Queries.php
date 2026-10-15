<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\MySQL;

/**
 * Catalog queries for MySQL/MariaDB.
 *
 * Introspection reads `information_schema` only. Foreign keys live in
 * `KEY_COLUMN_USAGE`: `information_schema.COLUMNS` has no `REFERENCED_*`
 * columns, so reading them from there fails on any database that has at least
 * one table.
 */
final class Queries
{
    /** Lists the base tables in the configured schema. */
    public const TABLES = "SELECT TABLE_NAME AS table_name, TABLE_ROWS AS table_rows
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = :schema AND TABLE_TYPE = 'BASE TABLE'
         ORDER BY TABLE_NAME";

    /** Lists the columns of one table, with primary and foreign key metadata. */
    public const COLUMNS = "SELECT c.COLUMN_NAME AS column_name, c.DATA_TYPE AS data_type,
                c.IS_NULLABLE AS is_nullable, c.COLUMN_DEFAULT AS column_default,
                c.COLUMN_KEY AS column_key, c.ORDINAL_POSITION AS ordinal_position,
                k.REFERENCED_TABLE_NAME AS referenced_table_name,
                k.REFERENCED_COLUMN_NAME AS referenced_column_name
         FROM information_schema.COLUMNS c
         LEFT JOIN information_schema.KEY_COLUMN_USAGE k
                ON k.TABLE_SCHEMA = c.TABLE_SCHEMA
               AND k.TABLE_NAME = c.TABLE_NAME
               AND k.COLUMN_NAME = c.COLUMN_NAME
               AND k.REFERENCED_TABLE_NAME IS NOT NULL
         WHERE c.TABLE_SCHEMA = :schema AND c.TABLE_NAME = :table
         ORDER BY c.ORDINAL_POSITION";
}
