<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\DuckDb;

/**
 * Catalog queries for DuckDB.
 *
 * DuckDB exposes its catalog through table functions (`duckdb_tables()`,
 * `duckdb_constraints()`, `duckdb_columns()`), scoped to the current database
 * and schema.
 */
final class Queries
{
    /** Base tables in the current database/schema, with an estimated size. */
    public const TABLES = "SELECT table_name, estimated_size FROM duckdb_tables()
         WHERE NOT (internal)
           AND database_name = current_database()
           AND schema_name = current_schema()
         ORDER BY table_name";

    /** Primary- and foreign-key constraints of the current database/schema. */
    public const CONSTRAINTS = "SELECT table_name, constraint_type, constraint_column_names,
                referenced_table, referenced_column_names
         FROM duckdb_constraints()
         WHERE database_name = current_database()
           AND schema_name = current_schema()
         ORDER BY table_name, constraint_index";

    /** Columns of one table (the table name is bound as `?`). */
    public const COLUMNS = "SELECT column_name, column_index, data_type, is_nullable, column_default
         FROM duckdb_columns()
         WHERE database_name = current_database()
           AND schema_name = current_schema()
           AND table_name = ?
         ORDER BY column_index";
}
