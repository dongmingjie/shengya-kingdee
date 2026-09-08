<?php

namespace Shengya\Kingdee\Documents;

use InvalidArgumentException;

/**
 * 组装金蝶单据转换时使用的来源单据关联结构。
 */
final class SourceRelationPayload extends AbstractDocumentPayload
{
    /**
     * 组装“开票/应收单下推收款单”的来源关联。
     */
    public static function receiptFromInvoice(array $source, array $mapping): array
    {
        self::requireKeys($source, [
            'bill_id', 'bill_number', 'entry_id', 'total_amount', 'plan_amount', 'current_amount',
        ]);

        $link = [
            'FRECEIVEBILLSRCENTRY_Link_FRuleId' => self::mapping($mapping, 'convert_rule_id'),
            'FRECEIVEBILLSRCENTRY_Link_FSBillId' => $source['bill_id'],
            'FRECEIVEBILLSRCENTRY_Link_FSTableName' => self::mapping($mapping, 'source_table'),
            'FRECEIVEBILLSRCENTRY_Link_FSId' => $source['entry_id'],
            'FRECEIVEBILLSRCENTRY_Link_FREALRECAMOUNTOLD' => $source['total_amount'],
            'FRECEIVEBILLSRCENTRY_Link_FREALRECAMOUNT' => $source['current_amount'],
        ];

        return [
            'FSRCBILLTYPEID' => self::mapping($mapping, 'source_form_id'),
            'FSRCBILLNO' => $source['bill_number'],
            // 金蝶收款源单头的两个字段命名容易误导：BILLID 实际接收计划分录 ID，ROWID 接收应收单 ID。
            'FSRCBILLID' => $source['entry_id'],
            'FSRCROWID' => $source['bill_id'],
            'FAFTTAXTOTALAMOUNT' => $source['total_amount'],
            'FPLANRECAMOUNT' => $source['plan_amount'],
            'FREALRECAMOUNT' => $source['current_amount'],
            'FRECEIVEBILLSRCENTRY_Link' => [$link],
        ];
    }

    /**
     * 组装“开票/应收单或收款单生成退款单”的来源关联。
     */
    public static function refund(array $source, array $mapping): array
    {
        self::requireKeys($source, ['bill_id', 'bill_number', 'total_amount', 'current_amount']);

        $links = array_map(static function (array $item) use ($source, $mapping): array {
            self::requireKeys($item, ['entry_id', 'source_amount', 'current_amount']);

            return [
                'FREFUNDBILLSRCENTRY_Link_FRuleId' => self::mapping($mapping, 'convert_rule_id'),
                'FREFUNDBILLSRCENTRY_Link_FSBillId' => $source['bill_id'],
                'FREFUNDBILLSRCENTRY_Link_FSTableName' => self::mapping($mapping, 'source_table'),
                'FREFUNDBILLSRCENTRY_Link_FSId' => $item['entry_id'],
                'FREFUNDBILLSRCENTRY_Link_FREALREFUNDAMOUNT_SOLD' => $item['source_amount'],
                'FREFUNDBILLSRCENTRY_Link_FREALREFUNDAMOUNT_S' => $item['current_amount'],
            ];
        }, (array) ($source['items'] ?? []));

        return [
            'FSOURCETYPE' => self::mapping($mapping, 'source_form_id'),
            'FSRCBILLNO' => $source['bill_number'],
            'FSRCBILLID' => $source['bill_id'],
            'FAFTTAXTOTALAMOUNT' => $source['total_amount'],
            'FPLANREFUNDAMOUNT' => $source['plan_amount'] ?? $source['total_amount'],
            'FREALREFUNDAMOUNT_S' => $source['current_amount'],
            'FREFUNDBILLSRCENTRY_Link' => $links,
        ];
    }

    /**
     * 读取一个必填接口映射。
     */
    private static function mapping(array $mapping, string $key): string
    {
        $value = trim((string) ($mapping[$key] ?? ''));
        if ($value === '') {
            throw new InvalidArgumentException('缺少金蝶独立接口映射：' . $key);
        }

        return $value;
    }

    /**
     * 校验来源单据关联所需的业务参数。
     */
    private static function requireKeys(array $values, array $keys): void
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $values) || $values[$key] === null || $values[$key] === '') {
                throw new InvalidArgumentException('缺少来源单据参数：' . $key);
            }
        }
    }
}
