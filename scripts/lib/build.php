<?php

/**
 * Code generation — builds Client.php method bodies and model classes.
 */

namespace Clearsoft\EasySQL\SDK\Scripts;

/**
 * Build the interface block (method signatures) for injection into the template.
 * Kept for potential separate ClientInterface.php generation.
 */
function buildInterface(array $methods): string
{
    $lines = [];

    foreach ($methods as $m) {
        $doc = buildPhpDoc($m);
        $sig = buildSignature($m);
        $lines[] = "    {$doc}";
        $lines[] = "    {$sig}";
    }

    return implode("\n", $lines);
}

/**
 * Build the implementation block (full methods with docblocks) for injection.
 * Each method gets 4-space indent since it lives inside the class body.
 */
function buildImpl(array $methods): string
{
    $out = [];

    foreach ($methods as $m) {
        $doc = buildPhpDoc($m);
        $body = buildMethodBody($m);

        $lines = [];
        foreach (explode("\n", $doc) as $docLine) {
            $lines[] = "    {$docLine}";
        }
        foreach (explode("\n", $body) as $bodyLine) {
            $lines[] = "    {$bodyLine}";
        }

        $out[] = implode("\n", $lines);
    }

    return implode("\n\n", $out);
}

/**
 * Build a PHP doc block for a method (no leading indent).
 */
function buildPhpDoc(GeneratedMethod $m): string
{
    $lines = [];
    $lines[] = "/**";
    $lines[] = " * {$m->summary}.";

    foreach ($m->pathParams as $p) {
        $lines[] = " * @param string \${$p["name"]}";
    }
    foreach ($m->queryParams as $p) {
        $type = $p["type"] === "integer" ? "int" : "string";
        if (!$p["required"]) {
            $type .= "|null";
        }
        $lines[] = " * @param {$type} \${$p["name"]}";
    }
    if ($m->paramStrategy === "body" || $m->paramStrategy === "explicit") {
        if ($m->requestSchema) {
            $lines[] = " * @param array \$body";
        }
    }
    if ($m->returnsNothing) {
        $lines[] = " * @return void";
    } else {
        $lines[] = " * @return array";
    }

    $lines[] = " */";
    return implode("\n", $lines);
}

/**
 * Build a PHP method signature string (no leading indent).
 */
function buildSignature(GeneratedMethod $m): string
{
    $params = buildMethodParams($m);
    $return = $m->returnsNothing ? "void" : "array";
    return "public function {$m->name}({$params}): {$return}";
}

/**
 * Build the parameter list string for a method signature.
 */
function buildMethodParams(GeneratedMethod $m): string
{
    $params = [];

    switch ($m->paramStrategy) {
        case "body":
            $params[] = 'array $body';
            break;

        case "path":
            foreach ($m->pathParams as $p) {
                $params[] = 'string $' . $p["name"];
            }
            break;

        case "query":
            $params[] = 'array $query = []';
            break;

        case "explicit":
            if ($m->hasBody) {
                $params[] = 'array $body';
            }
            foreach ($m->pathParams as $p) {
                $params[] = 'string $' . $p["name"];
            }
            foreach ($m->queryParams as $p) {
                $type = phpType($p["type"]);
                $default = !$p["required"]
                    ? " = " . defaultForType($p["type"])
                    : "";
                $params[] = "{$type} \${$p["name"]}{$default}";
            }
            break;

        case "none":
        default:
            break;
    }

    return implode(", ", $params);
}

/**
 * Build the complete method body (no leading indent — caller adds it).
 */
function buildMethodBody(GeneratedMethod $m): string
{
    $lines = [];
    $lines[] =
        "public function {$m->name}(" .
        buildMethodParams($m) .
        "): " .
        ($m->returnsNothing ? "void" : "array");
    $lines[] = "{";

    // ── Build path expression by splitting on {param} placeholders ──
    $pathExpr = buildPathExpr($m->path, $m->pathParams);

    // ── Build options array ──
    $options = [];

    if (
        $m->paramStrategy === "body" ||
        ($m->hasBody && $m->paramStrategy === "explicit")
    ) {
        $options[] = "'json' => \$body";
    }

    if ($m->paramStrategy === "query") {
        $options[] = "'query' => \$query";
    } elseif ($m->paramStrategy === "explicit" && !empty($m->queryParams)) {
        $queryVars = [];
        foreach ($m->queryParams as $p) {
            $queryVars[] = "'{$p["name"]}' => \${$p["name"]}";
        }
        $options[] = "'query' => [" . implode(", ", $queryVars) . "]";
    }

    // ── Build the request call ──
    $methodCall = strtolower($m->httpMethod);

    if (empty($options)) {
        $lines[] = "    \$response = \$this->request('{$methodCall}', {$pathExpr});";
    } else {
        $optionLines = [];
        foreach ($options as $opt) {
            $optionLines[] = "        {$opt},";
        }
        $lines[] = "    \$response = \$this->request('{$methodCall}', {$pathExpr}, [";
        $lines = array_merge($lines, $optionLines);
        $lines[] = "    ]);";
    }

    // ── Return ──
    if ($m->returnsNothing) {
        $lines[] = "}";
    } else {
        $lines[] =
            "    return json_decode((string) \$response->getBody(), true);";
        $lines[] = "}";
    }

    return implode("\n", $lines);
}

