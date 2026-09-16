<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Common;

/**
 * Defense-in-depth SQL validator shared by the connector packages.
 *
 * The EasySQL API already enforces SELECT-only server-side. This client-side
 * check is a safety belt: a connector never executes a mutation locally, even
 * if the generated SQL was tampered with.
 *
 * Strategy: strip comments + string literals, then match forbidden keywords
 * over the remaining code. Not as rigorous as a full AST — but the server
 * is the source of truth.
 */
final class SqlValidator
{
    private const FORBIDDEN_KEYWORDS = [
        'INSERT',
        'UPDATE',
        'DELETE',
        'DROP',
        'TRUNCATE',
        'ALTER',
        'CREATE',
        'GRANT',
        'REVOKE',
        'EXEC',
        'EXECUTE',
        'CALL',
        'COPY',
        'VACUUM',
        'REINDEX',
        'LOCK',
        'UNLOCK',
        'RENAME',
        'SET\s+ROLE',
        'SET\s+SESSION',
        'RESET',
        '\bINTO\b',
        '\bOUTFILE\b',
        '\bLOAD_FILE\b',
    ];

    /**
     * @return array{ok: bool, reason?: string}
     */
    public static function validateSelectOnly(string $sql): array
    {
        $cleaned = trim(self::neutralizeLiterals($sql));

        if ($cleaned === '') {
            return ['ok' => false, 'reason' => 'Empty SQL'];
        }

        // Reject stacked statements (semicolons other than at the very end)
        $semicolons = substr_count($cleaned, ';');
        if ($semicolons > 1 || ($semicolons === 1 && !str_ends_with($cleaned, ';'))) {
            return ['ok' => false, 'reason' => 'Multiple statements are not allowed'];
        }

        // First token must be WITH, SELECT, EXPLAIN or SHOW
        if (!preg_match('/^\s*(WITH|SELECT|EXPLAIN|SHOW)\b/i', $cleaned)) {
            return [
                'ok' => false,
                'reason' => 'Statement must start with SELECT, WITH, EXPLAIN, or SHOW',
            ];
        }

        foreach (self::FORBIDDEN_KEYWORDS as $keyword) {
            if (preg_match('/\b' . $keyword . '\b/i', $cleaned)) {
                return [
                    'ok' => false,
                    'reason' => 'Forbidden keyword detected: ' . str_replace('\b', '', $keyword),
                ];
            }
        }

        return ['ok' => true];
    }

    private static function neutralizeLiterals(string $sql): string
    {
        // Line comments
        $out = preg_replace('/--[^\n]*/', ' ', $sql) ?? $sql;
        // Block comments
        $out = preg_replace('/\/\*.*?\*\//s', ' ', $out) ?? $out;
        // Single-quoted strings (handle escaped quotes)
        $out = preg_replace("/'(?:''|[^'])*'/", "''", $out) ?? $out;
        // Double-quoted identifiers
        $out = preg_replace('/"(?:""|[^"])*"/', '""', $out) ?? $out;
        // Backtick-quoted identifiers (MySQL)
        $out = preg_replace('/`(?:``|[^`])*`/', '``', $out) ?? $out;

        return $out;
    }
}
