<?php

require_once dirname(__DIR__, 3).'/vendor/autoload.php';

if (getenv('DB_HOST') !== '127.0.0.1' || getenv('DB_PORT') !== '13316'
    || ! str_starts_with((string) getenv('DB_DATABASE'), 'clinicos_pr_')
    || ! str_contains((string) getenv('AUDIT_ROOT'), 'clinicos-readiness-')) {
    throw new RuntimeException('Audit requires the isolated MySQL instance.');
}

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->useStoragePath(getenv('AUDIT_ROOT').'/storage');
$app->usePublicPath(getenv('AUDIT_ROOT').'/public');

return $app;
