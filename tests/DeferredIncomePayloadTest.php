<?php

namespace Shengya\Kingdee\Tests;

use PHPUnit\Framework\TestCase;
use Shengya\Kingdee\Documents\DeferredIncomePayload;

final class DeferredIncomePayloadTest extends TestCase
{
    public function testItKeepsEverySelectedInvoiceLineSeparate(): void
    {
        $payload = DeferredIncomePayload::from([
            'income_date' => '2026-09-15',
            'currency_number' => 'PRE001',
            'project_number' => 'SYJY-SH-2026001',
            // 来源开票单还包含确认收入行，因此表头总额可以大于本次递延明细合计。
            'source_amount_without_tax' => 1500.00,
            'details' => [
                [
                    'material_number' => 'FW001',
                    'amount_with_tax' => 700,
                    'amount_without_tax' => 660.38,
                    'tax_amount' => 39.62,
                    'source_bill_number' => 'AR00001',
                    'source_bill_type' => 'AR_receivable',
                    'source_bill_id' => 88,
                    'source_table' => 't_AR_receivableEntry',
                    'source_entry_id' => 101,
                    'convert_rule_id' => 'AR_TO_DEFERRED',
                ],
                [
                    'material_number' => 'FW002',
                    'amount_with_tax' => 300,
                    'amount_without_tax' => 283.02,
                    'tax_amount' => 16.98,
                    'source_bill_number' => 'AR00001',
                    'source_bill_type' => 'AR_receivable',
                    'source_bill_id' => 88,
                    'source_table' => 't_AR_receivableEntry',
                    'source_entry_id' => 102,
                    'convert_rule_id' => 'AR_TO_DEFERRED',
                ],
            ],
        ]);

        $this->assertCount(2, $payload['Model']['FEntity']);
        // 递延明细金额使用不含税口径；表头金额由金蝶根据源单关系自动带出。
        $this->assertSame(660.38, $payload['Model']['FEntity'][0]['F_PAEZ_Amount']);
        $this->assertSame(283.02, $payload['Model']['FEntity'][1]['F_PAEZ_Amount']);
        $this->assertArrayNotHasKey('FNoTaxAmountFor', $payload['Model']);
        $this->assertFalse($payload['IsAutoSubmitAndAudit']);
    }

    public function testItBuildsSourceLinkOnlyWhenTheSourceEntryIsKnown(): void
    {
        $payload = DeferredIncomePayload::from([
            'details' => [[
                'material_number' => 'FW001',
                'amount_without_tax' => 100,
                'source_table' => 't_AR_receivableEntry',
                'source_bill_id' => 88,
                'source_bill_number' => 'AR00001',
                'source_bill_type' => 'AR_receivable',
                'source_entry_id' => 99,
                'convert_rule_id' => 'AR_TO_DEFERRED',
            ]],
        ]);

        $this->assertSame(
            99,
            $payload['Model']['FEntity'][0]['FEntity_Link'][0]['FEntity_Link_FSId']
        );
    }

    public function testItRejectsADeferredDocumentWithoutACompleteSourceLink(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DeferredIncomePayload::from([
            'details' => [[
                'material_number' => 'FW001',
                'amount_without_tax' => 100,
                'source_bill_id' => 88,
            ]],
        ]);
    }

    public function testItMapsTheVerifiedDeferredIncomeHeaderFields(): void
    {
        $payload = DeferredIncomePayload::from([
            'bill_type_number' => 'YSD05_SYS',
            'settle_org_number' => 'ORG001',
            'pay_org_number' => 'ORG001',
            'sale_org_number' => 'ORG001',
            'customer_number' => 'C001',
            'salesman_number' => 'STAFF001',
            'sale_department_number' => 'D001',
            'performance_department_number' => 'D002',
            'project_number' => 'P001',
            'contract_number' => 'HT001',
            'invoice_type' => 1,
            'income_date' => '2026-09-15',
            'details' => [],
        ]);

        $this->assertSame(['FNumber' => 'YSD05_SYS'], $payload['Model']['FBillTypeID']);
        $this->assertSame(['FNumber' => 'ORG001'], $payload['Model']['FSETTLEORGID']);
        // 新组件 project_number 是项目编码，不能沿用 yuanbo2019 内码字段的 FEntryId。
        $this->assertSame(['FNumber' => 'P001'], $payload['Model']['F_PAEZ_Assistant']);
        $this->assertSame('HT001', $payload['Model']['F_PAEZ_Text1']);
        $this->assertTrue($payload['Model']['FISTAX']);
    }
}
