<?php

namespace Shengya\KingdeeLaravel\Models;

/**
 * 组件收款单模型。
 */
class KingdeeReceipt extends KingdeeDocumentApplication
{
    /**
     * 来源开票申请单属于组件业务数据，调用记录仅由 save_record_id 关联。
     */
    public function sourceInvoice()
    {
        return $this->belongsTo(KingdeeInvoiceApplication::class, 'source_invoice_id');
    }

    /**
     * 返回由本收款单下推生成的组件开票申请单。
     */
    public function generatedInvoices()
    {
        return $this->hasMany(KingdeeInvoiceApplication::class, 'source_receipt_id');
    }

    protected function tableMappingKey(): string
    {
        return 'receipts';
    }
}
