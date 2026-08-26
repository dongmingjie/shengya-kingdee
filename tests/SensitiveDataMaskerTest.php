<?php

namespace Shengya\Kingdee\Tests;

use PHPUnit\Framework\TestCase;
use Shengya\Kingdee\Support\SensitiveDataMasker;

final class SensitiveDataMaskerTest extends TestCase
{
    public function testItMasksConfiguredKeysRecursivelyAndIgnoresCase(): void
    {
        $masker = new SensitiveDataMasker(['password', 'token']);

        $masked = $masker->mask([
            'Password' => 'top-secret',
            'nested' => ['TOKEN' => 'access-token', 'name' => 'customer'],
        ]);

        $this->assertSame('***', $masked['Password']);
        $this->assertSame('***', $masked['nested']['TOKEN']);
        $this->assertSame('customer', $masked['nested']['name']);
    }
}
