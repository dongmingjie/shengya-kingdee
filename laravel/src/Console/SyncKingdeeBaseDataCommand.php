<?php

namespace Shengya\KingdeeLaravel\Console;

use Illuminate\Console\Command;
use Shengya\KingdeeLaravel\Services\KingdeeBaseDataSyncService;

/**
 * 手工执行金蝶基础资料同步，可同步全部或指定一种类型。
 */
final class SyncKingdeeBaseDataCommand extends Command
{
    protected $signature = 'kingdee:sync-base-data
        {type=all : all或具体基础资料类型}
        {--filter= : 由后端管理员确认的金蝶FilterString}
        {--page-size= : 每页拉取数量}';

    protected $description = '分页拉取金蝶基础资料并按类型保存到独立基础资料表';

    public function handle(KingdeeBaseDataSyncService $sync): int
    {
        $type = (string) $this->argument('type');
        $filter = (string) ($this->option('filter') ?? '');
        $pageSizeOption = $this->option('page-size');
        $pageSize = $pageSizeOption === null ? null : (int) $pageSizeOption;

        if ($type === 'all') {
            if (trim($filter) !== '') {
                $this->error('同步 all 时不能传统一过滤条件，不同基础资料的字段不同。请指定具体类型。');
                return self::INVALID;
            }

            $results = $sync->syncAll($pageSize);
            $failed = false;
            foreach ($results as $dataType => $result) {
                $status = (string) ($result['status'] ?? 'failed');
                $this->line($dataType . ': ' . $status . '，接收 ' . (int) ($result['received'] ?? 0) . ' 条');
                $failed = $failed || $status === 'failed';
            }

            return $failed ? self::FAILURE : self::SUCCESS;
        }

        if (!in_array($type, $sync->types(), true)) {
            $this->error('不支持的类型。可选值：all、' . implode('、', $sync->types()));
            return self::INVALID;
        }

        $result = $sync->sync($type, $filter, $pageSize);
        $this->info(sprintf(
            '%s 同步完成：接收 %d，新增 %d，更新 %d，失效 %d。',
            $type,
            $result['received'],
            $result['created'],
            $result['updated'],
            $result['disabled']
        ));

        return self::SUCCESS;
    }
}
