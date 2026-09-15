<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建开票明细与递延转收入业务表。
 *
 * 单头、明细和金蝶调用记录必须分开：额度控制只能依赖可锁定的明细行，不能依赖 JSON 快照。
 */
class CreateKingdeeInvoiceLinesAndDeferredApplicationsTables extends Migration
{
    public function up()
    {
        $schema = Schema::connection($this->mapping('connection'));

        $schema->create($this->table('invoice_application_lines'), function (Blueprint $table) {
            $table->bigIncrements('id');
            // 显式使用短索引名，避免组件表名较长时超过 MySQL 64 字符限制。
            $table->unsignedBigInteger('invoice_application_id')->index('kd_inv_line_application_idx')->comment('组件开票申请单ID');
            $table->unsignedInteger('line_no')->comment('开票明细行号');
            $table->string('kingdee_entry_id', 100)->nullable()->index('kd_inv_line_entry_idx')->comment('金蝶应收单分录内码');
            $table->string('material_number', 191)->comment('物料编码快照');
            $table->string('material_name', 191)->nullable()->comment('物料名称快照');
            $table->decimal('quantity', 18, 4)->comment('数量');
            $table->decimal('tax_rate', 8, 4)->default(0)->comment('税率');
            $table->decimal('tax_price', 18, 6)->comment('含税单价');
            $table->decimal('amount_with_tax', 18, 2)->comment('税后/含税金额');
            $table->decimal('amount_without_tax', 18, 2)->comment('不含税金额');
            $table->decimal('tax_amount', 18, 2)->comment('税额');
            $table->unsignedInteger('settlement_category')->nullable()->comment('结算类别');
            $table->boolean('confirm_income')->default(true)->index('kd_inv_line_confirm_idx')->comment('是否确认收入');
            $table->timestamps();

            $table->unique(['invoice_application_id', 'line_no'], 'kd_invoice_line_no_unique');
            $table->index(
                ['invoice_application_id', 'confirm_income'],
                'kd_invoice_line_income_idx'
            );
        });

        $schema->create($this->table('deferred_income_applications'), function (Blueprint $table) {
            $this->documentColumns($table);
            $table->unsignedBigInteger('source_invoice_id')->index('kd_def_source_invoice_idx')->comment('来源组件开票申请单ID');
            $table->date('income_date')->comment('递延转收入日期');
            $table->index(['source_invoice_id', 'status'], 'kd_deferred_source_status_idx');
        });

        $schema->create($this->table('deferred_income_application_lines'), function (Blueprint $table) {
            $table->bigIncrements('id');
            // 这两个字段按 Laravel 默认规则生成的索引名会超过 MySQL 64 字符上限。
            $table->unsignedBigInteger('deferred_income_application_id')->index('kd_def_line_application_idx')->comment('组件递延申请单ID');
            $table->unsignedBigInteger('invoice_application_line_id')->index('kd_def_line_invoice_line_idx')->comment('来源组件开票明细ID');
            $table->unsignedInteger('line_no')->comment('递延明细行号');
            $table->string('kingdee_entry_id', 100)->nullable()->index('kd_def_line_entry_idx')->comment('金蝶递延单分录内码');
            $table->decimal('amount_with_tax', 18, 2)->comment('本次递延税后/含税金额');
            $table->decimal('amount_without_tax', 18, 2)->comment('本次递延不含税金额');
            $table->decimal('tax_amount', 18, 2)->comment('本次递延税额');
            $table->timestamps();

            $table->unique(
                ['deferred_income_application_id', 'invoice_application_line_id'],
                'kd_deferred_source_line_unique'
            );
        });
    }

    public function down()
    {
        $schema = Schema::connection($this->mapping('connection'));
        $schema->dropIfExists($this->table('deferred_income_application_lines'));
        $schema->dropIfExists($this->table('deferred_income_applications'));
        $schema->dropIfExists($this->table('invoice_application_lines'));
    }

    /**
     * 递延申请与其他组件业务单据使用相同的可恢复 Save/Submit/Audit 状态字段。
     */
    private function documentColumns(Blueprint $table): void
    {
        $table->bigIncrements('id');
        $table->uuid('trace_id')->unique()->comment('ERP申请唯一标识');
        $table->string('account_set', 100)->nullable()->index()->comment('金蝶账套');
        $table->string('form_id', 100)->index()->comment('目标金蝶FormId');
        $table->string('status', 30)->default('auditing')->index()->comment('业务审核及金蝶落单状态');
        $table->string('project_number', 191)->nullable()->index()->comment('项目编码');
        $table->string('organization_number', 100)->nullable()->index()->comment('主业务组织编码');
        $table->string('counterparty_number', 191)->nullable()->index()->comment('客户编码');
        $table->string('counterparty_name', 191)->nullable()->comment('客户名称');
        $table->decimal('amount', 18, 2)->default(0)->comment('递延申请税后/含税金额');
        $table->unsignedBigInteger('applicant_id')->nullable()->index()->comment('宿主申请人ID');
        $table->string('applicant_name', 100)->nullable()->comment('申请人名称快照');
        $table->longText('request_payload')->nullable()->comment('业务表单快照');
        $table->longText('response_payload')->nullable()->comment('审核流程快照');
        $table->unsignedBigInteger('save_record_id')->nullable()->index()->comment('金蝶Save调用记录ID');
        $table->string('kingdee_id', 100)->nullable()->index()->comment('金蝶单据内码');
        $table->string('kingdee_number', 100)->nullable()->index()->comment('金蝶单据编号');
        $table->uuid('save_trace_id')->nullable()->index()->comment('金蝶Save调用链标识');
        $table->uuid('submit_trace_id')->nullable()->index()->comment('金蝶Submit调用链标识');
        $table->uuid('audit_trace_id')->nullable()->index()->comment('金蝶Audit调用链标识');
        $table->text('exception_message')->nullable()->comment('最后一次业务处理异常');
        $table->timestamp('submitted_at')->nullable()->index()->comment('ERP提交审核时间');
        $table->timestamp('approved_at')->nullable()->index()->comment('金蝶审核通过时间');
        $table->timestamps();
        $table->index(['status', 'created_at'], 'kd_deferred_status_time_idx');
    }

    private function table(string $key): string
    {
        return $this->mapping('tables.'.$key);
    }

    private function mapping(string $key): string
    {
        $value = config('kingdee.documents.'.$key);
        if (! is_string($value) || trim($value) === '') {
            throw new \RuntimeException('缺少金蝶业务单据配置映射：kingdee.documents.'.$key);
        }

        return $value;
    }
}
