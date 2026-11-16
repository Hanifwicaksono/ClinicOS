<?php

use Illuminate\Foundation\Http\Events\RequestHandled;
use PHPUnit\Event\Facade;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;

require_once dirname(__DIR__, 2).'/vendor/autoload.php';

$routeEvidence = dirname(__DIR__).'/evidence/route-requests.jsonl';
file_put_contents($routeEvidence, '');

Facade::instance()->registerSubscriber(new class($routeEvidence) implements PreparedSubscriber
{
    public function __construct(private string $path) {}

    public function notify(Prepared $event): void
    {
        if (! app()->bound('events')) {
            return;
        }

        app('events')->listen(RequestHandled::class, function (RequestHandled $handled) use ($event): void {
            $route = $handled->request->route();
            if ($route === null) {
                return;
            }

            file_put_contents($this->path, json_encode([
                'test' => $event->test()->id(),
                'name' => $route->getName(),
                'domain' => $route->getDomain(),
                'uri' => $route->uri(),
                'method' => $handled->request->method(),
                'status' => $handled->response->getStatusCode(),
            ], JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND | LOCK_EX);
        });
    }
});
