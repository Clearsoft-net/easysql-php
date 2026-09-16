<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Postgres\Tests;

use Clearsoft\EasySQL\Connectors\Postgres\TypeMapper;
use PHPUnit\Framework\TestCase;

class TypeMapperTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function typeProvider(): iterable
    {
        yield 'integer' => ['integer', 'integer'];
        yield 'bigint' => ['bigint', 'bigint'];
        yield 'boolean' => ['boolean', 'boolean'];
        yield 'text' => ['text', 'text'];
        yield 'uuid' => ['uuid', 'uuid'];
        yield 'jsonb' => ['jsonb', 'jsonb'];
        yield 'bytea (no JSON equivalent)' => ['bytea', 'bytea'];
        yield 'character varying with length' => ['character varying(255)', 'varchar(255)'];
        yield 'character with length' => ['character(3)', 'char(3)'];
        yield 'numeric precision' => ['numeric(10,2)', 'numeric(10,2)'];
        yield 'double precision' => ['double precision', 'double'];
        yield 'timestamp without time zone' => ['timestamp without time zone', 'timestamp'];
        yield 'timestamp with time zone' => ['timestamp with time zone', 'timestamptz'];
        yield 'time without time zone' => ['time without time zone', 'time'];
        yield 'time with time zone' => ['time with time zone', 'timetz'];
        yield 'array of integers' => ['integer[]', 'integer[]'];
        yield 'array of varchar' => ['character varying(50)[]', 'varchar(50)[]'];
        yield 'unknown type verbatim' => ['some_custom_type', 'some_custom_type'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('typeProvider')]
    public function testMapsTypes(string $input, string $expected): void
    {
        $this->assertSame($expected, TypeMapper::map($input));
    }
}
