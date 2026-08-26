<?php

namespace Shengya\Kingdee\Documents;

/**
 * 将生涯选择的业务值转换为金蝶退款单 Save 入参。
 */
final class RefundPayload extends AbstractDocumentPayload
{
    /**
     * @param array<string, mixed> $input 页面选择结果及本次退款业务数据
     * @return array<string, mixed>
     */
    public static function from(array $input): array
    {
        $entries = array_map(static function (array $entry): array {
            return self::removeEmpty([
                'FEntryID' => $entry['entry_id'] ?? 0,
                'FSETTLETYPEID' => self::baseData($entry['settlement_type_number'] ?? null),
                'FREFUNDAMOUNTFOR' => $entry['amount'] ?? null,
                'FNOTE' => $entry['remark'] ?? null,
                'FHANDLINGCHARGEFOR' => $entry['handling_fee'] ?? null,
                'FACCOUNTID' => self::baseData($entry['account_number'] ?? null),
                'FCashAccount' => self::baseData($entry['cash_account_number'] ?? null, 'FNUMBER'),
                'FPURPOSEID' => self::baseData($entry['purpose_number'] ?? null),
                'FRecType' => $entry['receipt_type'] ?? null,
                'FRuZhangType' => $entry['posting_type'] ?? null,
                'FPayType' => $entry['payment_type'] ?? null,
                'FPOSTDATE' => $entry['posting_date'] ?? null,
                'FCOSTID' => self::baseData($entry['income_category_number'] ?? null, 'FNUMBER'),
            ]);
        }, (array) ($input['entries'] ?? []));

        $model = self::removeEmpty([
            'FID' => $input['id'] ?? 0,
            'FPAYORGID' => self::baseData($input['pay_org_number'] ?? null),
            'FSETTLEORGID' => self::baseData($input['settle_org_number'] ?? null),
            'FSALEORGID' => self::baseData($input['sale_org_number'] ?? null),
            'FBillTypeID' => self::baseData($input['bill_type_number'] ?? null, 'FNUMBER'),
            'FDATE' => $input['date'] ?? null,
            'FBookingDate' => $input['booking_date'] ?? null,
            'FCONTACTUNITTYPE' => $input['contact_unit_type'] ?? null,
            'FCONTACTUNIT' => self::baseData($input['contact_unit_number'] ?? null),
            'FCURRENCYID' => self::baseData($input['currency_number'] ?? null),
            'FSETTLECUR' => self::baseData($input['settle_currency_number'] ?? null, 'FNUMBER'),
            'FBUSINESSTYPE' => $input['business_type'] ?? null,
            'FREMARK' => $input['remark'] ?? null,
            'FTHIRDBILLNO' => $input['third_bill_number'] ?? null,
            'FSALEDEPTID' => self::baseData($input['sale_department_number'] ?? null),
            'FSALEERID' => self::baseData($input['salesman_number'] ?? null),
            'F_PAEZ_Assistant' => self::baseData($input['project_number'] ?? null),
            'F_PAEZ_Base' => self::baseData($input['employee_number'] ?? null, 'FSTAFFNUMBER'),
            'F_PAEZ_Text' => $input['student_name'] ?? null,
            'F_PAEZ_Text1' => $input['source_text'] ?? null,
            'F_PAEZ_Text2' => $input['contract_number'] ?? null,
            'FSETTLEMAINBOOKID' => self::baseData($input['main_book_currency_number'] ?? null, 'FNUMBER'),
            'FREFUNDBILLENTRY' => $entries,
            'FREFUNDBILLSRCENTRY' => $input['source_entries'] ?? [],
        ]);

        return self::saveData($model, ['FREFUNDBILLENTRY.FEntryID', 'FBillNo']);
    }
}
