<?php

namespace Shengya\Kingdee\Recorder;

use Psr\Log\LoggerInterface;
use Shengya\Kingdee\Contracts\CallRecorderInterface;
use Shengya\Kingdee\DTO\CallContext;
use Shengya\Kingdee\Exceptions\KingdeeException;
use Shengya\Kingdee\Support\SensitiveDataMasker;
use Throwable;

/**
 * 适用于 Laravel 及其他 PSR-3 项目的日志调用记录器。
 */
final class PsrLogCallRecorder implements CallRecorderInterface
{
    private $logger;
    private $masker;

    public function __construct(LoggerInterface $logger, SensitiveDataMasker $masker)
    {
        $this->logger = $logger;
        $this->masker = $masker;
    }

    public function started(CallContext $context, array $request): void
    {
        $this->logger->info('kingdee.call.started', $this->base($context) + [
            'request' => $this->masker->mask($request),
        ]);
    }

    public function succeeded(CallContext $context, array $request, array $response, int $durationMs): void
    {
        $this->logger->info('kingdee.call.succeeded', $this->base($context) + [
            'duration_ms' => $durationMs,
            'request' => $this->masker->mask($request),
            'response' => $this->masker->mask($response),
        ]);
    }

    public function failed(CallContext $context, array $request, Throwable $exception, int $durationMs): void
    {
        $this->logger->error('kingdee.call.failed', $this->base($context) + [
            'duration_ms' => $durationMs,
            'request' => $this->masker->mask($request),
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'retryable' => $exception instanceof KingdeeException ? $exception->retryable() : false,
        ]);
    }

    private function base(CallContext $context): array
    {
        return ['kingdee' => $context->toArray()];
    }
}
