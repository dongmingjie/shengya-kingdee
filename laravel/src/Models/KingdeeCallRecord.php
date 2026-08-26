<?php

namespace Shengya\KingdeeLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Shengya\Kingdee\Exceptions\ConfigurationException;

/**
 * 由组件维护的调用记录模型，业务项目不应在此添加领域逻辑。
 */
class KingdeeCallRecord extends Model
{
    protected $guarded = [];

    protected $casts = [
        'attempt' => 'integer',
        'duration_ms' => 'integer',
        'http_status' => 'integer',
        'retryable' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function getTable()
    {
        $table = config('kingdee.records.table');
        if (!is_string($table) || trim($table) === '') {
            throw new ConfigurationException('缺少金蝶调用记录表映射：kingdee.records.table');
        }

        return $table;
    }

    public function getConnectionName()
    {
        $connection = config('kingdee.records.connection');
        if (!is_string($connection) || trim($connection) === '') {
            throw new ConfigurationException('缺少金蝶调用记录数据库映射：kingdee.records.connection');
        }

        return $connection;
    }
}
