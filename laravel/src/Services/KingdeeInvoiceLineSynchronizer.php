<?php

namespace Shengya\KingdeeLaravel\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Shengya\KingdeeLaravel\Models\KingdeeInvoiceApplication;
use Shengya\KingdeeLaravel\Models\KingdeeInvoiceApplicationLine;

/**
 * 将审核页的开票明细快照同步为组件正式子表。
 */
final class KingdeeInvoiceLineSynchronizer
{
    public function sync(KingdeeInvoiceApplication $application, array $details): void
    {
        $keptIds = [];
        foreach (array_values($details) as $index => $detail) {
            $line = KingdeeInvoiceApplicationLine::query()->updateOrCreate(
                [
                    'invoice_application_id' => (int) $application->id,
                    'line_no' => $index + 1,
                ],
                [
                    'kingdee_entry_id' => $detail['entry_id'] ?? null,
                    'material_number' => (string) $detail['material_number'],
                    'material_name' => $detail['material_name'] ?? null,
                    'quantity' => (float) $detail['quantity'],
                    'tax_rate' => (float) $detail['tax_rate'],
                    'tax_price' => (float) $detail['tax_price'],
                    'amount_with_tax' => round(
                        (float) $detail['quantity'] * (float) $detail['tax_price'],
                        2
                    ),
                    'amount_without_tax' => (float) $detail['amount_without_tax'],
                    'tax_amount' => (float) $detail['tax_amount'],
                    'settlement_category' => $detail['settlement_category'] ?? null,
                    'confirm_income' => (bool) ($detail['confirm_income'] ?? false),
                ]
            );
            $keptIds[] = (int) $line->id;
        }

        // 审核通过前允许服务部删减明细，子表必须与当前审核快照完全一致。
        KingdeeInvoiceApplicationLine::query()
            ->where('invoice_application_id', $application->id)
            ->when($keptIds, function ($query) use ($keptIds) {
                $query->whereNotIn('id', $keptIds);
            })
            ->delete();
    }

    /**
     * 从金蝶 View 结果补齐可递延行的分录内码。
     *
     * 递延只依赖“不确认收入”明细；确认收入行及金蝶返回的额外行不参与数量校验。
     */
    public function syncDeferrableEntryIds(KingdeeInvoiceApplication $application, array $viewPayload): void
    {
        $localLines = KingdeeInvoiceApplicationLine::query()
            ->where('invoice_application_id', $application->id)
            ->where('confirm_income', false)
            ->where(function ($query) {
                $query->whereNull('kingdee_entry_id')->orWhere('kingdee_entry_id', '');
            })
            ->orderBy('line_no')
            ->get();
        if ($localLines->isEmpty()) {
            return;
        }

        $kingdeeLines = $this->findEntityDetails($viewPayload);
        if ($kingdeeLines === []) {
            throw ValidationException::withMessages([
                'lines' => '金蝶开票申请单未返回明细分录，无法建立逐行递延关系',
            ]);
        }

        $entryIds = $this->matchEntryIds($localLines, $kingdeeLines);
        $application->getConnection()->transaction(function () use ($localLines, $entryIds) {
            foreach ($localLines as $line) {
                $line->forceFill(['kingdee_entry_id' => $entryIds[(int) $line->id]])->save();
            }
        });
    }

    /**
     * @param Collection<int, KingdeeInvoiceApplicationLine> $localLines
     * @return array<int, string> 组件明细 ID 到金蝶分录 ID 的映射
     */
    private function matchEntryIds(Collection $localLines, array $kingdeeLines): array
    {
        $usedIndexes = [];
        $entryIds = [];
        foreach ($localLines as $line) {
            $matchedIndex = $this->matchKingdeeLineIndex($line, $kingdeeLines, $usedIndexes);
            if ($matchedIndex === null) {
                throw ValidationException::withMessages([
                    'lines' => '第'.$line->line_no.'条不确认收入明细未找到对应的金蝶分录序号，无法建立源单关联',
                ]);
            }
            // Save 返回 FEntryID，View 在当前账套返回 AP_PAYABLEENTRY.Id。
            $entryId = $this->arrayValue($kingdeeLines[$matchedIndex], 'FEntryID')
                ?? $this->arrayValue($kingdeeLines[$matchedIndex], 'Id');
            if ($entryId === null || $entryId === '' || (int) $entryId <= 0) {
                throw ValidationException::withMessages([
                    'lines' => '第'.$line->line_no.'条金蝶开票明细缺少分录内码，无法递延',
                ]);
            }
            $usedIndexes[] = $matchedIndex;
            $entryIds[(int) $line->id] = (string) $entryId;
        }

        return $entryIds;
    }

    private function matchKingdeeLineIndex(
        KingdeeInvoiceApplicationLine $localLine,
        array $kingdeeLines,
        array $usedIndexes
    ): ?int {
        // 递延额度只读取组件明细金额；金蝶数据仅按 FSeq 定位下游源单分录 ID。
        foreach ($kingdeeLines as $index => $kingdeeLine) {
            if (! is_array($kingdeeLine) || in_array($index, $usedIndexes, true)) {
                continue;
            }
            // Save 返回 FSeq，View 在当前账套返回 Seq。
            $sequence = (int) ($this->arrayValue($kingdeeLine, 'FSeq')
                ?? $this->arrayValue($kingdeeLine, 'Seq')
                ?? ($index + 1));
            if ($sequence === (int) $localLine->line_no) {
                return (int) $index;
            }
        }

        return null;
    }

    /**
     * 兼容金蝶不同版本对字段名大小写和 Result 包装层级的差异。
     */
    private function findEntityDetails(array $payload): array
    {
        foreach ($payload as $key => $value) {
            // 不同金蝶入口使用业务实体标识或内部实体名返回同一组应收明细。
            $isInvoiceEntity = strcasecmp((string) $key, 'FEntityDetail') === 0
                || strcasecmp((string) $key, 'AP_PAYABLEENTRY') === 0;
            if ($isInvoiceEntity && is_array($value)) {
                if ($this->arrayValue($value, 'FEntryID') !== null
                    || $this->arrayValue($value, 'Id') !== null) {
                    return [$value];
                }

                return array_values(array_filter($value, 'is_array'));
            }
            if (is_array($value)) {
                $details = $this->findEntityDetails($value);
                if ($details !== []) {
                    return $details;
                }
            }
        }

        return [];
    }

    private function arrayValue(array $values, string $expectedKey)
    {
        foreach ($values as $key => $value) {
            if (strcasecmp((string) $key, $expectedKey) === 0) {
                return $value;
            }
        }

        return null;
    }

}
