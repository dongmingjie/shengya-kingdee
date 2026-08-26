# Shengya Kingdee

`shengya/kingdee` 是金蝶 K3Cloud WebAPI 的 PHP SDK 与 Laravel 集成包，最低支持 PHP 7.4。

一个 Composer 包同时保留两层职责：

- `Shengya\Kingdee\*`：不依赖业务框架的金蝶客户端、单据入参、异常和脱敏。
- `Shengya\KingdeeLaravel\*`：Laravel 服务注册、调用记录、迁移发布和基础资料同步。

## 功能

- 登录并维护金蝶 Cookie 会话。
- 查询、查看、保存、提交和审核单据。
- 调用链 `trace_id` / `request_id` 与安全重试。
- 递归脱敏请求及响应中的敏感字段。
- Laravel 调用记录与失败日志降级。
- 14 类金蝶基础资料分页、幂等同步。
- 组件迁移只发布给宿主审阅，不自动执行。

## 环境

- PHP 7.4 或更高版本。
- Laravel 8.75、9、10 或 11（使用 Laravel 集成时）。

## 安装

```bash
composer require shengya/kingdee:^0.1
```

Laravel 会通过 Composer 自动发现 `KingdeeServiceProvider`。

## 纯 PHP 用法

```php
<?php

use Shengya\Kingdee\Config\KingdeeConfig;
use Shengya\Kingdee\KingdeeClient;
use Shengya\Kingdee\Recorder\NullCallRecorder;

$config = new KingdeeConfig([
    'enabled' => true,
    'base_url' => 'https://kingdee.example.com/K3Cloud/',
    'account_set' => 'your-account-set',
    'username' => 'your-api-user',
    'password' => 'your-api-password',
    'lcid' => 2052,
    'timeout' => 60,
    'connect_timeout' => 10,
    'auth' => [
        'format' => 1,
        'user_agent' => 'ShengyaKingdeeSdk',
        'version' => '1.0',
    ],
    'endpoints' => [
        'login' => 'Kingdee.BOS.WebApi.ServicesStub.AuthService.ValidateUser.common.kdsvc',
        'query' => 'Kingdee.BOS.WebApi.ServicesStub.DynamicFormService.ExecuteBillQuery.common.kdsvc',
        'view' => 'Kingdee.BOS.WebApi.ServicesStub.DynamicFormService.View.common.kdsvc',
        'save' => 'Kingdee.BOS.WebApi.ServicesStub.DynamicFormService.Save.common.kdsvc',
        'submit' => 'Kingdee.BOS.WebApi.ServicesStub.DynamicFormService.Submit.common.kdsvc',
        'audit' => 'Kingdee.BOS.WebApi.ServicesStub.DynamicFormService.Audit.common.kdsvc',
    ],
    'query' => ['limit' => 1000, 'start_row' => 0],
    'retry' => ['max_attempts' => 2, 'delay_ms' => 300],
]);

// 空记录器适合无框架示例；生产环境应注入日志或数据库记录器。
$client = new KingdeeClient($config, new NullCallRecorder());
$rows = $client->query('BD_Customer', ['FCUSTID', 'FNumber', 'FName'])->rows();
```

SDK 不内置账套、账号、密码或业务组织默认值。

## Laravel 配置

宿主项目需要提供 `config/kingdee.php`，主要配置节点为：

```php
<?php

return [
    'sdk' => [
        // 连接、账套、认证、端点、分页和重试配置。
    ],
    'operations' => [
        // 业务单据 FormId 及转换规则。
    ],
    'base_data' => [
        // 组织、客户、物料等 14 类 FormId。
    ],
    'base_data_storage' => [
        // 连接、分表、同步批次表和分页条数。
    ],
    'records' => [
        // 调用记录连接、表名和降级日志通道。
    ],
    'sensitive_keys' => [
        'password', 'token', 'authorization', 'cookie', 'bankaccount', 'mobile',
    ],
];
```

配置缺失或为空时组件会抛出 `ConfigurationException`，不会静默使用账套或业务默认值。

## 迁移

发布迁移文件：

```bash
php artisan vendor:publish \
  --provider="Shengya\\KingdeeLaravel\\KingdeeServiceProvider" \
  --tag=kingdee-migrations
```

组件不会自动执行迁移。发布后请先在宿主项目审阅文件，再按项目的发布流程处理。

## 基础资料同步

同步全部 14 类资料：

```bash
php artisan kingdee:sync-base-data all --page-size=200
```

只同步客户：

```bash
php artisan kingdee:sync-base-data customers --page-size=200
```

全量同步会将本批次未返回的历史资料标记为失效，不会删除。

## 调用方式

```php
<?php

use Shengya\Kingdee\Contracts\KingdeeClientInterface;

final class CustomerService
{
    private $kingdee;

    public function __construct(KingdeeClientInterface $kingdee)
    {
        $this->kingdee = $kingdee;
    }

    public function customers(): array
    {
        return $this->kingdee
            ->query('BD_Customer', ['FCUSTID', 'FNumber', 'FName'])
            ->rows();
    }
}
```

## 安全

- 不要将真实账套、账号、密码或 Cookie 提交到代码仓库。
- 金蝶 `FilterString` 必须由后端根据可信参数构造，禁止透传浏览器原始字符串。
- 写操作默认不重试，只有业务明确幂等时才可标记为可安全重试。

安全问题请使用代码托管平台的私密漏洞报告功能，不要在公开 Issue 中披露凭证。

## 版本

包内不声明固定 `version`，Packagist 从 `v0.1.0` 这类 Git 标签识别版本。变更记录见 [CHANGELOG.md](CHANGELOG.md)。

## 许可证

当前为 proprietary，详见 [LICENSE](LICENSE)。如需改为 MIT 或 Apache-2.0，必须由代码权利人明确确认后再发布。
