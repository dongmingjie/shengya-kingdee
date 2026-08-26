<?php

namespace Shengya\Kingdee\DTO;

/**
 * 统一的结果包装对象，在保留原始响应的同时避免业务接口直接依赖金蝶响应结构。
 */
final class KingdeeResponse
{
    private $payload;
    private $traceId;

    public function __construct(array $payload, string $traceId)
    {
        $this->payload = $payload;
        $this->traceId = $traceId;
    }

    public function payload(): array { return $this->payload; }
    public function traceId(): string { return $this->traceId; }
    public function billId() { return $this->payload['Result']['Id'] ?? null; }
    public function billNo() { return $this->payload['Result']['Number'] ?? null; }
    public function rows(): array { return array_values($this->payload); }
}
