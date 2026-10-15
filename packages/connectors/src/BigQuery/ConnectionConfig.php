<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\BigQuery;

use Clearsoft\EasySQL\Common\ConnectionUrl;
use Clearsoft\EasySQL\Connectors\ConnectionConfigInterface;
use InvalidArgumentException;

/**
 * Connection configuration for a Google BigQuery project.
 *
 * BigQuery has no password. Authentication is either a service-account key —
 * supplied as the path to a JSON key file or as the JSON content itself — or
 * Application Default Credentials when neither is given. The credentials are
 * held in memory only and are stripped from error messages.
 */
final class ConnectionConfig implements ConnectionConfigInterface
{
    public function __construct(
        public readonly string $projectId = '',
        public readonly string $datasetId = '',
        public readonly string $credentialsJson = '',
        public readonly string $location = '',
        public readonly float $timeout = 10.0,
    ) {
    }

    /**
     * Builds the config from a `bigquery://project[/dataset]` URL.
     */
    public static function fromUrl(string $url): static
    {
        $parsed = ConnectionUrl::parse($url);
        if ($parsed['type'] !== 'bigquery') {
            throw new InvalidArgumentException(
                "Expected a bigquery connection string, received '{$parsed['type']}'.",
            );
        }

        return new self(
            projectId: $parsed['host'],
            datasetId: $parsed['database'],
        );
    }
}
