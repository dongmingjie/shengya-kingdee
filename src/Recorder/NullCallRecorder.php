<?php

namespace Shengya\Kingdee\Recorder;

use Shengya\Kingdee\Contracts\CallRecorderInterface;
use Shengya\Kingdee\DTO\CallContext;
use Throwable;

/**
 * 空实现调用记录器，适用于非框架项目和隔离测试。
 */
final class NullCallRecorder implements CallRecorderInterface
{
    public function started(CallContext $context, array $request): void {}
    public function succeeded(CallContext $context, array $request, array $response, int $durationMs): void {}
    public function failed(CallContext $context, array $request, Throwable $exception, int $durationMs): void {}
}
