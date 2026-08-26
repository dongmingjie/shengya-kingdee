<?php

namespace Shengya\Kingdee\Contracts;

/**
 * 提供业务页面下拉选项所需的金蝶基础资料只读接口。
 */
interface KingdeeBaseDataInterface
{
    public function organizations(string $filter = '', ?int $limit = null, ?int $startRow = null): array;

    public function customers(string $filter = '', ?int $limit = null, ?int $startRow = null): array;

    public function materials(string $filter = '', ?int $limit = null, ?int $startRow = null): array;

    public function accounts(string $filter = '', ?int $limit = null, ?int $startRow = null): array;

    public function departments(string $filter = '', ?int $limit = null, ?int $startRow = null): array;

    public function positions(string $filter = '', ?int $limit = null, ?int $startRow = null): array;

    public function salesmen(string $filter = '', ?int $limit = null, ?int $startRow = null): array;

    public function employees(string $filter = '', ?int $limit = null, ?int $startRow = null): array;

    public function staffAssignments(string $filter = '', ?int $limit = null, ?int $startRow = null): array;

    public function paymentPurposes(string $filter = '', ?int $limit = null, ?int $startRow = null): array;

    public function settlementTypes(string $filter = '', ?int $limit = null, ?int $startRow = null): array;

    public function bankAccounts(string $filter = '', ?int $limit = null, ?int $startRow = null): array;

    public function cashAccounts(string $filter = '', ?int $limit = null, ?int $startRow = null): array;

    public function projects(string $filter = '', ?int $limit = null, ?int $startRow = null): array;
}
