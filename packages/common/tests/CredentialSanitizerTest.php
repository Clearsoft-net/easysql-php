<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Common\Tests;

use Clearsoft\EasySQL\Common\CredentialSanitizer;
use PHPUnit\Framework\TestCase;

class CredentialSanitizerTest extends TestCase
{
    public function testRedactsUserInfoInConnectionUri(): void
    {
        $message = 'SQLSTATE: connection to postgres://secret_user:s3cr3t@db.internal:5432 failed';

        $sanitized = CredentialSanitizer::sanitize($message);

        $this->assertStringNotContainsString('secret_user', $sanitized);
        $this->assertStringNotContainsString('s3cr3t', $sanitized);
        $this->assertStringContainsString('//***@db.internal', $sanitized);
    }

    public function testRedactsPasswordAssignments(): void
    {
        $message = 'connection failed: host=db user=app password=s3cr3t-p4ss dbname=shop';

        $sanitized = CredentialSanitizer::sanitize($message);

        $this->assertStringNotContainsString('s3cr3t-p4ss', $sanitized);
        $this->assertStringContainsString('password=***', $sanitized);
    }

    public function testRedactsPwdAlias(): void
    {
        $sanitized = CredentialSanitizer::sanitize('pwd=hunter2 port=5432');

        $this->assertStringNotContainsString('hunter2', $sanitized);
        $this->assertStringContainsString('pwd=***', $sanitized);
    }

    public function testLeavesCleanMessagesUntouched(): void
    {
        $message = 'SQLSTATE[42S22]: Column not found: 1054 Unknown column';

        $this->assertSame($message, CredentialSanitizer::sanitize($message));
    }
}
