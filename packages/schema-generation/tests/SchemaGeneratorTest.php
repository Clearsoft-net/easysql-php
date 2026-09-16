<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\SchemaGeneration\Tests;

use Clearsoft\EasySQL\SchemaGeneration\SchemaGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SchemaGeneratorTest extends TestCase
{
    private SchemaGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new SchemaGenerator();
    }

    // ── Fixtures ──────────────────────────────────────────────

    /**
     * @return iterable<string, array{string}>
     */
    public static function fixtureProvider(): iterable
    {
        yield 'mysql shop' => [__DIR__ . '/fixtures/mysql/shop.json'];
        yield 'sqlite shop' => [__DIR__ . '/fixtures/sqlite/shop.json'];
        yield 'empty database' => [__DIR__ . '/fixtures/empty.json'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('fixtureProvider')]
    public function testFixtureProducesExpectedPayload(string $fixturePath): void
    {
        $fixture = json_decode((string) file_get_contents($fixturePath), true);

        $payload = $this->generator->generate($fixture['input']);

        $this->assertSame($fixture['expected'], $payload);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('fixtureProvider')]
    public function testGenerationIsDeterministic(string $fixturePath): void
    {
        $fixture = json_decode((string) file_get_contents($fixturePath), true);

        $first = $this->generator->generate($fixture['input']);
        $second = $this->generator->generate($fixture['input']);

        $this->assertSame($first, $second);
        $this->assertSame(
            $this->generator->toJson($first),
            $this->generator->toJson($second),
        );
    }

    // ── Type mapping ──────────────────────────────────────────

    public function testTypeMappingNormalizesCase(): void
    {
        $payload = $this->generator->generate([
            'tables' => [
                [
                    'name' => 't',
                    'columns' => [
                        ['name' => 'a', 'type' => 'VARCHAR', 'nullable' => true, 'primary_key' => false, 'default' => null, 'foreign_key' => null],
                        ['name' => 'b', 'type' => 'Int', 'nullable' => false, 'primary_key' => true, 'default' => null, 'foreign_key' => null],
                    ],
                    'rows_approx' => null,
                ],
            ],
        ]);

        $this->assertSame('varchar', $payload[0]['columns'][0]['type']);
        $this->assertSame('int', $payload[0]['columns'][1]['type']);
    }

    public function testEmptyTypeFallsBackToBlob(): void
    {
        $payload = $this->generator->generate([
            'tables' => [
                [
                    'name' => 't',
                    'columns' => [
                        ['name' => 'a', 'type' => '', 'nullable' => true, 'primary_key' => false, 'default' => null, 'foreign_key' => null],
                    ],
                    'rows_approx' => null,
                ],
            ],
        ]);

        $this->assertSame('blob', $payload[0]['columns'][0]['type']);
    }

    // ── Ordering ──────────────────────────────────────────────

    public function testTablesAreSortedByName(): void
    {
        $payload = $this->generator->generate([
            'tables' => [
                ['name' => 'zebra', 'columns' => [], 'rows_approx' => null],
                ['name' => 'apple', 'columns' => [], 'rows_approx' => null],
                ['name' => 'mango', 'columns' => [], 'rows_approx' => null],
            ],
        ]);

        $this->assertSame(
            ['apple', 'mango', 'zebra'],
            array_column($payload, 'name'),
        );
    }

    // ── Foreign keys ──────────────────────────────────────────

    public function testMalformedForeignKeyBecomesNull(): void
    {
        $payload = $this->generator->generate([
            'tables' => [
                [
                    'name' => 't',
                    'columns' => [
                        ['name' => 'a', 'type' => 'int', 'nullable' => true, 'primary_key' => false, 'default' => null, 'foreign_key' => ['table' => 'u']],
                        ['name' => 'b', 'type' => 'int', 'nullable' => true, 'primary_key' => false, 'default' => null, 'foreign_key' => 'garbage'],
                    ],
                    'rows_approx' => null,
                ],
            ],
        ]);

        $this->assertNull($payload[0]['columns'][0]['foreign_key']);
        $this->assertNull($payload[0]['columns'][1]['foreign_key']);
    }

    // ── Validation ────────────────────────────────────────────

    public function testMissingTablesKeyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->generator->generate(['nope' => []]);
    }

    public function testTableWithoutColumnsThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->generator->generate([
            'tables' => [['name' => 't']],
        ]);
    }

    public function testColumnWithoutNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->generator->generate([
            'tables' => [
                [
                    'name' => 't',
                    'columns' => [['type' => 'int']],
                    'rows_approx' => null,
                ],
            ],
        ]);
    }

    // ── No I/O ────────────────────────────────────────────────

    public function testPackagePerformsNoIo(): void
    {
        $src = (string) file_get_contents(__DIR__ . '/../src/SchemaGenerator.php');
        $src .= (string) file_get_contents(__DIR__ . '/../src/RawSchemaValidator.php');

        foreach (['file_get_contents', 'file_put_contents', 'fopen', 'curl_', 'PDO', 'mysqli', 'socket_'] as $needle) {
            $this->assertStringNotContainsString($needle, $src, "Found I/O usage: {$needle}");
        }
    }
}
