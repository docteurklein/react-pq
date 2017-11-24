<?php

require(__DIR__.'/../vendor/autoload.php');

$loop = \React\EventLoop\Factory::create();

pcntl_signal(SIGINT, [$loop, 'stop']);
$loop->addPeriodicTimer(1, 'pcntl_signal_dispatch');

$stdout = new \React\Stream\WritableResourceStream(STDOUT, $loop);

$pq = new \ReactPq\Client($loop, 'host=postgres user=postgres');
$loop->addPeriodicTimer(1, function() use($pq, $loop, $stdout) {
    $stream = $pq->query("select now(), s from generate_series(0, 1000000) s", [], [], function($data) {
        return $data[0].': '.$data[1]."\n";
    });
    $stream->pipe($stdout, ['end' => false]);
});

$loop->run();
