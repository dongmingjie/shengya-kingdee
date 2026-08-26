<?php

namespace Shengya\Kingdee\Support;

/**
 * 递归脱敏认证信息及常见的财务、个人敏感数据。
 */
final class SensitiveDataMasker
{
    private $keys;

    public function __construct(array $keys = [])
    {
        $this->keys = array_map('strtolower', $keys ?: [
            'password', 'passwd', 'token', 'secret', 'authorization', 'cookie',
            'bank_number', 'bankaccount', 'faccount', 'tax_number', 'phone', 'mobile',
        ]);
    }

    public function mask(array $payload): array
    {
        return $this->walk($payload);
    }

    private function walk(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), $this->keys, true)) {
                $payload[$key] = '***';
                continue;
            }

            if (is_array($value)) {
                $payload[$key] = $this->walk($value);
            }
        }

        return $payload;
    }
}
