<?php

namespace Shengya\Kingdee\Contracts;

use Shengya\Kingdee\DTO\CallContext;
use Shengya\Kingdee\DTO\KingdeeResponse;

/**
 * 面向业务项目的独立金蝶能力接口，每个方法只执行一次保存动作。
 */
interface KingdeeOperationsInterface
{
    public function createInvoice(array $input, ?CallContext $context = null): KingdeeResponse;

    public function createReceipt(array $input, ?CallContext $context = null): KingdeeResponse;

    public function createReceiptFromInvoice(array $input, array $source, ?CallContext $context = null): KingdeeResponse;

    public function createRefundFromInvoice(array $input, array $source, ?CallContext $context = null): KingdeeResponse;

    public function createRefundFromReceipt(array $input, array $source, ?CallContext $context = null): KingdeeResponse;
}
