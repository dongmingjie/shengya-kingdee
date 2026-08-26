<?php

namespace Shengya\Kingdee\Documents;

/**
 * 将生涯选择的业务值转换为金蝶收款单 Save 入参。
 */
final class ReceiptPayload extends AbstractDocumentPayload
{
    /**
     * @param array<string, mixed> $input 页面选择结果及本次收款业务数据
     * @return array<string, mixed> 可直接传给 KingdeeClientInterface::save() 的 data 参数
     */
    public static function from(array $input): array
    {
        $entries = array_map(static function (array $entry): array {
            return self::removeEmpty([
                'FEntryID' => $entry['entry_id'] ?? 0,
                'FACCOUNTID' => self::baseData($entry['account_number'] ?? null),
                'FCashAccount' => self::baseData($entry['cash_account_number'] ?? null),
                'FRECTOTALAMOUNTFOR' => $entry['amount'] ?? null,
                'FSETTLETYPEID' => self::baseData($entry['settlement_type_number'] ?? null),
                'FPURPOSEID' => self::baseData($entry['purpose_number'] ?? null),
                'FHANDLINGCHARGEFOR' => $entry['handling_fee'] ?? null,
                'F_PAEZ_Base1' => self::baseData($entry['income_category_number'] ?? null, 'FNUMBER'),
                'F_PAEZ_Text3' => $entry['contract_text'] ?? null,
                'FCOMMENT' => $entry['remark'] ?? null,
            ]);
        }, (array) ($input['entries'] ?? []));

        $model = self::removeEmpty([
            'FID' => $input['id'] ?? 0,
            'FDate' => $input['date'] ?? null,
            'FPAYORGID' => self::baseData($input['pay_org_number'] ?? null),
            'FBILLTYPEID' => self::baseData($input['bill_type_number'] ?? null),
            'FSETTLEORGID' => self::baseData($input['settle_org_number'] ?? null),
            'FSALEORGID' => self::baseData($input['sale_org_number'] ?? null),
            'FCONTACTUNITTYPE' => $input['contact_unit_type'] ?? null,
            'FCONTACTUNIT' => self::baseData($input['contact_unit_number'] ?? null),
            'FPAYUNITTYPE' => $input['pay_unit_type'] ?? null,
            'FPAYUNIT' => self::baseData($input['pay_unit_number'] ?? null),
            'FCURRENCYID' => self::baseData($input['currency_number'] ?? null),
            'FSETTLECUR' => self::baseData($input['settle_currency_number'] ?? null),
            'F_PAEZ_Assistant' => self::baseData($input['project_number'] ?? null),
            'F_PAEZ_Text' => $input['student_name'] ?? null,
            'F_PAEZ_Text1' => $input['payer_name'] ?? null,
            'F_PAEZ_Text2' => $input['source_text'] ?? null,
            'FREMARK' => $input['remark'] ?? null,
            'FSALEERID' => self::baseData($input['salesman_number'] ?? null),
            'FSALEDEPTID' => self::baseData($input['sale_department_number'] ?? null),
            'F_PAEZ_Base' => self::baseData($input['employee_number'] ?? null, 'FSTAFFNUMBER'),
            'FRECEIVEBILLENTRY' => $entries,
            'FRECEIVEBILLSRCENTRY' => $input['source_entries'] ?? [],
        ]);

        return self::saveData($model, [
            'FRECEIVEBILLENTRY.FENTRYID',
            'FRECEIVEBILLENTRY.FENTRYNumber',
        ]);
    }
}
