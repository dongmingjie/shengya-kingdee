<?php

namespace Shengya\Kingdee;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Shengya\Kingdee\Config\KingdeeConfig;
use Shengya\Kingdee\Contracts\CallRecorderInterface;
use Shengya\Kingdee\Contracts\KingdeeClientInterface;
use Shengya\Kingdee\DTO\CallContext;
use Shengya\Kingdee\DTO\KingdeeResponse;
use Shengya\Kingdee\Exceptions\AuthenticationException;
use Shengya\Kingdee\Exceptions\KingdeeBusinessException;
use Shengya\Kingdee\Exceptions\KingdeeException;
use Shengya\Kingdee\Exceptions\TimeoutException;
use Shengya\Kingdee\Exceptions\TransportException;

/**
 * 与框架无关的金蝶 WebAPI 客户端，统一处理调用链记录和异常。
 */
final class KingdeeClient implements KingdeeClientInterface
{
    private $config;
    private $recorder;
    private $http;
    private $authenticated = false;

    public function __construct(KingdeeConfig $config, CallRecorderInterface $recorder, ?Client $http = null)
    {
        $this->config = $config;
        $this->recorder = $recorder;
        $this->http = $http ?: new Client([
            'base_uri' => $config->baseUrl(),
            'timeout' => $config->timeout(),
            'connect_timeout' => $config->connectTimeout(),
            'cookies' => new CookieJar(),
            'http_errors' => false,
        ]);
    }

    public function query(string $formId, array $fieldKeys, string $filter = '', ?int $limit = null, ?int $startRow = null, ?CallContext $context = null): KingdeeResponse
    {
        return $this->execute('query', $formId, [
            'data' => [
                'FormId' => $formId,
                'FieldKeys' => implode(',', $fieldKeys),
                'FilterString' => $filter,
                'Limit' => $limit === null ? $this->config->queryLimit() : $limit,
                'StartRow' => $startRow === null ? $this->config->queryStartRow() : $startRow,
                'TopRowCount' => 0,
            ],
        ], $context);
    }

    public function view(string $formId, array $data, ?CallContext $context = null): KingdeeResponse
    {
        return $this->execute('view', $formId, ['formid' => $formId, 'data' => $data], $context);
    }

    public function save(string $formId, array $data, ?CallContext $context = null): KingdeeResponse
    {
        return $this->execute('save', $formId, ['formid' => $formId, 'data' => $data], $context);
    }

    public function submit(string $formId, array $numbers, ?CallContext $context = null): KingdeeResponse
    {
        return $this->execute('submit', $formId, ['formid' => $formId, 'data' => ['Numbers' => array_values($numbers)]], $context);
    }

    public function audit(string $formId, array $numbers, ?CallContext $context = null): KingdeeResponse
    {
        return $this->execute('audit', $formId, ['formid' => $formId, 'data' => ['Numbers' => array_values($numbers)]], $context);
    }

    private function execute(string $operation, string $formId, array $payload, ?CallContext $context): KingdeeResponse
    {
        $this->config->assertUsable();
        $this->authenticate();
        $context = $context ?: CallContext::create($operation, $formId);
        $attempt = 1;

        while (true) {
            $context->nextAttempt($attempt);
            $startedAt = microtime(true);
            $this->recorder->started($context, $payload);

            try {
                $decoded = $this->post($this->config->endpoint($operation), $payload, $context);
                $this->assertBusinessSuccess($decoded, $context);
                $duration = (int) round((microtime(true) - $startedAt) * 1000);
                $this->recorder->succeeded($context, $payload, $decoded, $duration);

                return new KingdeeResponse($decoded, $context->traceId());
            } catch (KingdeeException $exception) {
                $duration = (int) round((microtime(true) - $startedAt) * 1000);
                $this->recorder->failed($context, $payload, $exception, $duration);

                if (!$this->shouldRetry($exception, $context, $attempt)) {
                    throw $exception;
                }

                usleep($this->config->retryDelayMs() * $attempt * 1000);
                $attempt++;
            }
        }
    }

    private function authenticate(): void
    {
        if ($this->authenticated) {
            return;
        }

        $context = CallContext::create('login', 'AUTH');
        $payload = [
            'format' => $this->config->authFormat(),
            'useragent' => $this->config->authUserAgent(),
            'rid' => $context->traceId(),
            'parameters' => [$this->config->accountSet(), $this->config->username(), $this->config->password(), $this->config->lcid()],
            'timestamp' => date('Y-m-d'),
            'v' => $this->config->authVersion(),
        ];
        $startedAt = microtime(true);
        $this->recorder->started($context, ['operation' => 'login', 'parameters' => ['***']]);

        try {
            $decoded = $this->post($this->config->endpoint('login'), $payload, $context);
            if ((int) ($decoded['LoginResultType'] ?? 0) !== 1) {
                throw new AuthenticationException('Kingdee authentication failed.', false, $context->traceId(), ['response' => $decoded]);
            }
            $this->authenticated = true;
            $this->recorder->succeeded($context, ['operation' => 'login'], ['LoginResultType' => 1], (int) round((microtime(true) - $startedAt) * 1000));
        } catch (KingdeeException $exception) {
            $this->recorder->failed($context, ['operation' => 'login'], $exception, (int) round((microtime(true) - $startedAt) * 1000));
            throw $exception;
        }
    }

    private function post(string $endpoint, array $payload, CallContext $context): array
    {
        try {
            $response = $this->http->post($endpoint, [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (ConnectException $exception) {
            $message = strtolower($exception->getMessage());
            if (strpos($message, 'timed out') !== false || strpos($message, 'timeout') !== false) {
                throw new TimeoutException('Kingdee request timed out.', true, $context->traceId(), [], 0, $exception);
            }
            throw new TransportException('Unable to connect to Kingdee.', true, $context->traceId(), [], 0, $exception);
        } catch (GuzzleException $exception) {
            throw new TransportException('Kingdee transport failed.', true, $context->traceId(), [], 0, $exception);
        }

        if ($response->getStatusCode() >= 400) {
            throw new TransportException('Kingdee returned HTTP ' . $response->getStatusCode() . '.', $response->getStatusCode() >= 500, $context->traceId());
        }

        $decoded = json_decode((string) $response->getBody(), true);
        if (!is_array($decoded)) {
            throw new TransportException('Kingdee returned an invalid JSON response.', false, $context->traceId());
        }

        return $decoded;
    }

    private function assertBusinessSuccess(array $payload, CallContext $context): void
    {
        $status = $payload['Result']['ResponseStatus'] ?? null;
        if (is_array($status) && array_key_exists('IsSuccess', $status) && !$status['IsSuccess']) {
            $errors = $status['Errors'] ?? [];
            $message = $errors[0]['Message'] ?? 'Kingdee rejected the request.';
            throw new KingdeeBusinessException((string) $message, false, $context->traceId(), ['errors' => $errors]);
        }
    }

    private function shouldRetry(KingdeeException $exception, CallContext $context, int $attempt): bool
    {
        return $exception->retryable()
            && $context->retrySafe()
            && $attempt < $this->config->maxAttempts();
    }
}
