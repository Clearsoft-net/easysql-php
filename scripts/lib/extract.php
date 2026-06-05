<?php

/**
 * Spec parsing — extracts a list of GeneratedMethod from an OpenAPI 3.x spec.
 */

namespace Clearsoft\EasySQL\SDK\Scripts;

/**
 * Represents a single API operation ready to be rendered into the client.
 */
class GeneratedMethod
{
    /** CamelCase method name: "login", "listConnectors", "getConnector" */
    public string $name;

    /** HTTP method: GET, POST, PUT, PATCH, DELETE */
    public string $httpMethod;

    /** Full path template: "/v1/connectors/{connector_id}" */
    public string $path;

    /** Raw operationId from spec */
    public string $operationId;

    /** Primary tag (category): "auth", "connectors", etc. */
    public string $tag;

    /** Human-readable summary */
    public string $summary;

    /** Whether this endpoint requires authentication */
    public bool $requiresAuth;

    /** Schema name for the request body, if any */
    public ?string $requestSchema;

    /** Schema name for the success response body, if any */
    public ?string $responseSchema;

    /** True if the success response is 204 No Content */
    public bool $returnsNothing;

    /** Path parameters: [['name' => 'connector_id', 'type' => 'string', 'format' => 'uuid', 'required' => true]] */
    public array $pathParams;

    /** Query parameters: [['name' => 'page', 'type' => 'integer', 'required' => false]] */
    public array $queryParams;

    /** Whether the operation has a request body */
    public bool $hasBody;

    /** PHP return type hint: "array", "void", etc. */
    public string $returnType;

    /** Parameter strategy: "body", "path", "query", "explicit" */
    public string $paramStrategy;
}

/**
 * Download the spec JSON from a URL with retry + backoff.
 */
function downloadSpec(string $url, int $maxRetries = 3): array
{
    $delay = 1; // seconds

    for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
        if ($attempt > 0) {
            fwrite(STDERR, "Retry {$attempt}/{$maxRetries} in {$delay}s...\n");
            sleep($delay);
            $delay *= 2; // exponential backoff
        }

        $json = @file_get_contents($url);
        if ($json !== false) {
            $data = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                return $data;
            }
            fwrite(STDERR, "Invalid JSON received.\n");
        }
    }

    throw new \RuntimeException("Failed to download spec from {$url} after {$maxRetries} retries.");
}

/**
 * Parse an OpenAPI spec and return a list of GeneratedMethod objects.
 */
function extractMethods(array $spec): array
{
    $methods = [];

    foreach ($spec['paths'] ?? [] as $path => $pathItem) {
        foreach ($pathItem as $httpMethod => $operation) {
            if (!in_array(strtolower($httpMethod), ['get', 'post', 'put', 'patch', 'delete'], true)) {
                continue;
            }

            $httpMethod = strtoupper($httpMethod);

            // ── Method name from operationId ──
            $operationId = $operation['operationId'] ?? '';
            $methodName = deriveMethodName($operationId, $httpMethod);

            // ── Tag (category) ──
            $tags = $operation['tags'] ?? [];
            $tag = !empty($tags) ? $tags[0] : 'default';

            // ── Summary ──
            $summary = $operation['summary'] ?? $methodName;

            // ── Auth ──
            $requiresAuth = !empty($operation['security'] ?? null);

            // ── Request body ──
            $hasBody = isset($operation['requestBody']);
            $requestSchema = null;
            if ($hasBody) {
                $content = $operation['requestBody']['content'] ?? [];
                $jsonContent = $content['application/json'] ?? [];
                $ref = $jsonContent['schema']['$ref'] ?? null;
                if ($ref) {
                    $requestSchema = refToSchemaName($ref);
                }
            }

            // ── Response schema ──
            $responseSchema = null;
            $returnsNothing = false;
            foreach ($operation['responses'] ?? [] as $statusCode => $response) {
                $code = (int) $statusCode;
                if ($code >= 200 && $code < 300) {
                    if ($code === 204) {
                        $returnsNothing = true;
                    }
                    $respContent = $response['content'] ?? [];
                    $jsonContent = $respContent['application/json'] ?? [];
                    $ref = $jsonContent['schema']['$ref'] ?? null;
                    if ($ref) {
                        $responseSchema = refToSchemaName($ref);
                    }
                    break;
                }
            }

            // ── Parameters ──
            $pathParams = [];
            $queryParams = [];
            foreach ($operation['parameters'] ?? [] as $param) {
                $p = [
                    'name'     => $param['name'],
                    'type'     => $param['schema']['type'] ?? 'string',
                    'format'   => $param['schema']['format'] ?? null,
                    'required' => $param['required'] ?? false,
                    'in'       => $param['in'],
                ];
                if ($param['in'] === 'path') {
                    $pathParams[] = $p;
                } elseif ($param['in'] === 'query') {
                    $queryParams[] = $p;
                }
            }

            // ── Return type ──
            $returnType = $returnsNothing ? 'void' : 'array';

            // ── Parameter strategy ──
            $paramStrategy = determineParamStrategy($hasBody, $pathParams, $queryParams);

            $m = new GeneratedMethod();
            $m->name           = $methodName;
            $m->httpMethod     = $httpMethod;
            $m->path           = $path;
            $m->operationId    = $operationId;
            $m->tag            = $tag;
            $m->summary        = $summary;
            $m->requiresAuth   = $requiresAuth;
            $m->requestSchema  = $requestSchema;
            $m->responseSchema = $responseSchema;
            $m->returnsNothing = $returnsNothing;
            $m->pathParams     = $pathParams;
            $m->queryParams    = $queryParams;
            $m->hasBody        = $hasBody;
            $m->returnType     = $returnType;
            $m->paramStrategy  = $paramStrategy;

            $methods[] = $m;
        }
    }

    // Sort: by tag, then by name
    usort($methods, function (GeneratedMethod $a, GeneratedMethod $b) {
        $tagCmp = strcmp($a->tag, $b->tag);
        if ($tagCmp !== 0) return $tagCmp;
        return strcmp($a->name, $b->name);
    });

    return $methods;
}

