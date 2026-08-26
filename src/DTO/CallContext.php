<?php

namespace Shengya\Kingdee\DTO;

/**
 * 不依赖具体业务模型的跨系统调用链上下文。
 */
final class CallContext
{
    private $traceId;
    private $requestId;
    private $operation;
    private $formId;
    private $businessType;
    private $businessId;
    private $idempotencyKey;
    private $retrySafe;
    private $attempt = 1;
    private $metadata;

    public function __construct(
        string $operation,
        string $formId,
        ?string $businessType = null,
        ?string $businessId = null,
        ?string $idempotencyKey = null,
        bool $retrySafe = false,
        array $metadata = []
    ) {
        $this->traceId = self::uuid();
        $this->requestId = self::uuid();
        $this->operation = $operation;
        $this->formId = $formId;
        $this->businessType = $businessType;
        $this->businessId = $businessId;
        $this->idempotencyKey = $idempotencyKey;
        $this->retrySafe = $retrySafe;
        $this->metadata = $metadata;
    }

    public static function create(string $operation, string $formId): self
    {
        return new self($operation, $formId, null, null, null, in_array($operation, ['query', 'view'], true));
    }

    public static function forBusiness(string $operation, string $formId, string $businessType, $businessId, ?string $idempotencyKey = null, bool $retrySafe = false, array $metadata = []): self
    {
        return new self($operation, $formId, $businessType, (string) $businessId, $idempotencyKey, $retrySafe, $metadata);
    }

    public function nextAttempt(int $attempt): void
    {
        $this->attempt = $attempt;
        $this->requestId = self::uuid();
    }

    public function toArray(): array
    {
        return [
            'trace_id' => $this->traceId,
            'request_id' => $this->requestId,
            'operation' => $this->operation,
            'form_id' => $this->formId,
            'business_type' => $this->businessType,
            'business_id' => $this->businessId,
            'idempotency_key' => $this->idempotencyKey,
            'attempt' => $this->attempt,
            'metadata' => $this->metadata,
        ];
    }

    public function retrySafe(): bool { return $this->retrySafe; }
    public function traceId(): string { return $this->traceId; }
    public function requestId(): string { return $this->requestId; }

    private static function uuid(): string
    {
        $hex = bin2hex(random_bytes(16));
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    }
}
