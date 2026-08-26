<?php

namespace Shengya\KingdeeLaravel\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Shengya\Kingdee\Contracts\KingdeeBaseDataInterface;
use Throwable;

/**
 * 分页拉取金蝶基础资料并按资料类型幂等保存到独立表。
 */
final class KingdeeBaseDataSyncService
{
    private const METHODS = [
        'organizations' => 'organizations',
        'customers' => 'customers',
        'materials' => 'materials',
        'accounts' => 'accounts',
        'departments' => 'departments',
        'positions' => 'positions',
        'salesmen' => 'salesmen',
        'employees' => 'employees',
        'staff_assignments' => 'staffAssignments',
        'payment_purposes' => 'paymentPurposes',
        'settlement_types' => 'settlementTypes',
        'bank_accounts' => 'bankAccounts',
        'cash_accounts' => 'cashAccounts',
        'projects' => 'projects',
    ];

    /**
     * 各类资料独有的金蝶字段映射，公共字段仍由 normalize() 统一处理。
     */
    private const TYPE_FIELDS = [
        'customers' => [
            'customer_type_number' => ['FCustTypeId.FNumber'],
            'group_number' => ['FGroup.FNumber'],
            'address' => ['FADDRESS'],
            'tax_register_code' => ['FTAXREGISTERCODE'],
            'telephone' => ['FTel'],
            'account_name' => ['FACCOUNTNAME'],
        ],
        'materials' => [
            'material_group_name' => ['FMaterialGroup.FName'],
            'material_group_id' => ['FMaterialGroup'],
            'category_number' => ['FCategoryID.FNumber'],
            'category_name' => ['FCategoryID.FName'],
            'base_unit_number' => ['FBaseUnitId.FNumber'],
        ],
        'departments' => [
            'group_id' => ['FGroup'],
            'group_name' => ['FGroup.FName'],
        ],
        'positions' => [
            'department_number' => ['FDept.FNumber'],
        ],
        'salesmen' => [
            'employee_number' => ['FEmpNumber'],
            'business_org_number' => ['FBizOrgId.FNumber'],
            'department_number' => ['FDeptId.FNumber'],
        ],
        'staff_assignments' => [
            'staff_number' => ['FStaffNumber'],
            'employee_number' => ['FNumber'],
            'department_number' => ['FDept.FNumber'],
            'position_number' => ['FPosition.FNumber'],
        ],
    ];

    private $baseData;
    private $connection;
    private $accountSet;
    private $tables;
    private $batchesTable;
    private $defaultPageSize;

    public function __construct(
        KingdeeBaseDataInterface $baseData,
        ConnectionInterface $connection,
        string $accountSet,
        array $tables,
        string $batchesTable,
        int $defaultPageSize
    ) {
        if (trim($accountSet) === '') {
            throw new InvalidArgumentException('金蝶基础资料同步缺少账套配置。');
        }

        if ($defaultPageSize <= 0) {
            throw new InvalidArgumentException('金蝶基础资料同步每页数量必须大于 0。');
        }

        $this->baseData = $baseData;
        $this->connection = $connection;
        $this->accountSet = $accountSet;
        foreach (array_keys(self::METHODS) as $type) {
            if (!isset($tables[$type]) || !is_string($tables[$type]) || trim($tables[$type]) === '') {
                throw new InvalidArgumentException('缺少金蝶基础资料表配置：' . $type);
            }
        }

        $this->tables = $tables;
        $this->batchesTable = $batchesTable;
        $this->defaultPageSize = $defaultPageSize;
    }

    /**
     * 返回组件支持同步的全部基础资料类型。
     */
    public function types(): array
    {
        return array_keys(self::METHODS);
    }

