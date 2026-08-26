<?php

namespace Shengya\Kingdee;

use InvalidArgumentException;
use Shengya\Kingdee\Contracts\KingdeeBaseDataInterface;
use Shengya\Kingdee\Contracts\KingdeeClientInterface;

/**
 * 通过金蝶 ExecuteBillQuery 获取基础资料，并转换为带字段名的数组。
 */
final class KingdeeBaseData implements KingdeeBaseDataInterface
{
    private const FIELDS = [
        'organizations' => [
            'FOrgID', 'FNumber', 'FName', 'FForbidStatus',
        ],
        'customers' => [
            'FCustID', 'FCustTypeId.FNumber', 'FGroup.FNumber', 'FNumber', 'FName',
            'FCreateOrgId.FNumber', 'FUseOrgId.FNumber', 'FADDRESS', 'FTAXREGISTERCODE',
            'FTel', 'FACCOUNTNAME', 'FForbidStatus',
        ],
        'materials' => [
            'FNumber', 'FName', 'FForbidStatus', 'FUseOrgId.FNumber',
            'FMaterialGroup.FName', 'FMaterialGroup', 'FCategoryID.FNumber',
            'FCategoryID.FName', 'FBaseUnitId.FNumber',
        ],
        'accounts' => ['FNumber', 'FName'],
        'departments' => [
            'FDEPTID', 'FNumber', 'FName', 'FForbidStatus', 'FUseOrgId.FNumber',
            'FCreateOrgId.FNumber', 'FGroup', 'FGroup.FName',
        ],
        'positions' => [
            'FNumber', 'FName', 'FDept.FNumber', 'FCreateOrgId.FNumber',
            'FUseOrgId.FNumber', 'FForbidStatus',
        ],
        'salesmen' => [
            'FNumber', 'FName', 'FEmpNumber', 'FBizOrgId.FNumber',
            'FDeptId.FNumber', 'FForbidStatus',
        ],
        'employees' => [
            'FID', 'FNumber', 'FName', 'FCreateOrgId.FNumber',
            'FUseOrgId.FNumber', 'FForbidStatus',
        ],
        'staff_assignments' => [
            'FStaffNumber', 'FNumber', 'FDept.FNumber', 'FPosition.FNumber',
            'FCreateOrgId.FNumber', 'FUseOrgId.FNumber', 'FForbidStatus',
        ],
        'payment_purposes' => ['FNumber', 'FName'],
        'settlement_types' => ['FNumber', 'FName'],
        'bank_accounts' => ['FNumber', 'FName', 'FBANKACNTID', 'FUseOrgId.FNumber'],
        'cash_accounts' => ['FNumber', 'FName', 'FID', 'FUseOrgId.FNumber'],
        'projects' => ['FEntryId', 'FNumber', 'FDataValue'],
    ];

    private $client;
    private $formIds;

    public function __construct(KingdeeClientInterface $client, array $formIds)
    {
        $this->client = $client;
        $this->formIds = $formIds;
    }

    public function organizations(string $filter = '', ?int $limit = null, ?int $startRow = null): array
    {
        return $this->fetch('organizations', $filter, $limit, $startRow);
    }

    public function customers(string $filter = '', ?int $limit = null, ?int $startRow = null): array
    {
        return $this->fetch('customers', $filter, $limit, $startRow);
    }

    public function materials(string $filter = '', ?int $limit = null, ?int $startRow = null): array
    {
        return $this->fetch('materials', $filter, $limit, $startRow);
    }

    public function departments(string $filter = '', ?int $limit = null, ?int $startRow = null): array
    {
        return $this->fetch('departments', $filter, $limit, $startRow);
    }

    public function accounts(string $filter = '', ?int $limit = null, ?int $startRow = null): array
    {
        return $this->fetch('accounts', $filter, $limit, $startRow);
    }

    public function positions(string $filter = '', ?int $limit = null, ?int $startRow = null): array
    {
        return $this->fetch('positions', $filter, $limit, $startRow);
    }

    public function salesmen(string $filter = '', ?int $limit = null, ?int $startRow = null): array
    {
        return $this->fetch('salesmen', $filter, $limit, $startRow);
    }

    public function employees(string $filter = '', ?int $limit = null, ?int $startRow = null): array
    {
        return $this->fetch('employees', $filter, $limit, $startRow);
    }

    public function staffAssignments(string $filter = '', ?int $limit = null, ?int $startRow = null): array
    {
        return $this->fetch('staff_assignments', $filter, $limit, $startRow);
    }

    public function paymentPurposes(string $filter = '', ?int $limit = null, ?int $startRow = null): array
    {
        return $this->fetch('payment_purposes', $filter, $limit, $startRow);
    }

    public function settlementTypes(string $filter = '', ?int $limit = null, ?int $startRow = null): array
    {
        return $this->fetch('settlement_types', $filter, $limit, $startRow);
    }

    public function bankAccounts(string $filter = '', ?int $limit = null, ?int $startRow = null): array
    {
        return $this->fetch('bank_accounts', $filter, $limit, $startRow);
    }

    public function cashAccounts(string $filter = '', ?int $limit = null, ?int $startRow = null): array
    {
        return $this->fetch('cash_accounts', $filter, $limit, $startRow);
    }

    public function projects(string $filter = '', ?int $limit = null, ?int $startRow = null): array
    {
        return $this->fetch('projects', $filter, $limit, $startRow);
    }

    /**
     * 查询一类基础资料并将金蝶按列返回的数据转换为关联数组。
     */
    private function fetch(string $type, string $filter, ?int $limit, ?int $startRow): array
    {
        $fields = self::FIELDS[$type] ?? null;
        $formId = trim((string) ($this->formIds[$type] ?? ''));
        if (!is_array($fields) || $formId === '') {
            throw new InvalidArgumentException('缺少金蝶基础资料映射：' . $type);
        }

        $rows = $this->client->query($formId, $fields, $filter, $limit, $startRow)->rows();

        return array_map(static function ($row) use ($fields): array {
            if (!is_array($row)) {
                return [];
            }

            $values = array_values($row);
            $values = array_pad($values, count($fields), null);

            return array_combine($fields, array_slice($values, 0, count($fields)));
        }, $rows);
    }
}
