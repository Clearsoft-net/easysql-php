<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors;

use Clearsoft\EasySQL\Common\CredentialSanitizer;
use RuntimeException;
use Throwable;

/**
 * Connector errors with credentials stripped from driver messages.
 *
 * One exception type serves every engine; the engine label is part of the
 * message (e.g. "Failed to connect to MySQL: ..."), mirroring the shared
 * lifecycle the TypeScript sibling package uses.
 */
final class ConnectorException extends RuntimeException
{
    /**
     * @param list<string> $secrets Additional secrets to redact, beyond the
     *   URI/password patterns {@see CredentialSanitizer} already handles.
     */
    public static function connectionFailed(string $engine, Throwable $previous, array $secrets = []): self
    {
        return new self(
            self::build("Failed to connect to {$engine}", $previous, $secrets),
            0,
            $previous,
        );
    }

    /**
     * @param list<string> $secrets
     */
    public static function introspectionFailed(string $engine, Throwable $previous, array $secrets = []): self
    {
        return new self(
            self::build("{$engine} introspection failed", $previous, $secrets),
            0,
            $previous,
        );
    }

    /**
     * @param list<string> $secrets
     */
    public static function executionFailed(string $engine, Throwable $previous, array $secrets = []): self
    {
        return new self(
            self::build("{$engine} query execution failed", $previous, $secrets),
            0,
            $previous,
        );
    }

    /**
     * @param list<string> $secrets
     */
    private static function build(string $prefix, Throwable $previous, array $secrets): string
    {
        $message = CredentialSanitizer::sanitize($previous->getMessage());

        foreach ($secrets as $secret) {
            if ($secret !== '') {
                $message = str_replace($secret, '***', $message);
            }
        }

        return $prefix . ': ' . $message;
    }
}
