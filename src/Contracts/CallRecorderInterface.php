<?php

namespace Shengya\Kingdee\Contracts;

use Shengya\Kingdee\DTO\CallContext;
use Throwable;

/**
 * 与存储方式无关的调用记录接口，使用方可以写入日志、数据库或 APM 系统。
 */
interface CallRecorderInterface
{
    public function started(CallContext $context, array $request): void;

    public function succeeded(CallContext $context, array $request, array $response, int $durationMs): void;

    public function failed(CallContext $context, array $request, Throwable $exception, int $durationMs): void;
}
