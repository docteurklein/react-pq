<?php declare(strict_types=1);

namespace ReactPq;

use pq;
use React\EventLoop\LoopInterface;
use React\Stream\ThroughStream;

final Class Client
{
    private $loop;
    private $dsn;

    public function __construct(LoopInterface $loop, string $dsn)
    {
        $this->loop = $loop;
        $this->dsn = $dsn;
    }

    public function query(string $query, array $params = [], array $types = [], callable $transform = null)
    {
        $c = new pq\Connection($this->dsn, pq\Connection::ASYNC);
        $c->defaultAutoConvert = 0;
        $c->unbuffered = true;

        $stream = new ThroughStream($transform);

        $this->loop->addReadStream($c->socket, function($socketStream) use($c, $stream) {
            $result = $c->getResult();
            if (!$result) {
                return $stream->end();
            }
            $row = $result->fetchRow();
            $stream->write($row);
        });

        $this->pollConnectionStatus($c, function() use($c, $query, $params, $types) {
            $c->execParamsAsync($query, $params, $types);
        });

        return $stream;
    }

    private function pollConnectionStatus($c, callable $then)
    {
        $this->loop->futureTick(function() use($c, $then) {
            $status = $c->poll();
            switch ($status) {
                case pq\Connection::POLLING_FAILED:
                    throw new \Exception($c->errorMessage);
                case pq\Connection::POLLING_OK:
                    return $then();
                default:
                    return $this->pollConnectionStatus($c, $then);
            }
        });
    }
}

