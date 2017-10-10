<?php

require(__DIR__.'/../vendor/autoload.php');

$loop = \React\EventLoop\Factory::create();

pcntl_signal(SIGINT, [$loop, 'stop']);

$client = new \ReactPq\Client($loop, 'host=postgres user=postgres');
$stream = $client->query('select generate_series(0, 10000)', [], [], function($data) {
    return $data[0]."\n";
});

$stdout = new \React\Stream\WritableResourceStream(STDOUT, $loop);
$stream->pipe($stdout);

$loop->run();
