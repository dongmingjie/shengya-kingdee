<?php

namespace Shengya\Kingdee\Tests;

use PHPUnit\Framework\TestCase;
use Shengya\Kingdee\Documents\InvoiceApplicationPayload;

final class InvoiceApplicationPayloadTest extends TestCase
{
    public function testItMapsSalesmanAssignmentToBothRequiredReceivableFields(): void
    {
        $payload = InvoiceApplicationPayload::from([
            'employee_number' => 'YG202205243',
            'salesman_number' => 'YG202205243_GW202164_1',
            'performance_department_number' => 'BM0003',
            'details' => [],
        ]);

        $this->assertSame(
            ['FNumber' => 'YG202205243_GW202164_1'],
            $payload['Model']['FSALEERID']
        );
        $this->assertSame(
            ['FSTAFFNUMBER' => 'YG202205243_GW202164_1'],
            $payload['Model']['F_PAEZ_Base1']
        );
        $this->assertSame(
            ['FNumber' => 'BM0003'],
            $payload['Model']['F_PAEZ_Base2']
        );
        $this->assertArrayNotHasKey('F_PAEZ_Base', $payload['Model']);
    }
}
