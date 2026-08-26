<?php

namespace Shengya\KingdeeLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Shengya\Kingdee\Exceptions\ConfigurationException;

/**
 * 记录一次金蝶基础资料同步过程和结果统计。
 */
class KingdeeBaseDataSyncBatch extends Model
{
    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function getTable()
    {
        return $this->storageMapping('sync_batches_table');
    }

    public function getConnectionName()
    {
        return $this->storageMapping('connection');
    }

    /**
     * 读取宿主项目的基础资料存储配置。
     */
    private function storageMapping(string $key): string
    {
        $value = config('kingdee.base_data_storage.' . $key);
        if (!is_string($value) || trim($value) === '') {
            throw new ConfigurationException('缺少金蝶基础资料存储配置：' . $key);
        }

        return $value;
    }
}
