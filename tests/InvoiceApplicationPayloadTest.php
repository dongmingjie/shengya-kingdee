<?php

namespace Shengya\Kingdee\Tests;

use PHPUnit\Framework\TestCase;
use Shengya\Kingdee\Documents\InvoiceApplicationPayload;

final class InvoiceApplicationPayloadTest extends TestCase
{
    public function testItMapsConfiguredEmployeeToInvoiceApplication(): void
    {
        $payload = InvoiceApplicationPayload::from([
            'employee_number' => 'EMP001',
            'details' => [],
        ]);

        $this->assertSame(
            ['FSTAFFNUMBER' => 'EMP001'],
            $payload['Model']['F_PAEZ_Base']
        );
    }
}

