<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Client\Tests;

use Clearsoft\EasySQL\Client\Models\TokenResponse;
use PHPUnit\Framework\TestCase;

/**
 * The generated Models are part of the package's public surface: consumers can
 * hydrate a response array into a typed object with fromArray().
 */
class ModelsTest extends TestCase
{
    public function testHydratesFromArray(): void
    {
        $model = TokenResponse::fromArray([
            'access_token' => 'abc123',
            'refresh_token' => 'ref456',
            'token_type' => 'bearer',
        ]);

        $this->assertSame('abc123', $model->access_token);
        $this->assertSame('ref456', $model->refresh_token);
        $this->assertSame('bearer', $model->token_type);
    }

    public function testMissingKeysFallBackToTypeDefaults(): void
    {
        $model = TokenResponse::fromArray([]);

        $this->assertSame('', $model->access_token);
        $this->assertSame('', $model->refresh_token);
        $this->assertSame('', $model->token_type);
    }
}