/**
 * Build a PHP string expression for a path with {param} placeholders.
 * e.g., "/v1/connectors/{connector_id}/sync" → "'/v1/connectors/' . \$connector_id . '/sync'"
 */
function buildPathExpr(string $path, array $pathParams): string
{
    // Build a lookup of param name → PHP variable string
    $vars = [];
    foreach ($pathParams as $p) {
        $vars[$p["name"]] = '$' . $p["name"];
    }

    // Split the path on {param} tokens, keeping delimiters
    $parts = preg_split("/(\{[^}]+\})/", $path, -1, PREG_SPLIT_DELIM_CAPTURE);
    $segments = [];

    foreach ($parts as $part) {
        if ($part === "") {
            continue;
        }
        if (preg_match('/^\{(\w+)\}$/', $part, $m)) {
            $paramName = $m[1];
            if (isset($vars[$paramName])) {
                $segments[] = $vars[$paramName];
            } else {
                // Unknown param — leave as literal
                $segments[] = var_export($part, true);
            }
        } else {
            $segments[] = var_export($part, true);
        }
    }

    // Join with concat operator
    $result = implode(" . ", $segments);

    // If result is just a string literal (no concat), return as-is
    if (!str_contains($result, " . ")) {
        return $result;
    }

    return $result;
}

/**
 * Map OpenAPI type to PHP type.
 */
function phpType(string $openApiType): string
{
    return match ($openApiType) {
        "integer", "number" => "int",
        "boolean" => "bool",
        default => "string",
    };
}

/**
 * Default value for a given type.
 */
function defaultForType(string $type): string
{
    return match ($type) {
        "integer", "number" => "0",
        "boolean" => "false",
        default => "''",
    };
}

/**
 * Generate model PHP classes from the spec's component schemas.
 */
function generateModels(array $spec, string $outputDir): array
{
    $schemas = $spec["components"]["schemas"] ?? [];
    $generated = [];

    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    foreach ($schemas as $name => $schema) {
        $props = $schema["properties"] ?? [];
        $required = $schema["required"] ?? [];
        $type = $schema["type"] ?? "object";

        if ($type !== "object" || empty($props)) {
            continue;
        }

        $phpCode = buildModelClass($name, $props, $required);
        $filePath = $outputDir . "/" . $name . ".php";
        file_put_contents($filePath, $phpCode);
        $generated[] = $name;
    }

    return $generated;
}

/**
 * Build a PHP model class string.
 */
function buildModelClass(
    string $name,
    array $properties,
    array $required,
): string {
    $lines = [];
    $lines[] = "<?php";
    $lines[] = "";
    $lines[] = "namespace Clearsoft\\EasySQL\\SDK\\Models;";
    $lines[] = "";
    $lines[] = "class {$name}";
    $lines[] = "{";

    // ── Properties ──
    foreach ($properties as $propName => $propSchema) {
        $phpType = phpType($propSchema["type"] ?? "string");
        $isRequired = in_array($propName, $required, true);
        $nullable = !$isRequired ? "?" : "";
        $lines[] = "    public {$nullable}{$phpType} \${$propName};";
    }

    $lines[] = "";
    $lines[] = "    /**";
    $lines[] = "     * @param array \$data Raw API response data.";
    $lines[] = "     */";
    $lines[] = "    public static function fromArray(array \$data): self";
    $lines[] = "    {";
    $lines[] = "        \$instance = new self();";

    foreach ($properties as $propName => $propSchema) {
        $phpType = phpType($propSchema["type"] ?? "string");
        $lines[] =
            "        \$instance->{$propName} = ({$phpType}) (\$data['{$propName}'] ?? " .
            defaultForProp($propSchema) .
            ");";
    }

    $lines[] = "        return \$instance;";
    $lines[] = "    }";
    $lines[] = "}";
    $lines[] = "";

    return implode("\n", $lines);
}

/**
 * Default value for a model property.
 */
function defaultForProp(array $propSchema): string
{
    $type = $propSchema["type"] ?? "string";
    return match ($type) {
        "integer", "number" => "0",
        "boolean" => "false",
        "array" => "[]",
        default => "''",
    };
}
