<?php

namespace Shengya\KingdeeLaravel\Models;

/**
 * 组件开票申请单模型。
 */
class KingdeeInvoiceApplication extends KingdeeDocumentApplication
{
    /**
     * 开票单头与金蝶明细保持一对多，不能从 request_payload 临时猜测明细身份。
     */
    public function lines()
    {
        return $this->hasMany(KingdeeInvoiceApplicationLine::class, 'invoice_application_id');
    }

    public function deferredIncomeApplications()
    {
        return $this->hasMany(KingdeeDeferredIncomeApplication::class, 'source_invoice_id');
    }

    /**
     * 来源收款单属于组件业务数据，不能关联到只负责审计的调用记录表。
     */
    public function sourceReceipt()
    {
        return $this->belongsTo(KingdeeReceipt::class, 'source_receipt_id');
    }

    /**
     * 返回由本开票申请单下推生成的组件收款单。
     */
    public function generatedReceipts()
    {
        return $this->hasMany(KingdeeReceipt::class, 'source_invoice_id');
    }

    protected function tableMappingKey(): string
    {
        return 'invoice_applications';
    }
}
