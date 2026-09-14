<?php

namespace Shengya\KingdeeLaravel\Models;

/**
 * 组件开票申请单模型。
 */
class KingdeeInvoiceApplication extends KingdeeDocumentApplication
{
    protected function tableMappingKey(): string
    {
        return 'invoice_applications';
    }
}
