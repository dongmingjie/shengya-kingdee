<?php

// 组件仓库没有独立 vendor 时复用宿主依赖，但组件类必须始终从当前源码加载。
$autoloadCandidates = [
    dirname(__DIR__).'/vendor/autoload.php',
    dirname(__DIR__, 3).'/shengya2022/vendor/autoload.php',
];
foreach ($autoloadCandidates as $autoload) {
    if (is_file($autoload)) {
        require $autoload;
        break;
    }
}

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Shengya\\KingdeeLaravel\\' => dirname(__DIR__).'/laravel/src/',
        'Shengya\\Kingdee\\Tests\\' => __DIR__.'/',
        'Shengya\\Kingdee\\' => dirname(__DIR__).'/src/',
    ];
    foreach ($prefixes as $prefix => $directory) {
        if (strpos($class, $prefix) !== 0) {
            continue;
        }
        $file = $directory.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (is_file($file)) {
            require $file;
        }

        return;
    }
}, true, true);
