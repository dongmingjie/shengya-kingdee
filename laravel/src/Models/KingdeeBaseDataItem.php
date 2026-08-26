<?php

namespace Shengya\KingdeeLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Shengya\Kingdee\Exceptions\ConfigurationException;

/**
 * 金蝶基础资料通用模型，查询前必须通过 forType() 选择独立表。
 */
class KingdeeBaseDataItem extends Model
{
    private $dataType;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'source_payload' => 'array',
        'synced_at' => 'datetime',
    ];

    public function getTable()
    {
        if ($this->dataType === null) {
            throw new ConfigurationException('查询金蝶基础资料前必须调用 KingdeeBaseDataItem::forType() 指定类型');
        }

        return $this->storageMapping('tables.' . $this->dataType);
    }

    /**
     * 为通用模型选择一类基础资料的独立表。
     */
    public static function forType(string $type)
    {
        $model = new static();
        $model->dataType = $type;

        return $model->newQuery();
    }

    /**
     * Eloquent 在水合查询结果时会复制模型，这里同步保留已选择的资料类型。
     */
    public function newInstance($attributes = [], $exists = false)
    {
        $model = parent::newInstance($attributes, $exists);
        $model->dataType = $this->dataType;

        return $model;
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
