<?php

namespace Shengya\KingdeeLaravel\Recorders;

use Illuminate\Database\ConnectionInterface;
use Psr\Log\LoggerInterface;
use Shengya\Kingdee\Contracts\CallRecorderInterface;
use Shengya\Kingdee\DTO\CallContext;
use Shengya\Kingdee\Exceptions\KingdeeException;
use Shengya\Kingdee\Support\SensitiveDataMasker;
use Throwable;

/**
 * 将调用记录和异常信息写入组件自有数据表。
 * 数据库记录失败时降级写入 PSR 日志，不会覆盖或隐藏接口调用结果。
 */
final class DatabaseCallRecorder implements CallRecorderInterface
{
    private $connection;
    private $logger;
    private $masker;
    private $table;
    private $accountSet;

    public function __construct(ConnectionInterface $connection, LoggerInterface $logger, SensitiveDataMasker $masker, string $table, string $accountSet)
    {
        $this->connection = $connection;
        $this->logger = $logger;
        $this->masker = $masker;
        $this->table = $table;
        $this->accountSet = $accountSet;
    }

    public function started(CallContext $context, array $request): void
    {
        $this->safely(function () use ($context, $request) {
            $data = $context->toArray();
            $this->connection->table($this->table)->insert([
                'trace_id' => $data['trace_id'],
                'request_id' => $data['request_id'],
                'account_set' => $this->accountSet,
                'operation' => $data['operation'],
                'form_id' => $data['form_id'],
                'business_type' => $data['business_type'],
                'business_id' => $data['business_id'],
                'idempotency_key' => $data['idempotency_key'],
                'attempt' => $data['attempt'],
                'status' => 'running',
                'request_payload' => $this->encode($this->masker->mask($request)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, $context);
    }

    public function succeeded(CallContext $context, array $request, array $response, int $durationMs): void
    {
        $this->safely(function () use ($context, $response, $durationMs) {
            $this->connection->table($this->table)->where('request_id', $context->requestId())->update([
                'status' => 'succeeded',
                'duration_ms' => $durationMs,
                'response_payload' => $this->encode($this->masker->mask($response)),
                'kingdee_code' => $this->kingdeeCode($response),
                'updated_at' => now(),
            ]);
        }, $context);
    }

    public function failed(CallContext $context, array $request, Throwable $exception, int $durationMs): void
    {
        $this->safely(function () use ($context, $exception, $durationMs) {
            $details = $exception instanceof KingdeeException ? $exception->details() : [];
            $this->connection->table($this->table)->where('request_id', $context->requestId())->update([
                'status' => 'failed',
                'duration_ms' => $durationMs,
                'response_payload' => $details ? $this->encode($this->masker->mask($details)) : null,
                'kingdee_code' => $this->kingdeeCode($details),
                'exception_class' => get_class($exception),
                'exception_message' => $exception->getMessage(),
                'retryable' => $exception instanceof KingdeeException && $exception->retryable(),
                'updated_at' => now(),
            ]);
        }, $context);
    }

    private function safely(callable $callback, CallContext $context): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            $this->logger->error('kingdee.call_record.persistence_failed', [
                'trace_id' => $context->traceId(),
                'request_id' => $context->requestId(),
                'exception_class' => get_class($exception),
                'exception_message' => $exception->getMessage(),
            ]);
        }
    }

    private function encode(array $payload): string
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded === false ? '{}' : $encoded;
    }

    private function kingdeeCode(array $payload): ?string
    {
        $code = $payload['Result']['ResponseStatus']['ErrorCode']
            ?? $payload['errors'][0]['Code']
            ?? $payload['errors'][0]['ErrorCode']
            ?? null;

        return $code === null ? null : (string) $code;
    }
}
