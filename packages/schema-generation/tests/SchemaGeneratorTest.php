<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\SchemaGeneration\Tests;

use Clearsoft\EasySQL\SchemaGeneration\SchemaGenerator;
use Clearsoft\EasySQL\SchemaGeneration\TypeMapper;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SchemaGeneratorTest extends TestCase
{
    private SchemaGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new SchemaGenerator();
    }

    /**
     * @return array<string, mixed>
     */
    private static function fixture(string $name): array
    {
        return json_decode(
            (string) file_get_contents(__DIR__ . "/fixtures/{$name}.json"),
            true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function table(array $payload, string $name): array
    {
        foreach ($payload as $table) {
            if ($table['name'] === $name) {
                return $table;
            }
        }

        throw new \RuntimeException("Table {$name} not found in payload.");
    }

    // ── Fixtures / determinism ────────────────────────────────

    /**
     * @return iterable<string, array{string}>
     */
    public static function fixtureProvider(): iterable
    {
        yield 'mysql' => ['mysql'];
        yield 'sqlite' => ['sqlite'];
        yield 'clickhouse' => ['clickhouse'];
    }

    #[DataProvider('fixtureProvider')]
    public function testProducesTheSamePayloadOnEveryRun(string $fixture): void
    {
        $raw = self::fixture($fixture);

        $this->assertSame($this->generator->generate($raw), $this->generator->generate($raw));
        $this->assertSame(
            $this->generator->toJson($this->generator->generate($raw)),
            $this->generator->toJson($this->generator->generate($raw)),
        );
    }

    #[DataProvider('fixtureProvider')]
    public function testStableRegardlessOfInputTableOrder(string $fixture): void
    {
        $raw = self::fixture($fixture);
        $shuffled = $raw;
        $shuffled['tables'] = array_reverse($raw['tables']);

        $this->assertSame(
            $this->generator->generate($raw),
            $this->generator->generate($shuffled),
        );
    }

    #[DataProvider('fixtureProvider')]
    public function testNeverMutatesItsInput(string $fixture): void
    {
        $raw = self::fixture($fixture);
        $snapshot = $raw;

        $this->generator->generate($raw);

        $this->assertSame($snapshot, $raw);
    }

    public function testSortsTablesByName(): void
    {
        $payload = $this->generator->generate(self::fixture('mysql'));

        $this->assertSame(['post_tags', 'posts', 'users'], array_column($payload, 'name'));
    }

    public function testKeepsDeclaredColumnOrderWhenOrdinalIsPresent(): void
    {
        $users = self::table($this->generator->generate(self::fixture('mysql')), 'users');

        $this->assertSame(
            ['id', 'email', 'name', 'role', 'balance', 'is_active', 'bio', 'created_at'],
            array_column($users['columns'], 'name'),
        );
    }

    // ── Payload shape ─────────────────────────────────────────

    public function testEmitsTheApiContractFieldsForEveryColumn(): void
    {
        $users = self::table($this->generator->generate(self::fixture('mysql')), 'users');

        $this->assertSame(
            [
                'name' => 'id',
                'type' => 'integer',
                'nullable' => false,
                'primary_key' => true,
                'default' => null,
                'foreign_key' => null,
            ],
            $users['columns'][0],
        );
    }

    public function testCarriesPrimaryKeysForeignKeysAndDefaultsThrough(): void
    {
        $payload = $this->generator->generate(self::fixture('mysql'));

        $posts = self::table($payload, 'posts');
        $userId = null;
        foreach ($posts['columns'] as $column) {
            if ($column['name'] === 'user_id') {
                $userId = $column;
            }
        }
        $this->assertSame(['table' => 'users', 'column' => 'id'], $userId['foreign_key']);

        $users = self::table($payload, 'users');
        $byName = array_column($users['columns'], null, 'name');
        $this->assertSame('user', $byName['role']['default']);
        $this->assertNull($byName['bio']['default']);
    }

    public function testPreservesCompositeKeys(): void
    {
        $postTags = self::table($this->generator->generate(self::fixture('mysql')), 'post_tags');

        $keys = [];
        foreach ($postTags['columns'] as $column) {
            if ($column['primary_key']) {
                $keys[] = $column['name'];
            }
        }
        $this->assertSame(['post_id', 'tag_id'], $keys);
    }

    public function testPassesRowsApproxThroughAndDefaultsItToNull(): void
    {
        $payload = $this->generator->generate(self::fixture('mysql'));
        $this->assertSame(42, self::table($payload, 'users')['rows_approx']);

        $noRows = $this->generator->generate([
            'engine' => 'sqlite',
            'tables' => [
                ['name' => 'empty', 'columns' => [['name' => 'id', 'dataType' => 'INTEGER', 'nullable' => true]]],
            ],
        ]);
        $this->assertNull($noRows[0]['rows_approx']);
    }

    public function testEmptyDatabaseReturnsAnEmptyPayload(): void
    {
        $this->assertSame([], $this->generator->generate(['engine' => 'mysql', 'tables' => []]));
    }

    // ── Type mapping — MySQL ──────────────────────────────────

    public function testMapsMysqlIntegersBooleansAndNumerics(): void
    {
        $this->assertSame('integer', TypeMapper::mapMysql('int'));
        $this->assertSame('bigint', TypeMapper::mapMysql('BIGINT'));
        $this->assertSame('boolean', TypeMapper::mapMysql('tinyint(1)'));
        $this->assertSame('smallint', TypeMapper::mapMysql('tinyint(4)'));
        $this->assertSame('decimal', TypeMapper::mapMysql('decimal(10,2)'));
    }

    public function testMapsMysqlStringsTextBinaryAndTemporal(): void
    {
        $this->assertSame('string', TypeMapper::mapMysql('varchar(255)'));
        $this->assertSame('text', TypeMapper::mapMysql('longtext'));
        $this->assertSame('binary', TypeMapper::mapMysql('varbinary(16)'));
        $this->assertSame('timestamp', TypeMapper::mapMysql('timestamp'));
        $this->assertSame('json', TypeMapper::mapMysql('json'));
    }

    public function testMysqlFallsBackToUnknown(): void
    {
        $this->assertSame('unknown', TypeMapper::mapMysql('geometry'));
    }

    // ── Type mapping — SQLite ─────────────────────────────────

    public function testResolvesSqliteDeclaredTypeAffinity(): void
    {
        $this->assertSame('integer', TypeMapper::mapSqlite('INTEGER'));
        $this->assertSame('text', TypeMapper::mapSqlite('VARCHAR(255)'));
        $this->assertSame('text', TypeMapper::mapSqlite('TEXT'));
        $this->assertSame('float', TypeMapper::mapSqlite('REAL'));
        $this->assertSame('decimal', TypeMapper::mapSqlite('NUMERIC'));
        $this->assertSame('blob', TypeMapper::mapSqlite(''));
    }

    public function testRefinesSqliteAffinityWithNamedTypes(): void
    {
        $this->assertSame('boolean', TypeMapper::mapSqlite('BOOLEAN'));
        $this->assertSame('datetime', TypeMapper::mapSqlite('DATETIME'));
        $this->assertSame('date', TypeMapper::mapSqlite('DATE'));
        $this->assertSame('json', TypeMapper::mapSqlite('JSON'));
        $this->assertSame('uuid', TypeMapper::mapSqlite('UUID'));
    }

    // ── Type mapping — ClickHouse ─────────────────────────────

    public function testMapsClickhouseScalars(): void
    {
        $this->assertSame('smallint', TypeMapper::mapClickhouse('UInt8'));
        $this->assertSame('integer', TypeMapper::mapClickhouse('UInt32'));
        $this->assertSame('bigint', TypeMapper::mapClickhouse('Int64'));
        $this->assertSame('bigint', TypeMapper::mapClickhouse('UInt64'));
        $this->assertSame('float', TypeMapper::mapClickhouse('Float64'));
        $this->assertSame('decimal', TypeMapper::mapClickhouse('Decimal(18, 4)'));
        $this->assertSame('boolean', TypeMapper::mapClickhouse('Bool'));
    }

    public function testUnwrapsClickhouseNullableAndLowCardinality(): void
    {
        $this->assertSame('string', TypeMapper::mapClickhouse('Nullable(String)'));
        $this->assertSame('string', TypeMapper::mapClickhouse('LowCardinality(String)'));
        $this->assertSame('decimal', TypeMapper::mapClickhouse('Nullable(Decimal(18, 4))'));
        $this->assertSame('string', TypeMapper::mapClickhouse('LowCardinality(Nullable(String))'));
    }

    public function testMapsClickhouseStringsTemporalAndComposites(): void
    {
        $this->assertSame('string', TypeMapper::mapClickhouse('String'));
        $this->assertSame('string', TypeMapper::mapClickhouse('FixedString(2)'));
        $this->assertSame('date', TypeMapper::mapClickhouse('Date'));
        $this->assertSame('timestamp', TypeMapper::mapClickhouse('DateTime64(3, \'UTC\')'));
        $this->assertSame('uuid', TypeMapper::mapClickhouse('UUID'));
        $this->assertSame('enum', TypeMapper::mapClickhouse("Enum8('a' = 1)"));
        $this->assertSame('json', TypeMapper::mapClickhouse('Array(String)'));
        $this->assertSame('json', TypeMapper::mapClickhouse('Map(String, UInt8)'));
        $this->assertSame('json', TypeMapper::mapClickhouse('Tuple(String, UInt8)'));
        $this->assertSame('json', TypeMapper::mapClickhouse('Nested(x UInt8)'));
        $this->assertSame('unknown', TypeMapper::mapClickhouse('AggregateFunction(sum, UInt64)'));
    }

    public function testNormalizesAWholeClickhouseSchema(): void
    {
        $events = self::table($this->generator->generate(self::fixture('clickhouse')), 'events');

        $this->assertSame(
            ['bigint', 'integer', 'string', 'json', 'decimal', 'json', 'uuid', 'timestamp', 'date', 'boolean'],
            array_column($events['columns'], 'type'),
        );
    }

    // ── Type mapping — PostgreSQL ─────────────────────────────

    public function testMapsPostgresScalars(): void
    {
        $this->assertSame('integer', TypeMapper::mapPostgres('integer'));
        $this->assertSame('bigint', TypeMapper::mapPostgres('bigint'));
        $this->assertSame('smallint', TypeMapper::mapPostgres('smallint'));
        $this->assertSame('integer', TypeMapper::mapPostgres('serial'));
        $this->assertSame('boolean', TypeMapper::mapPostgres('boolean'));
        $this->assertSame('decimal', TypeMapper::mapPostgres('numeric(10,2)'));
        $this->assertSame('float', TypeMapper::mapPostgres('double precision'));
    }

    public function testMapsPostgresStringsTemporalJsonAndUuid(): void
    {
        $this->assertSame('string', TypeMapper::mapPostgres('character varying(255)'));
        $this->assertSame('text', TypeMapper::mapPostgres('text'));
        $this->assertSame('timestamp', TypeMapper::mapPostgres('timestamp without time zone'));
        $this->assertSame('timestamp', TypeMapper::mapPostgres('timestamp with time zone'));
        $this->assertSame('time', TypeMapper::mapPostgres('time without time zone'));
        $this->assertSame('date', TypeMapper::mapPostgres('date'));
        $this->assertSame('interval', TypeMapper::mapPostgres('interval'));
        $this->assertSame('json', TypeMapper::mapPostgres('jsonb'));
        $this->assertSame('uuid', TypeMapper::mapPostgres('uuid'));
        $this->assertSame('binary', TypeMapper::mapPostgres('bytea'));
        $this->assertSame('json', TypeMapper::mapPostgres('integer[]'));
        $this->assertSame('unknown', TypeMapper::mapPostgres('geography'));
    }

    // ── Foreign keys ──────────────────────────────────────────

    public function testMalformedForeignKeyBecomesNull(): void
    {
        $payload = $this->generator->generate([
            'engine' => 'mysql',
            'tables' => [
                [
                    'name' => 't',
                    'columns' => [
                        ['name' => 'a', 'dataType' => 'int', 'nullable' => true, 'foreignKey' => ['table' => 'u']],
                        ['name' => 'b', 'dataType' => 'int', 'nullable' => true, 'foreignKey' => 'garbage'],
                    ],
                    'rowsApprox' => null,
                ],
            ],
        ]);

        $this->assertNull($payload[0]['columns'][0]['foreign_key']);
        $this->assertNull($payload[0]['columns'][1]['foreign_key']);
    }

    // ── Validation ────────────────────────────────────────────

    public function testMissingEngineThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->generator->generate(['tables' => []]);
    }

    public function testUnknownEngineThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->generator->generate(['engine' => 'oracle', 'tables' => []]);
    }

    public function testMissingTablesKeyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->generator->generate(['engine' => 'mysql', 'nope' => []]);
    }

    public function testTableWithoutColumnsThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->generator->generate([
            'engine' => 'mysql',
            'tables' => [['name' => 't']],
        ]);
    }

    public function testColumnWithoutNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->generator->generate([
            'engine' => 'mysql',
            'tables' => [
                [
                    'name' => 't',
                    'columns' => [['dataType' => 'int']],
                    'rowsApprox' => null,
                ],
            ],
        ]);
    }

    public function testColumnWithoutDataTypeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->generator->generate([
            'engine' => 'mysql',
            'tables' => [
                [
                    'name' => 't',
                    'columns' => [['name' => 'a']],
                    'rowsApprox' => null,
                ],
            ],
        ]);
    }

    // ── No I/O ────────────────────────────────────────────────

    public function testPackagePerformsNoIo(): void
    {
        $src = (string) file_get_contents(__DIR__ . '/../src/SchemaGenerator.php');
        $src .= (string) file_get_contents(__DIR__ . '/../src/RawSchemaValidator.php');
        $src .= (string) file_get_contents(__DIR__ . '/../src/TypeMapper.php');

        foreach (['file_get_contents', 'file_put_contents', 'fopen', 'curl_', 'PDO', 'mysqli', 'socket_'] as $needle) {
            $this->assertStringNotContainsString($needle, $src, "Found I/O usage: {$needle}");
        }
    }
}
