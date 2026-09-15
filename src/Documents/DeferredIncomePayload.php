<?php

namespace Shengya\Kingdee\Documents;

/**
 * 将组件递延申请转换为金蝶“递延收益结转单”Save 入参。
 */
final class DeferredIncomePayload extends AbstractDocumentPayload
{
    /**
     * @param array<string, mixed> $input 已审核的递延申请及来源开票明细
     * @return array<string, mixed>
     */
    public static function from(array $input): array
    {
        $details = array_map(static function (array $item): array {
            $sourceLink = self::sourceLink($item);

            return self::removeEmpty([
                'FEntryID' => $item['entry_id'] ?? 0,
                'FMATERIALID' => self::baseData($item['material_number'] ?? null),
                'F_PAEZ_Amount' => $item['amount_with_tax'] ?? null,
                'F_PAEZ_SourceBillNo' => $item['source_bill_number'] ?? null,
                'F_PAEZ_SourceBillType' => $item['source_bill_type'] ?? null,
                'F_PAEZ_Integer' => $item['source_bill_id'] ?? null,
                'FEntity_Link' => $sourceLink ? [$sourceLink] : [],
            ]);
        }, (array) ($input['details'] ?? []));

        $model = self::removeEmpty([
            'FID' => $input['id'] ?? 0,
            'FBillNo' => $input['number'] ?? null,
            'FBillTypeID' => self::baseData($input['bill_type_number'] ?? null),
            'FSETTLEORGID' => self::baseData($input['settle_org_number'] ?? null),
            'FPAYORGID' => self::baseData($input['pay_org_number'] ?? null),
            'FSaleOrgId' => self::baseData($input['sale_org_number'] ?? null),
            'FCustomerID' => self::baseData($input['customer_number'] ?? null),
            'FDATE' => $input['income_date'] ?? null,
            'FENDDATE_H' => $input['income_date'] ?? null,
            'FCURRENCYID' => self::baseData($input['currency_number'] ?? null),
            'FSALEERID' => self::baseData($input['salesman_number'] ?? null),
            'FSALEDEPTID' => self::baseData($input['sale_department_number'] ?? null),
            'F_PAEZ_Base2' => self::baseData($input['performance_department_number'] ?? null),
            // 当前组件保存的是项目编码而非旧系统的项目内码，必须按 FNumber 解析。
            'F_PAEZ_Assistant' => self::baseData($input['project_number'] ?? null),
            'F_PAEZ_Combo' => $input['invoice_type'] ?? null,
            'F_PAEZ_Text1' => $input['contract_number'] ?? null,
            'F_PAEZ_Text3' => $input['customer_name'] ?? null,
            'F_PAEZ_Text4' => $input['source_text'] ?? 'crm',
            'FAR_Remark' => $input['remark'] ?? null,
            'FISTAX' => true,
            'FEntity' => $details,
        ]);

        return self::saveData($model, ['FEntity.FEntryID']);
    }

    /**
     * 金蝶源单关联配置为空时只保留来源单号，避免生成不完整的 Link 报文。
     */
    private static function sourceLink(array $item): array
    {
        $sourceTable = trim((string) ($item['source_table'] ?? ''));
        $sourceEntryId = trim((string) ($item['source_entry_id'] ?? ''));
        $convertRuleId = trim((string) ($item['convert_rule_id'] ?? ''));
        if ($sourceTable === '' || $sourceEntryId === '' || $convertRuleId === '') {
            return [];
        }

        return self::removeEmpty([
            'FEntity_Link_FRuleId' => $convertRuleId,
            'FEntity_Link_FSTableName' => $sourceTable,
            'FEntity_Link_FSBillId' => $item['source_bill_id'] ?? null,
            'FEntity_Link_FSId' => $sourceEntryId,
        ]);
    }
}
