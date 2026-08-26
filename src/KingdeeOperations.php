<?php

namespace Shengya\Kingdee;

use InvalidArgumentException;
use Shengya\Kingdee\Contracts\KingdeeClientInterface;
use Shengya\Kingdee\Contracts\KingdeeOperationsInterface;
use Shengya\Kingdee\Documents\InvoiceApplicationPayload;
use Shengya\Kingdee\Documents\ReceiptPayload;
use Shengya\Kingdee\Documents\RefundPayload;
use Shengya\Kingdee\Documents\SourceRelationPayload;
use Shengya\Kingdee\DTO\CallContext;
use Shengya\Kingdee\DTO\KingdeeResponse;

/**
 * 独立金蝶业务能力门面，不编排跨接口自动流程。
 */
final class KingdeeOperations implements KingdeeOperationsInterface
{
    private $client;
    private $operations;

    public function __construct(KingdeeClientInterface $client, array $operations)
    {
        $this->client = $client;
        $this->operations = $operations;
    }

    public function createInvoice(array $input, ?CallContext $context = null): KingdeeResponse
    {
        return $this->save('create_invoice', InvoiceApplicationPayload::from($input), $context);
    }

    public function createReceipt(array $input, ?CallContext $context = null): KingdeeResponse
    {
        return $this->save('create_receipt', ReceiptPayload::from($input), $context);
    }

    public function createReceiptFromInvoice(array $input, array $source, ?CallContext $context = null): KingdeeResponse
    {
        $mapping = $this->operation('create_receipt_from_invoice');
        $input['source_entries'] = [SourceRelationPayload::receiptFromInvoice($source, $mapping)];

        return $this->saveWithMapping($mapping, ReceiptPayload::from($input), $context);
    }

    public function createRefundFromInvoice(array $input, array $source, ?CallContext $context = null): KingdeeResponse
    {
        return $this->createRefund('create_refund_from_invoice', $input, $source, $context);
    }

    public function createRefundFromReceipt(array $input, array $source, ?CallContext $context = null): KingdeeResponse
    {
        return $this->createRefund('create_refund_from_receipt', $input, $source, $context);
    }

    /**
     * 只保存退款单，不自动提交、审核或触发其他接口。
     */
    private function createRefund(string $operation, array $input, array $source, ?CallContext $context): KingdeeResponse
    {
        $mapping = $this->operation($operation);
        $input['source_entries'] = [SourceRelationPayload::refund($source, $mapping)];

        return $this->saveWithMapping($mapping, RefundPayload::from($input), $context);
    }

    /**
     * 根据独立接口名称保存目标单据。
     */
    private function save(string $operation, array $data, ?CallContext $context): KingdeeResponse
    {
        return $this->saveWithMapping($this->operation($operation), $data, $context);
    }

    /**
     * 执行一次保存动作；后续动作必须由调用方明确发起。
     */
    private function saveWithMapping(array $mapping, array $data, ?CallContext $context): KingdeeResponse
    {
        $formId = trim((string) ($mapping['target_form_id'] ?? ''));
        if ($formId === '') {
            throw new InvalidArgumentException('金蝶独立接口缺少 target_form_id。');
        }

        return $this->client->save($formId, $data, $context);
    }

    /**
     * 读取并校验一个独立接口配置。
     */
    private function operation(string $name): array
    {
        $mapping = $this->operations[$name] ?? null;
        if (!is_array($mapping)) {
            throw new InvalidArgumentException('缺少金蝶独立接口配置：' . $name);
        }

        return $mapping;
    }
}
