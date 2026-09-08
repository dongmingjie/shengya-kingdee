<?php

namespace Shengya\Kingdee\Tests;

use PHPUnit\Framework\TestCase;
use Shengya\Kingdee\Documents\SourceRelationPayload;

final class SourceRelationPayloadTest extends TestCase
{
    public function testItUsesTheReceivablePlanEntryAsSourceBillId(): void
    {
        $payload = SourceRelationPayload::receiptFromInvoice([
            'bill_id' => 101,
            'bill_number' => 'YS0001',
            'entry_id' => 202,
            'total_amount' => 1000,
            'plan_amount' => 1000,
            'current_amount' => 600,
        ], [
            'source_form_id' => 'AR_receivable',
            'convert_rule_id' => 'AR_recableToRecBill',
            'source_table' => 't_AR_receivablePlan',
        ]);

        $this->assertSame(202, $payload['FSRCBILLID']);
        $this->assertSame(101, $payload['FSRCROWID']);
        $this->assertSame(101, $payload['FRECEIVEBILLSRCENTRY_Link'][0]['FRECEIVEBILLSRCENTRY_Link_FSBillId']);
        $this->assertSame(202, $payload['FRECEIVEBILLSRCENTRY_Link'][0]['FRECEIVEBILLSRCENTRY_Link_FSId']);
    }
}
