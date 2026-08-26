<?php

namespace Shengya\Kingdee\Documents;

/**
 * 金蝶业务单据 Payload 的公共组装能力。
 */
abstract class AbstractDocumentPayload
{
    /**
     * 生成金蝶基础资料引用；未选择时返回 null，由上层省略该字段。
     */
    protected static function baseData($number, string $numberKey = 'FNumber'): ?array
    {
        if ($number === null || trim((string) $number) === '') {
            return null;
        }

        return [$numberKey => (string) $number];
    }

    /**
     * 删除未选择的可空字段，同时保留 0、false 和非空数组。
     */
    protected static function removeEmpty(array $values): array
    {
        return array_filter($values, static function ($value): bool {
            return $value !== null && $value !== '' && $value !== [];
        });
    }

    /**
     * 生成金蝶 Save 动作共用的协议参数。
     */
    protected static function saveData(array $model, array $needReturnFields): array
    {
        return [
            'Creator' => '',
            'NeedUpDateFields' => [],
            'NeedReturnFields' => $needReturnFields,
            'IsDeleteEntry' => true,
            'IsVerifyBaseDataField' => false,
            'IsEntryBatchFill' => true,
            'InterationFlags' => '',
            'IsAutoSubmitAndAudit' => false,
            'Model' => $model,
        ];
    }
}
