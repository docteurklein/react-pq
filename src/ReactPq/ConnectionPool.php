<?php declare(strict_types=1);

namespace ReactPq;

use pq\Connection;
use SplObjectStorage;

final Class ConnectionPool
{
    private $dsn;
    private $busy;
    private $ready;

    public function __construct(string $dsn)
    {
        $this->dsn = $dsn;
        $this->busy = new SplObjectStorage();
        $this->ready = new SplObjectStorage();
    }

    public function get(): Connection
    {
        if ($this->ready->count()) {
            $this->ready->rewind();
            return $this->ready->current();
        }
        $connection = new Connection($this->dsn, Connection::ASYNC);
        //$connection->defaultAutoConvert = 0; // TODO make configurable ?
        $connection->unbuffered = true; // very important for per-row streaming

        $this->busy->attach($connection);

        return $connection;
    }

    public function release(Connection $connection)
    {
        $connection->reset();
        unset($this->busy[$connection]);
        $this->ready->attach($connection);
    }
}
