<?php

namespace Shengya\KingdeeLaravel\Models;

/**
 * 组件递延转收入申请单模型。
 */
class KingdeeDeferredIncomeApplication extends KingdeeDocumentApplication
{
    protected $casts = [
        'amount' => 'float',
        'applicant_id' => 'integer',
        'source_invoice_id' => 'integer',
        'save_record_id' => 'integer',
        'income_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function sourceInvoice()
    {
        return $this->belongsTo(KingdeeInvoiceApplication::class, 'source_invoice_id');
    }

    public function lines()
    {
        return $this->hasMany(KingdeeDeferredIncomeApplicationLine::class, 'deferred_income_application_id');
    }

    protected function tableMappingKey(): string
    {
        return 'deferred_income_applications';
    }
}
