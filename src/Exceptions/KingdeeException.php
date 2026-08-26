<?php

namespace Shengya\Kingdee\Exceptions;

use RuntimeException;

/**
 * 金蝶异常基类，统一携带重试标记和调用链信息。
 */
class KingdeeException extends RuntimeException
{
    private $retryable;
    private $traceId;
    private $details;

    public function __construct(string $message, bool $retryable = false, ?string $traceId = null, array $details = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->retryable = $retryable;
        $this->traceId = $traceId;
        $this->details = $details;
    }

    public function retryable(): bool { return $this->retryable; }
    public function traceId(): ?string { return $this->traceId; }
    public function details(): array { return $this->details; }
}
