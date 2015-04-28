<?php
namespace Icicle\Http\Client;

use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Connector\Connector;
use Icicle\Dns\Connector\ConnectorInterface;
use Icicle\Dns\Executor\Executor;
use Icicle\Dns\Executor\MultiExecutor;
use Icicle\Dns\Resolver\Resolver;
use Icicle\Http\Exception\RuntimeException;
use Icicle\Http\Message\Request;
use Icicle\Http\Message\RequestInterface;
use Icicle\Stream\ReadableStreamInterface;

class Client implements ClientInterface
{
    /**
     * @var \Icicle\Http\Client\RequesterInterface
     */
    private $requester;

    /**
     * @var \Icicle\Dns\Connector\ConnectorInterface
     */
    private $connector;

    public function __construct(RequesterInterface $requester = null, ConnectorInterface $connector = null)
    {
        $this->requester = $requester ?: new Requester();

        $this->connector = $connector;

        if (null === $this->connector) {
            $executor = new MultiExecutor();
            $executor->add(new Executor('8.8.8.8'));
            $executor->add(new Executor('8.8.4.4'));

            $this->connector = new Connector(new Resolver($executor));
        }
    }

    /**
     * @inheritdoc
     */
    public function request(
        $method,
        $uri,
        array $headers = null,
        ReadableStreamInterface $body = null,
        $timeout = RequesterInterface::DEFAULT_TIMEOUT,
        array $options = null
    ) {
        return $this->send(new Request($method, $uri, $headers, $body), $timeout, $options);
    }

    /**
     * @inheritdoc
     */
    public function send(
        RequestInterface $request,
        $timeout = RequesterInterface::DEFAULT_TIMEOUT,
        array $options = null
    ) {
        $request = $request->withHeader('Connection', 'close');

        return new Coroutine($this->run($request, $timeout, $options));
    }

    /**
     * @param   \Icicle\Http\Message\RequestInterface $request
     * @param   float|int|null $timeout
     * @param   mixed[] $options
     *
     * @return  \Generator
     *
     * @throws  \Icicle\Http\Exception\MessageHeaderSizeException
     * @throws  \Icicle\Http\Exception\RuntimeException
     */
    public function run(RequestInterface $request, $timeout = null, array $options = null)
    {
        $uri = $request->getUri();

        /** @var \Icicle\Socket\Client\ClientInterface $client */
        $client = (yield $this->connector->connect($uri->getHost(), $uri->getPort(), $options));

        if (!$client->isOpen()) {
            throw new RuntimeException('Could not connect to server.');
        }

        yield $this->requester->request($client, $request, $timeout, $options);
    }
}
