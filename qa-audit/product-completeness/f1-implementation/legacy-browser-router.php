<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$storage = getenv('LARAVEL_STORAGE_PATH');
if (getenv('DB_CONNECTION') !== 'sqlite' || ! str_starts_with(getenv('DB_DATABASE'), dirname($storage))
    || (! str_contains($storage, 'clinicos-browser-') && ! str_contains($storage, 'clinicos-qa-regression-'))) {
    throw new RuntimeException('F1 browser isolation guard');
}

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$public = str_starts_with($path, '/build/') ? getenv('F1_BROWSER_PUBLIC') : getenv('AUDIT_REPO').'/public';
$file = realpath($public.$path);
if ($path !== '/' && $file && is_file($file) && str_starts_with($file, realpath($public).DIRECTORY_SEPARATOR) && ! str_ends_with($file, '.php') && ! str_starts_with($path, '/storage/')) {
    $extension = pathinfo($file, PATHINFO_EXTENSION);
    header('Content-Type: '.(['js' => 'application/javascript', 'css' => 'text/css', 'png' => 'image/png', 'svg' => 'image/svg+xml', 'woff2' => 'font/woff2'][$extension] ?? 'application/octet-stream'));
    readfile($file);

    return;
}

require getenv('AUDIT_REPO').'/vendor/autoload.php';
$app = require getenv('AUDIT_REPO').'/bootstrap/app.php';
$app->addAbsoluteCachePathPrefix('C:');
$app->useStoragePath($storage);
$app->usePublicPath(getenv('F1_BROWSER_PUBLIC'));
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
if (config('database.default') !== 'sqlite' || ! str_starts_with(config('filesystems.disks.local.root'), $storage)) {
    throw new RuntimeException('F1 filesystem isolation guard');
}
$response = $kernel->handle($request = Request::capture());
$response->send();
$kernel->terminate($request, $response);
