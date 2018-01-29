<?php

namespace TBolier\RethinkQL\Response;

use TBolier\RethinkQL\Connection\ConnectionCursorInterface;
use TBolier\RethinkQL\Connection\ConnectionException;
use TBolier\RethinkQL\Query\MessageInterface;
use TBolier\RethinkQL\Types\Response\ResponseType;

class Cursor implements \Iterator
{
    /**
     * @var ConnectionCursorInterface
     */
    private $connection;

    /**
     * @var int
     */
    private $token;

    /**
     * @var ResponseInterface
     */
    private $response;

    /**
     * @var int
     */
    private $index;

    /**
     * @var int
     */
    private $size;

    /**
     * @var bool
     */
    private $isComplete;

    /**
     * @var MessageInterface
     */
    private $message;

    /**
     * @param ConnectionCursorInterface $connection
     * @param int $token
     * @param ResponseInterface $response
     * @param MessageInterface $message
     */
    public function __construct(ConnectionCursorInterface $connection, int $token, ResponseInterface $response, MessageInterface $message)
    {
        $this->connection = $connection;
        $this->token = $token;
        $this->addResponse($response);
        $this->message = $message;
    }

    /**
     * @param ResponseInterface $response
     */
    private function addResponse(ResponseInterface $response)
    {
        $this->index = 0;
        $this->isComplete = $response->getType() === ResponseType::SUCCESS_SEQUENCE;
        $this->size = \count($response->getData());
        $this->response = $response;
    }

    /**
     * @return void
     * @throws \Exception
     */
    private function seek(): void
    {
        while ($this->index === $this->size) {
            if ($this->isComplete) {
                return;
            }

            $this->request();
        }
    }

    /**
     * @return void
     * @throws \Exception
     */
    private function request(): void
    {
        try {
            $response = $this->connection->continueQuery($this->token);
            $this->addResponse($response);
        } catch (\Exception $e) {
            $this->isComplete = true;
            $this->close();

            throw $e;
        }
    }

    /**
     * @return void
     */
    private function close(): void
    {
        if (!$this->isComplete) {
            $this->connection->stopQuery($this->token);
            $this->isComplete = true;
        }

        $this->index = 0;
        $this->size = 0;
        $this->response = null;
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function current()
    {
        $this->seek();

        if ($this->valid()) {
            return $this->response->getData()[$this->index];
        }
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function next(): void
    {
        $this->index++;

        $this->seek();
    }

    /**
     * @inheritdoc
     */
    public function key(): int
    {
        return $this->index;
    }

    /**
     * @inheritdoc
     */
    public function valid(): bool
    {
        return (!$this->isComplete || ($this->index < $this->size));
    }

    /**
     * @inheritdoc
     * @throws ConnectionException
     */
    public function rewind(): void
    {
        $this->close();

        $this->addResponse($this->connection->rewindFromCursor($this->message));
    }
}