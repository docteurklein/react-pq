<?php declare(strict_types=1);

namespace ReactPq;

use pq\Connection;
use pq\Result;
use React\EventLoop\LoopInterface;
use React\Stream\ThroughStream;

final Class Client
{
    private $loop;
    private $dsn;
    private $pool;

    const polling_types = [
        Connection::POLLING_FAILED  => 'Connection::POLLING_FAILED',
        Connection::POLLING_READING => 'Connection::POLLING_READING',
        Connection::POLLING_WRITING => 'Connection::POLLING_WRITING',
        Connection::POLLING_OK      => 'Connection::POLLING_OK',
    ];

    const result_statuses = [
        Result::EMPTY_QUERY    => 'Result::EMPTY_QUERY',
        Result::COMMAND_OK     => 'Result::COMMAND_OK',
        Result::TUPLES_OK      => 'Result::TUPLES_OK',
        Result::SINGLE_TUPLE   => 'Result::SINGLE_TUPLE',
        Result::COPY_OUT       => 'Result::COPY_OUT',
        Result::COPY_IN        => 'Result::COPY_IN',
        Result::BAD_RESPONSE   => 'Result::BAD_RESPONSE',
        Result::NONFATAL_ERROR => 'Result::NONFATAL_ERROR',
        Result::FATAL_ERROR    => 'Result::FATAL_ERROR',
    ];

    public function __construct(LoopInterface $loop, ConnectionPool $pool)
    {
        $this->loop = $loop;
        $this->pool = $pool;
    }

    public function query(string $query, array $params = [], array $types = [], callable $transform = null)
    {
        $connection = $this->pool->get();

        $stream = new ThroughStream($transform);

        $this->loop->addReadStream($connection->socket, function($socketStream) use($connection, $stream) {
            $this->pipeResult($connection, $stream);
        });

        $this->loop->addWriteStream($connection->socket, function($socketStream) use($connection, $stream) {
            $this->pipeResult($connection, $stream);
        });

        $this->exec($connection, $query, $params, $types);

        return $stream;
    }

    private function pipeResult($connection, $stream)
    {
        $result = $connection->getResult();
        if (!$result) {
            return;
        }
        $row = $result->fetchRow();
        if ($row === null) {
            $stream->end();
            $this->pool->release($connection);
            return;
        }
        $stream->write($row);
    }

    private function exec($connection, $query, $params, $types)
    {
        $this->loop->futureTick(function() use($connection, $query, $params, $types) {
            $status = $connection->poll();
            if ($status === Connection::POLLING_OK) {
                $connection->execParamsAsync($query, $params, $types);
                return;
            }
            $this->exec($connection, $query, $params, $types);
        });
    }
}
