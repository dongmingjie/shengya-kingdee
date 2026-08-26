<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 组件自有的金蝶调用审计表，用于记录每次认证和接口调用。
 */
class CreateKingdeeCallRecordsTable extends Migration
{
    public function up()
    {
        // 使用宿主项目配置的表名，确保迁移与调用记录器写入目标一致。
        $tableName = $this->recordMapping('table');
        $schema = Schema::connection($this->recordMapping('connection'));

        $schema->create($tableName, function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('trace_id')->index()->comment('一次业务调用链标识');
            $table->uuid('request_id')->unique()->comment('单次请求或重试标识');
            $table->string('account_set', 100)->nullable()->index()->comment('金蝶账套');
            $table->string('operation', 30)->index()->comment('login/query/view/save/submit/audit');
            $table->string('form_id', 100)->nullable()->index()->comment('金蝶FormId');
            $table->string('business_type', 100)->nullable()->index()->comment('宿主业务类型');
            $table->string('business_id', 100)->nullable()->index()->comment('宿主业务主键');
            $table->string('idempotency_key', 191)->nullable()->index()->comment('跨系统幂等键');
            $table->unsignedSmallInteger('attempt')->default(1)->comment('本次调用尝试次数');
            $table->string('status', 30)->default('running')->index()->comment('running/succeeded/failed/resolved');
            $table->unsignedInteger('duration_ms')->nullable()->comment('调用耗时毫秒');
            $table->unsignedSmallInteger('http_status')->nullable()->comment('HTTP状态码');
            $table->string('kingdee_code', 100)->nullable()->index()->comment('金蝶错误码');
            $table->longText('request_payload')->nullable()->comment('脱敏后的请求');
            $table->longText('response_payload')->nullable()->comment('脱敏后的响应');
            $table->string('exception_class', 255)->nullable()->index()->comment('标准异常类');
            $table->text('exception_message')->nullable()->comment('异常消息');
            $table->boolean('retryable')->default(false)->index()->comment('是否建议重试');
            $table->timestamp('resolved_at')->nullable()->index()->comment('异常解决时间');
            $table->unsignedBigInteger('resolved_by')->nullable()->comment('异常处理人，由宿主解释');
            $table->text('resolved_remark')->nullable()->comment('异常处理说明');
            $table->timestamps();

            $table->index(['business_type', 'business_id'], 'kd_call_business_idx');
            $table->index(['status', 'created_at'], 'kd_call_status_time_idx');
        });
    }

    public function down()
    {
        // 回滚时读取同一份宿主配置，删除对应的调用记录表。
        $tableName = $this->recordMapping('table');
        Schema::connection($this->recordMapping('connection'))->dropIfExists($tableName);
    }

    /**
     * 迁移只使用宿主项目显式提供的记录配置，不使用组件默认值。
     */
    private function recordMapping(string $key): string
    {
        $value = config('kingdee.records.' . $key);
        if (!is_string($value) || trim($value) === '') {
            throw new \RuntimeException('缺少金蝶调用记录配置映射：kingdee.records.' . $key);
        }

        return $value;
    }
}