    /**
     * 同步全部基础资料；每个类型使用独立批次，单类失败不会抹掉其他类型结果。
     */
    public function syncAll(?int $pageSize = null): array
    {
        $results = [];
        foreach ($this->types() as $type) {
            try {
                $results[$type] = $this->sync($type, '', $pageSize);
            } catch (Throwable $exception) {
                $results[$type] = [
                    'status' => 'failed',
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * 分页同步一个类型；无过滤条件时按全量处理并标记缺失旧数据为失效。
     */
    public function sync(string $type, string $filter = '', ?int $pageSize = null): array
    {
        // 长时间分页同步会让 Telescope 持续缓存查询事件，必须在同步前停止采集以控制内存。
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }

        $method = self::METHODS[$type] ?? null;
        if ($method === null) {
            throw new InvalidArgumentException('不支持的金蝶基础资料类型：' . $type);
        }

        $pageSize = $pageSize === null ? $this->defaultPageSize : $pageSize;
        if ($pageSize <= 0) {
            throw new InvalidArgumentException('金蝶基础资料同步每页数量必须大于 0。');
        }

        $batchId = $this->createBatch($type, $filter, $pageSize);
        $startRow = 0;
        $pages = 0;
        $received = 0;
        $created = 0;
        $updated = 0;
        $now = date('Y-m-d H:i:s');

        try {
            while (true) {
                $rows = $this->baseData->{$method}($filter, $pageSize, $startRow);
                $pageCount = count($rows);
                $pages++;
                $received += $pageCount;

                if ($pageCount > 0) {
                    [$pageCreated, $pageUpdated] = $this->persistRows($type, $rows, $batchId, $now);
                    $created += $pageCreated;
                    $updated += $pageUpdated;
                }

                // 每页保存当前进度，异常退出时仍能看到已经接收和写入的数量。
                $this->updateBatchProgress($batchId, compact('pages', 'received', 'created', 'updated'));

                if ($pageCount < $pageSize) {
                    break;
                }

                $startRow += $pageSize;
                unset($rows);
                gc_collect_cycles();
            }

            // 只有无过滤条件的全量同步才能安全地将本次未返回的数据标记为失效。
            $disabled = trim($filter) === '' ? $this->disableMissing($type, $batchId, $now) : 0;
            $result = compact('pages', 'received', 'created', 'updated', 'disabled');
            $this->finishBatch($batchId, 'succeeded', $result);

            return array_merge(['batch_id' => $batchId, 'status' => 'succeeded'], $result);
        } catch (Throwable $exception) {
            $this->finishBatch($batchId, 'failed', compact('pages', 'received', 'created', 'updated'), $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * 批量写入一页数据，按账套、类型和身份摘要更新。
     */
    private function persistRows(string $type, array $rows, int $batchId, string $now): array
    {
        $payloads = [];
        $created = 0;
        $updated = 0;
        foreach ($rows as $row) {
            if (!is_array($row) || !$row) {
                continue;
            }

            $normalized = $this->normalize($type, $row, $batchId, $now);
            $payloads[$normalized['identity_key']] = $normalized;

            // 限制单次 upsert 的数组和 SQL 体积，避免客户原始报文导致峰值内存过高。
            if (count($payloads) >= 100) {
                [$chunkCreated, $chunkUpdated] = $this->persistPayloads($type, $payloads);
                $created += $chunkCreated;
                $updated += $chunkUpdated;
                $payloads = [];
            }
        }

        if ($payloads) {
            [$chunkCreated, $chunkUpdated] = $this->persistPayloads($type, $payloads);
            $created += $chunkCreated;
            $updated += $chunkUpdated;
        }

        return [$created, $updated];
    }

    /**
     * 写入一个受控大小的数据块，并统计本次新增和更新数量。
     */
    private function persistPayloads(string $type, array $payloads): array
    {
        $identityKeys = array_keys($payloads);
        $table = $this->tableFor($type);
        $existing = $this->connection->table($table)
            ->where('account_set', $this->accountSet)
            ->whereIn('identity_key', $identityKeys)
            ->pluck('identity_key')
            ->all();

        $created = count(array_diff($identityKeys, $existing));
        $updated = count($identityKeys) - $created;

        // 每类表的特有字段也参与更新，避免只能从 source_payload 中读取业务字段。
        $updateColumns = array_values(array_diff(array_keys(reset($payloads)), [
            'account_set', 'identity_key', 'created_at',
        ]));
        $this->connection->table($table)->upsert(
            array_values($payloads),
            ['account_set', 'identity_key'],
            $updateColumns
        );

        return [$created, $updated];
    }

    /**
     * 将不同基础资料的公共字段归一化，同时完整保留原始具名字段。
     */
    private function normalize(string $type, array $row, int $batchId, string $now): array
    {
        $kingdeeId = $this->firstValue($row, ['FOrgID', 'FCustID', 'FDEPTID', 'FBANKACNTID', 'FEntryId', 'FID']);
        $number = $this->firstValue($row, ['FNumber', 'FStaffNumber']);
        $name = $this->firstValue($row, ['FName', 'FDataValue']);
        $useOrg = $this->firstValue($row, ['FUseOrgId.FNumber', 'FBizOrgId.FNumber']);
        $createOrg = $this->firstValue($row, ['FCreateOrgId.FNumber']);
        $forbidStatus = $this->firstValue($row, ['FForbidStatus']);
        // 金蝶内部 ID 优先作为稳定身份；任岗资料需额外区分部门和岗位。
        if ($kingdeeId !== '') {
            $identitySource = 'id:' . $kingdeeId;
        } elseif ($type === 'staff_assignments') {
            $identitySource = implode('|', [
                'staff:' . $this->firstValue($row, ['FStaffNumber']),
                'employee:' . $number,
                'dept:' . $this->firstValue($row, ['FDept.FNumber']),
                'position:' . $this->firstValue($row, ['FPosition.FNumber']),
                'org:' . $useOrg,
            ]);
        } else {
            $identitySource = 'number:' . $number . '|org:' . $useOrg;
        }
        if ($kingdeeId === '' && $number === '' && $useOrg === '') {
            $identitySource = json_encode(
                $row,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            ) ?: serialize($row);
        }

        $normalized = [
            'account_set' => $this->accountSet,
            'identity_key' => hash('sha256', $identitySource),
            'kingdee_id' => $kingdeeId !== '' ? $kingdeeId : null,
            'number' => $number !== '' ? $number : null,
            'name' => $name !== '' ? $name : null,
            'use_org_number' => $useOrg !== '' ? $useOrg : null,
            'create_org_number' => $createOrg !== '' ? $createOrg : null,
            'forbid_status' => $forbidStatus !== '' ? $forbidStatus : null,
            'is_active' => true,
            'source_payload' => json_encode(
                $row,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            ),
            'last_batch_id' => $batchId,
            'synced_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        foreach (self::TYPE_FIELDS[$type] ?? [] as $column => $keys) {
            $value = $this->firstValue($row, $keys);
            $normalized[$column] = $value !== '' ? $value : null;
        }

        return $normalized;
    }

    /**
     * 全量同步完成后将本次未出现的旧资料标记为失效，保留历史记录。
     */
    private function disableMissing(string $type, int $batchId, string $now): int
    {
        return $this->connection->table($this->tableFor($type))
            ->where('account_set', $this->accountSet)
            ->where(function ($query) use ($batchId) {
                $query->whereNull('last_batch_id')->orWhere('last_batch_id', '<>', $batchId);
            })
            ->where('is_active', true)
            ->update(['is_active' => false, 'updated_at' => $now]);
    }

    /**
     * 创建同步批次记录。
     */
    private function createBatch(string $type, string $filter, int $pageSize): int
    {
        return (int) $this->connection->table($this->batchesTable)->insertGetId([
            'batch_no' => (string) Str::uuid(),
            'account_set' => $this->accountSet,
            'data_type' => $type,
            'mode' => trim($filter) === '' ? 'full' : 'partial',
            'status' => 'running',
            'page_size' => $pageSize,
            'filter_string' => $filter !== '' ? $filter : null,
            'started_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 更新同步批次结果。
     */
    private function finishBatch(int $batchId, string $status, array $result, ?string $error = null): void
    {
        $this->connection->table($this->batchesTable)->where('id', $batchId)->update([
            'status' => $status,
            'pages' => (int) ($result['pages'] ?? 0),
            'received_count' => (int) ($result['received'] ?? 0),
            'created_count' => (int) ($result['created'] ?? 0),
            'updated_count' => (int) ($result['updated'] ?? 0),
            'disabled_count' => (int) ($result['disabled'] ?? 0),
            'error_message' => $error,
            'finished_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 分页过程中持续保存进度，避免进程异常时批次仍显示为零条。
     */
    private function updateBatchProgress(int $batchId, array $result): void
    {
        $this->connection->table($this->batchesTable)->where('id', $batchId)->update([
            'pages' => (int) ($result['pages'] ?? 0),
            'received_count' => (int) ($result['received'] ?? 0),
            'created_count' => (int) ($result['created'] ?? 0),
            'updated_count' => (int) ($result['updated'] ?? 0),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 从具名字段中读取第一个非空值。
     */
    private function firstValue(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return (string) $row[$key];
            }
        }

        return '';
    }

    /**
     * 获取指定基础资料类型的独立存储表。
     */
    private function tableFor(string $type): string
    {
        return $this->tables[$type];
    }
}
