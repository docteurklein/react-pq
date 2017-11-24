<?php

require(__DIR__.'/../vendor/autoload.php');

$loop = \React\EventLoop\Factory::create();

pcntl_signal(SIGINT, [$loop, 'stop']);
$loop->addPeriodicTimer(1, 'pcntl_signal_dispatch');

$pq = new \ReactPq\Client($loop, 'host=postgres user=postgres');
$http = new React\Http\Server(function($request) use($pq) {
    $stream = $pq->query('select generate_series(0, 10000)', [], [], function($data) {
        return print_r($data, true);
    });

    return new \React\Http\Response(200, ['Content-Type' => 'text/plain'], $stream);
});

$http->on('error', function (\Throwable $e) {
    throw $e;
});

$http->listen(new \React\Socket\Server('0.0.0.0:8080', $loop));

$loop->run();
