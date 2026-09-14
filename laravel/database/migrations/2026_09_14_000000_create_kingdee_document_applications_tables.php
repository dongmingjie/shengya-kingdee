<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建组件自有的开票申请单表和收款单表。
 *
 * kd_call_records 只记录对金蝶的调用；审核中、驳回、重提等单据状态由独立组件表承载。
 */
class CreateKingdeeDocumentApplicationsTables extends Migration
{
    public function up()
    {
        $schema = Schema::connection($this->mapping('connection'));

        $schema->create($this->table('invoice_applications'), function (Blueprint $table) {
            $this->commonColumns($table);
            $table->unsignedBigInteger('source_receipt_id')->nullable()->index()->comment('来源组件收款单ID');
            $table->index(['project_number', 'status'], 'kd_invoice_project_status_idx');
        });

        $schema->create($this->table('receipts'), function (Blueprint $table) {
            $this->commonColumns($table);
            $table->unsignedBigInteger('bank_flow_id')->nullable()->index()->comment('宿主银行流水ID');
            $table->unsignedBigInteger('source_invoice_id')->nullable()->index()->comment('来源组件开票申请单ID');
            $table->index(['bank_flow_id', 'status'], 'kd_receipt_flow_status_idx');
            $table->index(['project_number', 'status'], 'kd_receipt_project_status_idx');
        });
    }

    public function down()
    {
        $schema = Schema::connection($this->mapping('connection'));
        $schema->dropIfExists($this->table('receipts'));
        $schema->dropIfExists($this->table('invoice_applications'));
    }

    /**
     * 两类单据共用完整的审核、表单快照和金蝶落单结果字段。
     */
    private function commonColumns(Blueprint $table): void
    {
        $table->bigIncrements('id');
        $table->uuid('trace_id')->unique()->comment('ERP申请唯一标识');
        $table->string('account_set', 100)->nullable()->index()->comment('金蝶账套');
        $table->string('form_id', 100)->index()->comment('目标金蝶FormId');
        $table->string('status', 30)->default('auditing')->index()->comment('业务审核及金蝶落单状态');
        $table->string('project_number', 191)->nullable()->index()->comment('项目编码');
        $table->string('organization_number', 100)->nullable()->index()->comment('合同主体/主业务组织编码');
        $table->string('counterparty_number', 191)->nullable()->index()->comment('客户或往来单位编码');
        $table->string('counterparty_name', 191)->nullable()->comment('客户或往来单位名称');
        $table->decimal('amount', 18, 2)->default(0)->comment('申请金额');
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

        $table->index(['status', 'created_at'], 'kd_document_status_time_idx');
    }

    private function table(string $key): string
    {
        return $this->mapping('tables.'.$key);
    }

    /**
     * 迁移严格读取宿主显式映射，避免组件猜测业务表名。
     */
    private function mapping(string $key): string
    {
        $value = config('kingdee.documents.'.$key);
        if (! is_string($value) || trim($value) === '') {
            throw new \RuntimeException('缺少金蝶业务单据配置映射：kingdee.documents.'.$key);
        }

        return $value;
    }
}
