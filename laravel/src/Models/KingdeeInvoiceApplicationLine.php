<?php

namespace Shengya\KingdeeLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Shengya\Kingdee\Exceptions\ConfigurationException;

/**
 * 组件开票申请明细；每条记录严格对应一条金蝶应收单明细。
 */
class KingdeeInvoiceApplicationLine extends Model
{
    protected $guarded = [];

    protected $casts = [
        'invoice_application_id' => 'integer',
        'line_no' => 'integer',
        'quantity' => 'float',
        'tax_rate' => 'float',
        'tax_price' => 'float',
        'amount_with_tax' => 'float',
        'amount_without_tax' => 'float',
        'tax_amount' => 'float',
        'settlement_category' => 'integer',
        'confirm_income' => 'boolean',
    ];

    public function getTable()
    {
        return $this->mappedTable('invoice_application_lines');
    }

    public function getConnectionName()
    {
        $connection = config('kingdee.documents.connection');
        if (! is_string($connection) || trim($connection) === '') {
            throw new ConfigurationException('缺少金蝶业务单据数据库映射：kingdee.documents.connection');
        }

        return $connection;
    }

    public function invoiceApplication()
    {
        return $this->belongsTo(KingdeeInvoiceApplication::class, 'invoice_application_id');
    }

    public function deferredLines()
    {
        return $this->hasMany(KingdeeDeferredIncomeApplicationLine::class, 'invoice_application_line_id');
    }

    private function mappedTable(string $key): string
    {
        $table = config('kingdee.documents.tables.'.$key);
        if (! is_string($table) || trim($table) === '') {
            throw new ConfigurationException('缺少金蝶业务单据表映射：kingdee.documents.tables.'.$key);
        }

        return $table;
    }
}