/**
 * Derive a camelCase method name from the operationId.
 *
 * Examples:
 *   login_v1_auth_login_post        → login
 *   me_v1_auth_me_get              → me
 *   list_connectors_v1_connectors_get → listConnectors
 *   get_connector_v1_connectors__connector_id__get → getConnector
 *   change_password_v1_auth_change_password_post → changePassword
 *   delete_me_v1_auth_me_delete    → deleteMe
 *   update_me_v1_auth_me_patch     → updateMe
 */
function deriveMethodName(string $operationId, string $httpMethod): string
{
    // Strip the _v\d+_ version marker and the HTTP method suffix
    // Pattern: everything before the version marker (but also handle the HTTP method at the end)
    //
    // First, strip the trailing _<httpMethod> suffix
    $httpSuffix = '_' . strtolower($httpMethod);
    if (str_ends_with($operationId, $httpSuffix)) {
        $operationId = substr($operationId, 0, -strlen($httpSuffix));
    }

    // Now remove the _v\d+_ marker and everything after it (e.g., _v1_auth_login → just the first part)
    // Actually the pattern is: <method>_v<digit>_<path...>
    // Example: list_connectors_v1_connectors_get (after stripping _get)
    //        → list_connectors_v1_connectors
    // We need to extract the segment before _v<digit>_
    if (preg_match('/^(.+?)_v\d+_.*$/', $operationId, $m)) {
        $operationId = $m[1];
    }

    // Convert snake_case to camelCase
    $parts = explode('_', $operationId);
    $camel = $parts[0];
    for ($i = 1; $i < count($parts); $i++) {
        $camel .= ucfirst($parts[$i]);
    }

    return $camel;
}

/**
 * Determine the parameter strategy for a method.
 *
 *   - "body":    only request body, no path/query params
 *   - "path":    only path params, no body/query
 *   - "query":   only query params, no body/path
 *   - "explicit": mixed (body + path, etc.)
 */
function determineParamStrategy(bool $hasBody, array $pathParams, array $queryParams): string
{
    $paramTypes = [];
    if ($hasBody) $paramTypes[] = 'body';
    if (!empty($pathParams)) $paramTypes[] = 'path';
    if (!empty($queryParams)) $paramTypes[] = 'query';

    if (count($paramTypes) === 0) {
        return 'none';
    }
    if (count($paramTypes) === 1) {
        return $paramTypes[0];
    }
    return 'explicit';
}

/**
 * Extract schema name from a $ref string like "#/components/schemas/UserLogin".
 */
function refToSchemaName(string $ref): string
{
    $parts = explode('/', $ref);
    return end($parts);
}
