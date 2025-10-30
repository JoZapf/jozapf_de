<?php
declare(strict_types=1);

$base = __DIR__;
$log  = $base.'/logs/boot.log';
@mkdir($base.'/logs', 0775, true);
@file_put_contents($log, '['.date('c')."] health-check ping\n", FILE_APPEND);

header('Content-Type: application/json; charset=utf-8');

$autoload = realpath($base.'/../../vendor/autoload.php');
$envProd  = is_readable($base.'/.env.prod');
$envLocal = is_readable($base.'/.env.local');
$canWrite = is_writable($base.'/logs');

echo json_encode([
    'ok'           => true,
    'php'          => PHP_VERSION,
    'sapi'         => PHP_SAPI,
    'app_env'      => getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? null),
    'autoload'     => $autoload ?: 'MISSING',
    '.env.prod'    => $envProd ? 'OK' : 'MISSING',
    '.env.local'   => $envLocal ? 'OK' : 'MISSING',
    'logsWritable' => $canWrite ? 'YES' : 'NO',
    'bootLog'      => is_readable($log) ? 'OK' : 'MISSING',
], JSON_UNESCAPED_SLASHES);
