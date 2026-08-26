<?php

namespace Shengya\KingdeeLaravel;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Shengya\Kingdee\Config\KingdeeConfig;
use Shengya\Kingdee\Contracts\KingdeeBaseDataInterface;
use Shengya\Kingdee\Contracts\CallRecorderInterface;
use Shengya\Kingdee\Contracts\KingdeeClientInterface;
use Shengya\Kingdee\Contracts\KingdeeOperationsInterface;
use Shengya\Kingdee\Exceptions\ConfigurationException;
use Shengya\Kingdee\KingdeeClient;
use Shengya\Kingdee\KingdeeBaseData;
use Shengya\Kingdee\KingdeeOperations;
use Shengya\Kingdee\Support\SensitiveDataMasker;
use Shengya\KingdeeLaravel\Console\SyncKingdeeBaseDataCommand;
use Shengya\KingdeeLaravel\Recorders\DatabaseCallRecorder;
use Shengya\KingdeeLaravel\Services\KingdeeBaseDataSyncService;

/**
 * Laravel 适配层：读取宿主配置，注册 SDK、调用记录器和基础资料同步服务。
 */
class KingdeeServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(KingdeeConfig::class, function () {
            return new KingdeeConfig((array) config('kingdee.sdk'));
        });

        $this->app->singleton(SensitiveDataMasker::class, function () {
            $keys = config('kingdee.sensitive_keys');
            if (!is_array($keys) || !$keys) {
                throw new ConfigurationException('缺少金蝶敏感字段配置映射：kingdee.sensitive_keys');
            }

            return new SensitiveDataMasker($keys);
        });

        $this->app->singleton(CallRecorderInterface::class, function ($app) {
            $records = $this->recordsMapping();
            $connection = DB::connection($records['connection']);
            $logger = Log::channel($records['fallback_log_channel']);

            return new DatabaseCallRecorder(
                $connection,
                $logger,
                $app->make(SensitiveDataMasker::class),
                $records['table'],
                (string) config('kingdee.sdk.account_set')
            );
        });

        $this->app->singleton(KingdeeClientInterface::class, function ($app) {
            return new KingdeeClient($app->make(KingdeeConfig::class), $app->make(CallRecorderInterface::class));
        });

        $this->app->alias(KingdeeClientInterface::class, KingdeeClient::class);

        $this->app->singleton(KingdeeOperationsInterface::class, function ($app) {
            // 独立能力配置由宿主项目管理，组件内部不提供跨接口自动编排。
            return new KingdeeOperations(
                $app->make(KingdeeClientInterface::class),
                (array) config('kingdee.operations')
            );
        });

        $this->app->alias(KingdeeOperationsInterface::class, KingdeeOperations::class);

        $this->app->singleton(KingdeeBaseDataInterface::class, function ($app) {
            // SDK 只负责查询金蝶，Laravel 适配层按资料类型分别落库。
            return new KingdeeBaseData(
                $app->make(KingdeeClientInterface::class),
                (array) config('kingdee.base_data')
            );
        });

        $this->app->alias(KingdeeBaseDataInterface::class, KingdeeBaseData::class);

        $this->app->singleton(KingdeeBaseDataSyncService::class, function ($app) {
            $storage = $this->baseDataStorageMapping();

            return new KingdeeBaseDataSyncService(
                $app->make(KingdeeBaseDataInterface::class),
                DB::connection($storage['connection']),
                (string) config('kingdee.sdk.account_set'),
                $storage['tables'],
                $storage['sync_batches_table'],
                (int) $storage['page_size']
            );
        });
    }

    public function boot()
    {
        // 迁移必须先发布到宿主项目审阅，组件不自动加载或执行迁移。
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'kingdee-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([SyncKingdeeBaseDataCommand::class]);
        }
    }

    /**
     * 读取并校验宿主项目提供的调用记录配置映射。
     */
    private function recordsMapping(): array
    {
        $records = config('kingdee.records');
        if (!is_array($records)) {
            throw new ConfigurationException('缺少金蝶调用记录配置映射：kingdee.records');
        }

        foreach (['connection', 'table', 'fallback_log_channel'] as $key) {
            if (!array_key_exists($key, $records) || !is_string($records[$key]) || trim($records[$key]) === '') {
                throw new ConfigurationException('金蝶调用记录配置映射不能为空：kingdee.records.' . $key);
            }
        }

        return $records;
    }

    /**
     * 读取并校验宿主项目提供的基础资料存储配置。
     */
    private function baseDataStorageMapping(): array
    {
        $storage = config('kingdee.base_data_storage');
        if (!is_array($storage)) {
            throw new ConfigurationException('缺少金蝶基础资料存储配置：kingdee.base_data_storage');
        }

        foreach (['connection', 'sync_batches_table'] as $key) {
            if (!isset($storage[$key]) || !is_string($storage[$key]) || trim($storage[$key]) === '') {
                throw new ConfigurationException('金蝶基础资料存储配置不能为空：kingdee.base_data_storage.' . $key);
            }
        }

        $requiredTypes = [
            'organizations', 'customers', 'materials', 'accounts', 'departments', 'positions',
            'salesmen', 'employees', 'staff_assignments', 'payment_purposes', 'settlement_types',
            'bank_accounts', 'cash_accounts', 'projects',
        ];
        if (!isset($storage['tables']) || !is_array($storage['tables'])) {
            throw new ConfigurationException('缺少金蝶基础资料分表配置：kingdee.base_data_storage.tables');
        }
        foreach ($requiredTypes as $type) {
            if (!isset($storage['tables'][$type]) || !is_string($storage['tables'][$type]) || trim($storage['tables'][$type]) === '') {
                throw new ConfigurationException('金蝶基础资料表配置不能为空：kingdee.base_data_storage.tables.' . $type);
            }
        }

        if (!isset($storage['page_size']) || (int) $storage['page_size'] <= 0) {
            throw new ConfigurationException('金蝶基础资料同步每页数量必须大于 0。');
        }

        return $storage;
    }
}
