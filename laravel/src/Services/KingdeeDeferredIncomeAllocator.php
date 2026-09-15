<?php

namespace Shengya\KingdeeLaravel\Services;

use Illuminate\Validation\ValidationException;
use Shengya\KingdeeLaravel\Models\KingdeeDeferredIncomeApplicationLine;
use Shengya\KingdeeLaravel\Models\KingdeeInvoiceApplicationLine;

/**
 * 对来源开票明细执行可递延额度锁定与计算。
 *
 * 调用方必须在 documents.connection 对应的数据库事务内使用本服务。
 */
final class KingdeeDeferredIncomeAllocator
{
    private const QUOTA_STATUSES = [
        'auditing',
        'pending_finance',
        'finance_failed',
        'approving',
        'kingdee_saved',
        'kingdee_submitted',
        'approved',
    ];

    /**
     * @return array<int, array<string, mixed>> 已校验并补齐税额的递延明细
     */
    public function prepare(int $invoiceApplicationId, array $allocations, ?int $excludeApplicationId = null): array
    {
        $lineIds = array_values(array_unique(array_map(function (array $item): int {
            return (int) ($item['invoice_application_line_id'] ?? 0);
        }, $allocations)));
        if (in_array(0, $lineIds, true) || count($lineIds) !== count($allocations)) {
            throw ValidationException::withMessages(['lines' => '递延明细不能重复且必须选择有效的开票明细']);
        }

        $lines = KingdeeInvoiceApplicationLine::query()
            ->where('invoice_application_id', $invoiceApplicationId)
            ->whereIn('id', $lineIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        if ($lines->count() !== count($lineIds)) {
            throw ValidationException::withMessages(['lines' => '所选开票明细不存在或不属于当前开票申请单']);
        }

        return array_map(function (array $allocation) use ($lines, $excludeApplicationId): array {
            $line = $lines->get((int) $allocation['invoice_application_line_id']);
            if ((bool) $line->confirm_income) {
                throw ValidationException::withMessages([
                    'lines' => '第'.$line->line_no.'条开票明细已确认收入，不能递延',
                ]);
            }

            // 财务口径以不含税金额递延；含税金额只按来源税率反算用于核对。
            $amountWithoutTax = round((float) $allocation['amount_without_tax'], 2);
            if ($amountWithoutTax <= 0) {
                throw ValidationException::withMessages(['lines' => '本次递延金额必须大于0']);
            }

            $usedQuery = KingdeeDeferredIncomeApplicationLine::query()
                ->where('invoice_application_line_id', $line->id)
                ->whereHas('application', function ($query) {
                    $query->whereIn('status', self::QUOTA_STATUSES);
                });
            if ($excludeApplicationId) {
                $usedQuery->where('deferred_income_application_id', '<>', $excludeApplicationId);
            }
            $used = round((float) $usedQuery->sum('amount_without_tax'), 2);
            $available = round((float) $line->amount_without_tax - $used, 2);
            if ($amountWithoutTax > $available + 0.001) {
                throw ValidationException::withMessages([
                    'lines' => '第'.$line->line_no.'条开票明细剩余可递延不含税金额为'.number_format(max(0, $available), 2),
                ]);
            }

            $taxRate = (float) $line->tax_rate;
            $taxAmount = round($amountWithoutTax * $taxRate / 100, 2);
            $amountWithTax = round($amountWithoutTax + $taxAmount, 2);

            return [
                'invoice_application_line_id' => (int) $line->id,
                'source_line_no' => (int) $line->line_no,
                'material_number' => (string) $line->material_number,
                'material_name' => (string) $line->material_name,
                'tax_rate' => $taxRate,
                'amount_with_tax' => $amountWithTax,
                'amount_without_tax' => $amountWithoutTax,
                'tax_amount' => $taxAmount,
                'source_entry_id' => $line->kingdee_entry_id,
            ];
        }, array_values($allocations));
    }

    public static function quotaStatuses(): array
    {
        return self::QUOTA_STATUSES;
    }
}
