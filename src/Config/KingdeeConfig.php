<?php

namespace Shengya\Kingdee\Config;

use Shengya\Kingdee\Exceptions\ConfigurationException;

/**
 * 由宿主项目传入的不可变运行配置。
 */
final class KingdeeConfig
{
    private $missingMappings = [];
    private $enabled;
    private $baseUrl;
    private $accountSet;
    private $username;
    private $password;
    private $lcid;
    private $timeout;
    private $connectTimeout;
    private $maxAttempts;
    private $retryDelayMs;
    private $queryLimit;
    private $queryStartRow;
    private $authFormat;
    private $authUserAgent;
    private $authVersion;
    private $endpoints;

    public function __construct(array $config)
    {
        // SDK 只读取宿主传入的映射，不在组件内部补任何默认值。
        $this->enabled = (bool) $this->mapping($config, 'enabled');
        $this->baseUrl = rtrim((string) $this->mapping($config, 'base_url'), '/') . '/';
        $this->accountSet = (string) $this->mapping($config, 'account_set');
        $this->username = (string) $this->mapping($config, 'username');
        $this->password = (string) $this->mapping($config, 'password');
        $this->lcid = (int) $this->mapping($config, 'lcid');
        $this->timeout = (float) $this->mapping($config, 'timeout');
        $this->connectTimeout = (float) $this->mapping($config, 'connect_timeout');
        $this->maxAttempts = (int) $this->mapping($config, 'retry.max_attempts');
        $this->retryDelayMs = (int) $this->mapping($config, 'retry.delay_ms');
        $this->queryLimit = (int) $this->mapping($config, 'query.limit');
        $this->queryStartRow = (int) $this->mapping($config, 'query.start_row');
        $this->authFormat = (int) $this->mapping($config, 'auth.format');
        $this->authUserAgent = (string) $this->mapping($config, 'auth.user_agent');
        $this->authVersion = (string) $this->mapping($config, 'auth.version');
        $this->endpoints = [];

        foreach (['login', 'query', 'view', 'save', 'submit', 'audit'] as $operation) {
            $this->endpoints[$operation] = (string) $this->mapping($config, 'endpoints.' . $operation);
        }
    }

    /**
     * 延迟校验配置，使本地关闭金蝶功能时 Laravel 仍可正常启动。
     */
    public function assertUsable(): void
    {
        if ($this->missingMappings) {
            throw new ConfigurationException('缺少金蝶配置映射：' . implode('、', $this->missingMappings));
        }

        if (!$this->enabled) {
            throw new ConfigurationException('宿主项目未启用金蝶集成。');
        }

        foreach (['baseUrl', 'accountSet', 'username', 'password'] as $property) {
            if ($this->{$property} === '' || $this->{$property} === '/') {
                throw new ConfigurationException(sprintf('金蝶配置映射不能为空：%s', $property));
            }
        }

        if ($this->lcid <= 0 || $this->timeout <= 0 || $this->connectTimeout <= 0) {
            throw new ConfigurationException('金蝶语言、请求超时和连接超时配置必须大于 0。');
        }

        if ($this->authFormat <= 0 || trim($this->authUserAgent) === '' || trim($this->authVersion) === '') {
            throw new ConfigurationException('金蝶认证协议配置映射不合法。');
        }

        if ($this->maxAttempts <= 0 || $this->retryDelayMs < 0 || $this->queryLimit <= 0 || $this->queryStartRow < 0) {
            throw new ConfigurationException('金蝶重试和查询分页配置不合法。');
        }

        foreach ($this->endpoints as $operation => $endpoint) {
            if (trim($endpoint) === '') {
                throw new ConfigurationException('金蝶接口地址映射不能为空：endpoints.' . $operation);
            }
        }
    }

    public function baseUrl(): string { return $this->baseUrl; }
    public function accountSet(): string { return $this->accountSet; }
    public function username(): string { return $this->username; }
    public function password(): string { return $this->password; }
    public function lcid(): int { return $this->lcid; }
    public function timeout(): float { return $this->timeout; }
    public function connectTimeout(): float { return $this->connectTimeout; }
    public function maxAttempts(): int { return $this->maxAttempts; }
    public function retryDelayMs(): int { return $this->retryDelayMs; }
    public function queryLimit(): int { return $this->queryLimit; }
    public function queryStartRow(): int { return $this->queryStartRow; }
    public function authFormat(): int { return $this->authFormat; }
    public function authUserAgent(): string { return $this->authUserAgent; }
    public function authVersion(): string { return $this->authVersion; }

    public function endpoint(string $operation): string
    {
        if (!array_key_exists($operation, $this->endpoints) || $this->endpoints[$operation] === '') {
            throw new ConfigurationException('未配置金蝶接口地址映射：' . $operation);
        }

        return $this->endpoints[$operation];
    }

    /**
     * 按点分路径读取宿主映射，并统一收集缺失项供首次调用时校验。
     */
    private function mapping(array $config, string $path)
    {
        $value = $config;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value) || $value[$segment] === null) {
                $this->missingMappings[] = $path;
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
