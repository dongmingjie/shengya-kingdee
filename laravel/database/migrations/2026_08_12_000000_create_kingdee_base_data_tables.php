<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 参考 yuanbo2019 的分表方式，为每类金蝶基础资料创建独立存储表。
 */
class CreateKingdeeBaseDataTables extends Migration
{
    private const TYPES = [
        'organizations', 'customers', 'materials', 'accounts', 'departments', 'positions',
        'salesmen', 'employees', 'staff_assignments', 'payment_purposes', 'settlement_types',
        'bank_accounts', 'cash_accounts', 'projects',
    ];

    public function up()
    {
        $schema = Schema::connection($this->mapping('connection'));

        $schema->create($this->mapping('sync_batches_table'), function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('batch_no')->unique()->comment('同步批次号');
            $table->string('account_set', 100)->index()->comment('金蝶账套');
            $table->string('data_type', 50)->index()->comment('基础资料类型');
            $table->string('mode', 20)->default('full')->comment('full/partial');
            $table->string('status', 20)->default('running')->index()->comment('running/succeeded/failed');
            $table->unsignedInteger('page_size')->default(1000)->comment('每页数量');
            $table->unsignedInteger('pages')->default(0)->comment('已处理页数');
            $table->unsignedInteger('received_count')->default(0)->comment('金蝶返回数量');
            $table->unsignedInteger('created_count')->default(0)->comment('新增数量');
            $table->unsignedInteger('updated_count')->default(0)->comment('更新数量');
            $table->unsignedInteger('disabled_count')->default(0)->comment('本次标记失效数量');
            $table->text('filter_string')->nullable()->comment('本次后端生成的查询条件');
            $table->text('error_message')->nullable()->comment('同步失败信息');
            $table->timestamp('started_at')->nullable()->index()->comment('开始时间');
            $table->timestamp('finished_at')->nullable()->index()->comment('结束时间');
            $table->timestamps();

            $table->index(['account_set', 'data_type', 'created_at'], 'kd_base_batch_type_time_idx');
        });

        foreach ($this->tables() as $type => $tableName) {
            $schema->create($tableName, function (Blueprint $table) use ($type) {
                $table->bigIncrements('id');
                $table->string('account_set', 100)->index()->comment('金蝶账套');
                $table->string('identity_key', 64)->comment($type . '账套内稳定身份摘要');
                $table->string('kingdee_id', 191)->nullable()->index()->comment('金蝶内部ID');
                $table->string('number', 191)->nullable()->index()->comment('金蝶编码');
                $table->string('name', 255)->nullable()->index()->comment('显示名称');
                $table->string('use_org_number', 100)->nullable()->index()->comment('使用组织编码');
                $table->string('create_org_number', 100)->nullable()->index()->comment('创建组织编码');
                $table->string('forbid_status', 20)->nullable()->index()->comment('金蝶禁用状态');
                $table->boolean('is_active')->default(true)->index()->comment('是否仍在最近一次全量结果中');
                $table->json('source_payload')->comment('该类基础资料的金蝶原始具名字段');
                $table->unsignedBigInteger('last_batch_id')->nullable()->index()->comment('最近同步批次ID');
                $table->timestamp('synced_at')->nullable()->index()->comment('最近同步时间');
                $table->timestamps();

                $this->addTypeColumns($table, $type);
                $table->unique(['account_set', 'identity_key'], 'kd_' . $this->indexCode($type) . '_identity_uq');
                $table->index(['account_set', 'is_active', 'number'], 'kd_' . $this->indexCode($type) . '_option_idx');
            });
        }
    }

    public function down()
    {
        $schema = Schema::connection($this->mapping('connection'));

        // 分表之间没有外键，按配置逆序删除后再删除同步批次表。
        foreach (array_reverse($this->tables(), true) as $tableName) {
            $schema->dropIfExists($tableName);
        }
        $schema->dropIfExists($this->mapping('sync_batches_table'));
    }

    /**
     * 返回全部基础资料类型与独立表名的映射。
     */
    private function tables(): array
    {
        $tables = config('kingdee.base_data_storage.tables');
        if (!is_array($tables) || !$tables) {
            throw new RuntimeException('缺少金蝶基础资料分表配置：kingdee.base_data_storage.tables');
        }

        $resolved = [];
        foreach (self::TYPES as $type) {
            $tableName = $tables[$type] ?? null;
            if (!is_string($tableName) || trim($tableName) === '') {
                throw new RuntimeException('金蝶基础资料分表配置不合法：' . $type);
            }
            $resolved[$type] = $tableName;
        }

        return $resolved;
    }

    /**
     * 生成长度稳定的索引缩写，避免 MySQL 索引名超限。
     */
    private function indexCode(string $type): string
    {
        return substr(hash('crc32b', $type), 0, 8);
    }

    /**
     * 补充 yuanbo2019 同类表中常用的结构化业务字段。
     */
    private function addTypeColumns(Blueprint $table, string $type): void
    {
        if ($type === 'customers') {
            $table->string('customer_type_number', 100)->nullable()->index()->comment('客户类型编码');
            $table->string('group_number', 100)->nullable()->index()->comment('客户分组编码');
            $table->string('address', 255)->nullable()->comment('客户地址');
            $table->string('tax_register_code', 100)->nullable()->index()->comment('纳税登记号');
            $table->string('telephone', 50)->nullable()->comment('联系电话');
            $table->string('account_name', 100)->nullable()->comment('账户名称');
        } elseif ($type === 'materials') {
            $table->string('material_group_name', 191)->nullable()->comment('物料分组名称');
            $table->string('material_group_id', 100)->nullable()->index()->comment('物料分组ID');
            $table->string('category_number', 100)->nullable()->index()->comment('物料类别编码');
            $table->string('category_name', 191)->nullable()->comment('物料类别名称');
            $table->string('base_unit_number', 100)->nullable()->index()->comment('基础单位编码');
        } elseif ($type === 'departments') {
            $table->string('group_id', 100)->nullable()->index()->comment('部门分组ID');
            $table->string('group_name', 191)->nullable()->comment('部门分组名称');
        } elseif ($type === 'positions') {
            $table->string('department_number', 100)->nullable()->index()->comment('所属部门编码');
        } elseif ($type === 'salesmen') {
            $table->string('employee_number', 100)->nullable()->index()->comment('关联员工编码');
            $table->string('business_org_number', 100)->nullable()->index()->comment('业务组织编码');
            $table->string('department_number', 100)->nullable()->index()->comment('所属部门编码');
        } elseif ($type === 'staff_assignments') {
            $table->string('staff_number', 100)->nullable()->index()->comment('任岗编码');
            $table->string('employee_number', 100)->nullable()->index()->comment('员工编码');
            $table->string('department_number', 100)->nullable()->index()->comment('部门编码');
            $table->string('position_number', 100)->nullable()->index()->comment('岗位编码');
        }
    }

    private function mapping(string $key): string
    {
        $value = config('kingdee.base_data_storage.' . $key);
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException('缺少金蝶基础资料存储配置：kingdee.base_data_storage.' . $key);
        }

        return $value;
    }
}
