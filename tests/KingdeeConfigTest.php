<?php

namespace Shengya\Kingdee\Tests;

use PHPUnit\Framework\TestCase;
use Shengya\Kingdee\Config\KingdeeConfig;
use Shengya\Kingdee\Exceptions\ConfigurationException;

final class KingdeeConfigTest extends TestCase
{
    public function testItNormalizesTheBaseUrlAndAcceptsCompleteConfiguration(): void
    {
        $config = new KingdeeConfig($this->completeConfiguration());

        $config->assertUsable();

        $this->assertSame('https://kingdee.example.com/K3Cloud/', $config->baseUrl());
        $this->assertSame(1000, $config->queryLimit());
    }

    public function testItReportsMissingMappingsBeforeMakingARequest(): void
    {
        $config = new KingdeeConfig([]);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('缺少金蝶配置映射');

        $config->assertUsable();
    }

    /**
     * 返回不包含真实凭证的完整测试配置。
     */
    private function completeConfiguration(): array
    {
        return [
            'enabled' => true,
            'base_url' => 'https://kingdee.example.com/K3Cloud',
            'account_set' => 'test-account-set',
            'username' => 'test-user',
            'password' => 'test-password',
            'lcid' => 2052,
            'timeout' => 60,
            'connect_timeout' => 10,
            'retry' => ['max_attempts' => 2, 'delay_ms' => 300],
            'query' => ['limit' => 1000, 'start_row' => 0],
            'auth' => ['format' => 1, 'user_agent' => 'Tests', 'version' => '1.0'],
            'endpoints' => [
                'login' => 'login',
                'query' => 'query',
                'view' => 'view',
                'save' => 'save',
                'submit' => 'submit',
                'audit' => 'audit',
            ],
        ];
    }
}
