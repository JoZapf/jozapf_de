<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL);

require __DIR__ . '/../../vendor/autoload.php';

echo "autoload: ";
echo file_exists(__DIR__ . "/../../vendor/autoload.php") ? "OK\n" : "FEHLT\n";

echo "PHPMailer class: ";
echo class_exists('PHPMailer\\PHPMailer\\PHPMailer') ? "OK\n" : "NO\n";
