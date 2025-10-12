<?php
header('Content-Type: application/json; charset=utf-8');

$phpDir   = __DIR__;                  // …/assets/php
$rootDir  = dirname($phpDir, 2);      // …/test11.1
$logDir   = $phpDir . '/logs';
$phpLog   = $logDir . '/php-errors.log';
$envProd  = $rootDir . '/.env.prod';
$autoload = $rootDir . '/vendor/autoload.php';

@mkdir($logDir, 0775, true);
@touch($phpLog);
@ini_set('log_errors','1');
@ini_set('error_log',$phpLog);
@ini_set('display_errors','0');

function parse_dotenv_simple($file) {
  $out = [];
  if (!is_readable($file)) return $out;
  foreach (file($file, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || $line[0] === ';') continue;
    $p = strpos($line,'=');
    if ($p===false) continue;
    $k = trim(substr($line,0,$p));
    $v = trim(substr($line,$p+1));
    if ((strlen($v)>=2) && (($v[0]==='"'&&$v[-1]==='"')||($v[0]==="'"&&$v[-1]==="'"))) $v=substr($v,1,-1);
    $out[$k]=$v;
  }
  return $out;
}

$env = parse_dotenv_simple($envProd);

echo json_encode([
  'ok'        => true,
  'php'       => PHP_VERSION,
  'sapi'      => PHP_SAPI,
  'paths'     => compact('phpDir','rootDir','logDir','phpLog','envProd','autoload'),
  'exists'    => [
    'logDir'   => is_dir($logDir),
    'phpLog'   => is_file($phpLog),
    'envProd'  => is_readable($envProd),
    'autoload' => is_readable($autoload),
  ],
  'writable'  => [
    'logDir'   => is_writable($logDir),
    'phpLog'   => is_writable($phpLog),
  ],
  'env_sample'=> array_intersect_key($env, array_flip([
    'APP_ENV','RECIPIENT_EMAIL','NOREPLY_EMAIL','SMTP_HOST','SMTP_PORT','SMTP_SECURE','SMTP_USER'
  ])),
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
