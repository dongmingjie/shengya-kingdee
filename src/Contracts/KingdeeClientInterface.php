<?php

namespace Shengya\Kingdee\Contracts;

use Shengya\Kingdee\DTO\CallContext;
use Shengya\Kingdee\DTO\KingdeeResponse;

/**
 * 提供给业务项目使用的稳定调用接口。
 */
interface KingdeeClientInterface
{
    public function query(string $formId, array $fieldKeys, string $filter = '', ?int $limit = null, ?int $startRow = null, ?CallContext $context = null): KingdeeResponse;

    public function view(string $formId, array $data, ?CallContext $context = null): KingdeeResponse;

    public function save(string $formId, array $data, ?CallContext $context = null): KingdeeResponse;

    public function submit(string $formId, array $numbers, ?CallContext $context = null): KingdeeResponse;

    public function audit(string $formId, array $numbers, ?CallContext $context = null): KingdeeResponse;
}
