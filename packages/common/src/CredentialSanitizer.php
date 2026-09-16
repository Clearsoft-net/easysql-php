<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Common;

/**
 * Strips credentials from driver/exception messages before they are surfaced
 * or logged. Shared by the connectors that carry a user/password.
 */
final class CredentialSanitizer
{
    /**
     * Redact credentials from a message:
     *  - connection URIs: `scheme://user:password@host` -> `scheme://***@host`
     *  - libpq-style assignments: `password=...` / `pwd=...` -> `password=***`
     */
    public static function sanitize(string $message): string
    {
        $message = preg_replace('/\/\/[^@\s\/]+@/', '//***@', $message) ?? $message;
        $message = preg_replace(
            '/(password|passwd|pwd)\s*=\s*[^\s;]+/i',
            '$1=***',
            $message,
        ) ?? $message;

        return $message;
    }
}
