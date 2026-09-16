<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Common\Tests;

use Clearsoft\EasySQL\Common\SqlValidator;
use PHPUnit\Framework\TestCase;

class SqlValidatorTest extends TestCase
{
    public function testAcceptsSelectAndCte(): void
    {
        $this->assertTrue(SqlValidator::validateSelectOnly('SELECT * FROM users LIMIT 10')['ok']);
        $this->assertTrue(SqlValidator::validateSelectOnly('WITH cte AS (SELECT 1) SELECT * FROM cte')['ok']);
        $this->assertTrue(SqlValidator::validateSelectOnly('EXPLAIN SELECT 1')['ok']);
        $this->assertTrue(SqlValidator::validateSelectOnly('SHOW TABLES')['ok']);
    }

    public function testRejectsEmpty(): void
    {
        $result = SqlValidator::validateSelectOnly('   ');

        $this->assertFalse($result['ok']);
        $this->assertSame('Empty SQL', $result['reason']);
    }

    public function testRejectsMutations(): void
    {
        foreach ([
            'DELETE FROM users',
            'DROP TABLE users',
            'TRUNCATE TABLE users',
            'ALTER TABLE users ADD x INT',
            'CREATE TABLE t (id INT)',
            'GRANT ALL ON db.* TO u',
            'INSERT INTO users VALUES (1)',
            'UPDATE users SET x = 1',
            'SELECT * INTO backup FROM users',
            'SELECT load_file("/etc/passwd")',
        ] as $sql) {
            $this->assertFalse(SqlValidator::validateSelectOnly($sql)['ok'], $sql);
        }
    }

    public function testRejectsStackedStatements(): void
    {
        $this->assertFalse(SqlValidator::validateSelectOnly('SELECT 1; DELETE FROM users')['ok']);
        $this->assertFalse(SqlValidator::validateSelectOnly('SELECT 1; SELECT 2')['ok']);
    }

    public function testAllowsTrailingSemicolon(): void
    {
        $this->assertTrue(SqlValidator::validateSelectOnly('SELECT 1;')['ok']);
    }

    public function testIgnoresKeywordsInsideLiteralsAndComments(): void
    {
        $this->assertTrue(SqlValidator::validateSelectOnly("SELECT * FROM users WHERE note = 'please delete me'")['ok']);
        $this->assertTrue(SqlValidator::validateSelectOnly("SELECT * FROM `drop`")['ok']);
        $this->assertTrue(SqlValidator::validateSelectOnly("SELECT 1 -- drop table\n")['ok']);
    }

    public function testRejectsStatementsNotStartingWithSelect(): void
    {
        $this->assertFalse(SqlValidator::validateSelectOnly('table users')['ok']);
        $this->assertFalse(SqlValidator::validateSelectOnly('BEGIN')['ok']);
    }
}
