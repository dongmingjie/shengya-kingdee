<?php

namespace Shengya\Kingdee\Documents;

/**
 * 将生涯选择的业务值转换为金蝶开票申请/应收单 Save 入参。
 */
final class InvoiceApplicationPayload extends AbstractDocumentPayload
{
    /**
     * @param array<string, mixed> $input 页面选择结果及本次开票业务数据
     * @return array<string, mixed>
     */
    public static function from(array $input): array
    {
        $details = array_map(static function (array $item): array {
            return self::removeEmpty([
                'FEntryID' => $item['entry_id'] ?? 0,
                'FMATERIALID' => self::baseData($item['material_number'] ?? null),
                'F_PAEZ_CheckBox' => $item['confirm_income'] ?? null,
                'FPriceQty' => $item['quantity'] ?? null,
                'F_PAEZ_Combo3' => $item['settlement_category'] ?? null,
                'FEntryTaxRate' => $item['tax_rate'] ?? null,
                'FTaxPrice' => $item['tax_price'] ?? null,
                'FNoTaxAmountFor_D' => $item['amount_without_tax'] ?? null,
                'FTAXAMOUNTFOR_D' => $item['tax_amount'] ?? null,
            ]);
        }, (array) ($input['details'] ?? []));

        $finance = self::removeEmpty([
            'FMAINBOOKSTDCURRID' => self::baseData($input['main_book_currency_number'] ?? null),
            'FEXCHANGETYPE' => self::baseData($input['exchange_type_number'] ?? null),
        ]);

        $model = self::removeEmpty([
            'FID' => $input['id'] ?? 0,
            'FBillTypeID' => self::baseData($input['bill_type_number'] ?? null, 'FNUMBER'),
            'FDATE' => $input['date'] ?? null,
            'FENDDATE_H' => $input['end_date'] ?? null,
            'FCUSTOMERID' => self::baseData($input['customer_number'] ?? null),
            'FCURRENCYID' => self::baseData($input['currency_number'] ?? null),
            'FSETTLEORGID' => self::baseData($input['settle_org_number'] ?? null),
            'FPAYORGID' => self::baseData($input['pay_org_number'] ?? null),
            'FSaleOrgId' => self::baseData($input['sale_org_number'] ?? null),
            'FSALEDEPTID' => self::baseData($input['sale_department_number'] ?? null),
            'FSALEERID' => self::baseData($input['salesman_number'] ?? null),
            'F_PAEZ_Assistant' => self::baseData($input['project_number'] ?? null),
            'F_PAEZ_Base2' => self::baseData($input['performance_department_number'] ?? null),
            'F_PAEZ_Combo' => $input['invoice_type'] ?? null,
            'F_PAEZ_Text1' => $input['contract_number'] ?? null,
            'F_PAEZ_Text3' => $input['customer_name'] ?? null,
            'F_PAEZ_Text6' => $input['source_text'] ?? null,
            'FAR_Remark' => $input['remark'] ?? null,
            'FEntityDetail' => $details,
            'FsubHeadFinc' => $finance,
        ]);

        return self::saveData($model, ['FEntityDetail.FEntryID', 'FEntityPlan.FEntryID']);
    }
}
