<?php

namespace Shengya\KingdeeLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Shengya\Kingdee\Exceptions\ConfigurationException;

/**
 * 组件递延申请明细；保存每次对来源开票明细的额度占用。
 */
class KingdeeDeferredIncomeApplicationLine extends Model
{
    protected $guarded = [];

    protected $casts = [
        'deferred_income_application_id' => 'integer',
        'invoice_application_line_id' => 'integer',
        'line_no' => 'integer',
        'amount_with_tax' => 'float',
        'amount_without_tax' => 'float',
        'tax_amount' => 'float',
    ];

    public function getTable()
    {
        $table = config('kingdee.documents.tables.deferred_income_application_lines');
        if (! is_string($table) || trim($table) === '') {
            throw new ConfigurationException(
                '缺少金蝶业务单据表映射：kingdee.documents.tables.deferred_income_application_lines'
            );
        }

        return $table;
    }

    public function getConnectionName()
    {
        $connection = config('kingdee.documents.connection');
        if (! is_string($connection) || trim($connection) === '') {
            throw new ConfigurationException('缺少金蝶业务单据数据库映射：kingdee.documents.connection');
        }

        return $connection;
    }

    public function application()
    {
        return $this->belongsTo(KingdeeDeferredIncomeApplication::class, 'deferred_income_application_id');
    }

    public function invoiceLine()
    {
        return $this->belongsTo(KingdeeInvoiceApplicationLine::class, 'invoice_application_line_id');
    }
}
