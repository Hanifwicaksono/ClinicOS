<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$app = require __DIR__.'/application.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$queryTimes = [];
DB::listen(function (QueryExecuted $event) use (&$queryTimes): void {
    $queryTimes[] = $event->time;
});
$request = Request::capture();
$response = $kernel->handle($request);
$response->headers->set('X-Audit-Query-Count', (string) count($queryTimes));
$response->headers->set('X-Audit-DB-Time-Ms', (string) array_sum($queryTimes));
$response->headers->set('X-Audit-Query-Times', implode(',', $queryTimes));
$response->headers->set('X-Audit-Storage-Isolated', str_starts_with(storage_path(), getenv('AUDIT_ROOT')) ? 'true' : 'false');
$response->send();
$kernel->terminate($request, $response);
