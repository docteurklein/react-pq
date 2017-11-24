<?php declare(strict_types=1);

namespace ReactPq;

use pq\Connection;
use React\EventLoop\LoopInterface;
use React\Stream\ThroughStream;

final Class Client
{
    private $loop;
    private $dsn;

    const polling_types = [
        Connection::POLLING_FAILED  => 'Connection::POLLING_FAILED',
        Connection::POLLING_READING => 'Connection::POLLING_READING',
        Connection::POLLING_WRITING => 'Connection::POLLING_WRITING',
        Connection::POLLING_OK      => 'Connection::POLLING_OK',
    ];

    public function __construct(LoopInterface $loop, string $dsn)
    {
        $this->loop = $loop;
        $this->dsn = $dsn;
    }

    public function query(string $query, array $params = [], array $types = [], callable $transform = null)
    {
        $c = new Connection($this->dsn, Connection::ASYNC);
        $c->defaultAutoConvert = 0; // no auto convert ? // TODO configurable
        $c->unbuffered = true; // very important for per-row streaming

        $stream = new ThroughStream($transform);

        $this->loop->addReadStream($c->socket, function($socketStream) use($c, $stream) {
            $this->pipeResult($c, $stream);
        });

        $this->loop->addWriteStream($c->socket, function($socketStream) use($c, $stream) {
            $this->pipeResult($c, $stream);
        });

        $this->exec($c, $query, $params, $types);

        return $stream;
    }

    private function pipeResult($c, $stream)
    {
        $result = $c->getResult();
        if (!$result) {
            return;
        }
        $row = $result->fetchRow();
        $stream->write($row);
    }

    private function exec($c, $query, $params, $types)
    {
        $this->loop->futureTick(function() use($c, $query, $params, $types) {
            $status = $c->poll();
            if ($status === Connection::POLLING_OK) {
                $c->execParamsAsync($query, $params, $types);
                return;
            }
            $this->exec($c, $query, $params, $types);
        });
    }
}
