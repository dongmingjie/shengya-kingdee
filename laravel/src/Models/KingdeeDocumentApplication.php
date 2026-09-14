<?php

namespace Shengya\KingdeeLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Shengya\Kingdee\Exceptions\ConfigurationException;

/**
 * 组件金蝶单据基类。
 *
 * 组件单据与调用审计必须分表保存：本模型承载单据状态和表单快照，
 * Save/Submit/Audit 的每次请求仍由 KingdeeCallRecord 单独记录。
 */
abstract class KingdeeDocumentApplication extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'float',
        'applicant_id' => 'integer',
        'bank_flow_id' => 'integer',
        'source_invoice_id' => 'integer',
        'source_receipt_id' => 'integer',
        'save_record_id' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * 子类返回 documents.tables 下的配置键。
     */
    abstract protected function tableMappingKey(): string;

    public function getTable()
    {
        $key = $this->tableMappingKey();
        $table = config('kingdee.documents.tables.'.$key);
        if (! is_string($table) || trim($table) === '') {
            throw new ConfigurationException('缺少金蝶业务单据表映射：kingdee.documents.tables.'.$key);
        }

        return $table;
    }

    public function getConnectionName()
    {
        $connection = config('kingdee.documents.connection');
        if (! is_string($connection) || trim($connection) === '') {
            throw new ConfigurationException('缺少金蝶业务单据数据库映射：kingdee.documents.connection');
        }

        return $connection;
    }

    /**
     * 读取组件单据上的流程快照，宿主审核扩展无需重复解析 JSON。
     */
    public function workflow(): array
    {
        $payload = is_string($this->response_payload)
            ? json_decode($this->response_payload, true)
            : $this->response_payload;

        return is_array($payload) && isset($payload['workflow']) && is_array($payload['workflow'])
            ? $payload['workflow']
            : [];
    }

    /**
     * 由组件统一保存单据阶段及对应的金蝶调用索引。
     */
    public function persistWorkflow(array $workflow, string $status, ?string $error = null): void
    {
        $this->forceFill([
            'status' => $status,
            'response_payload' => json_encode(
                ['workflow' => $workflow],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'save_record_id' => $workflow['save_record_id'] ?? $this->save_record_id,
            'kingdee_id' => $workflow['kingdee_id'] ?? $this->kingdee_id,
            'kingdee_number' => $workflow['kingdee_number'] ?? $this->kingdee_number,
            'save_trace_id' => $workflow['save_trace_id'] ?? $this->save_trace_id,
            'submit_trace_id' => $workflow['submit_trace_id'] ?? $this->submit_trace_id,
            'audit_trace_id' => $workflow['audit_trace_id'] ?? $this->audit_trace_id,
            'exception_message' => $error,
            'approved_at' => $status === 'approved' ? now() : $this->approved_at,
        ])->save();
    }

    /**
     * 单据通过 save_record_id 关联对应的 Save 执行记录。
     */
    public function saveCall()
    {
        return $this->belongsTo(KingdeeCallRecord::class, 'save_record_id');
    }
}
