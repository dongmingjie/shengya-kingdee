<?php

namespace Shengya\KingdeeLaravel\Models;

/**
 * 组件收款单模型。
 */
class KingdeeReceipt extends KingdeeDocumentApplication
{
    protected function tableMappingKey(): string
    {
        return 'receipts';
    }
}
