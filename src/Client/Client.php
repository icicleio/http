<?php
namespace Icicle\Http\Client;

use Icicle\Dns\Connector\Connector;
use Icicle\Dns\Connector\ConnectorInterface;
use Icicle\Http\Message\Request;
use Icicle\Http\Message\RequestInterface;
use Icicle\Socket\Exception\FailureException;
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

    /**
     * @var int
     */
    private $cryptoMethod = self::DEFAULT_CRYPTO_METHOD;

    /**
     * @param \Icicle\Http\Client\RequesterInterface|null $requester
     * @param \Icicle\Dns\Connector\ConnectorInterface|null $connector
     */
    public function __construct(
        RequesterInterface $requester = null,
        ConnectorInterface $connector = null
    ) {
        $this->requester = $requester ?: new Requester();
        $this->connector = $connector ?: new Connector();
    }

    /**
     * {@inheritdoc}
     */
    public function request(
        $method,
        $uri,
        array $headers = [],
        ReadableStreamInterface $body = null,
        array $options = []
    ) {
        return $this->send(new Request($method, $uri, $headers, $body), $options);
    }

    /**
     * {@inheritdoc}
     */
    public function send(
        RequestInterface $request,
        array $options = []
    ) {
        $uri = $request->getUri();

        /** @var \Icicle\Socket\Client\ClientInterface $client */
        $client = (yield $this->connector->connect($uri->getHost(), $uri->getPort(), $options));

        if (!$client->isOpen()) {
            throw new FailureException('Could not connect to server.');
        }

        try {
            if ($uri->getScheme() === 'https') {
                $cryptoMethod = isset($options['crypto_method'])
                    ? (int) $options['crypto_method']
                    : self::DEFAULT_CRYPTO_METHOD;

                yield $client->enableCrypto($cryptoMethod);
            }

            yield $this->requester->request($client, $request, $options);
        } finally {
            $client->close();
        }
    }
}
