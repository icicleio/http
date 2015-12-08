<?php
namespace Icicle\Http\Client;

use Icicle\Dns\Connector\Connector;
use Icicle\Dns\Connector\DefaultConnector;
use Icicle\Http\Message\Request;
use Icicle\Http\Message\BasicRequest;
use Icicle\Socket\Exception\FailureException;
use Icicle\Stream\ReadableStream;

class Client
{
    /**
     * @var \Icicle\Http\Client\Requester
     */
    private $requester;

    /**
     * @var \Icicle\Dns\Connector\Connector
     */
    private $connector;

    /**
     * @var int
     */
    private $cryptoMethod = self::DEFAULT_CRYPTO_METHOD;

    /**
     * @param \Icicle\Http\Client\Requester|null $requester
     * @param \Icicle\Dns\Connector\Connector|null $connector
     */
    public function __construct(Requester $requester = null, Connector $connector = null)
    {
        $this->requester = $requester ?: new Requester();
        $this->connector = $connector ?: new DefaultConnector();
    }

    /**
     * {@inheritdoc}
     */
    public function request(
        $method,
        $uri,
        array $headers = [],
        ReadableStream $body = null,
        array $options = []
    ) {
        return $this->send(new BasicRequest($method, $uri, $headers, $body), $options);
    }

    /**
     * {@inheritdoc}
     */
    public function send(Request $request, array $options = [])
    {
        $uri = $request->getUri();

        /** @var \Icicle\Socket\Socket $socket */
        $socket = (yield $this->connector->connect($uri->getHost(), $uri->getPort(), $options));

        if (!$socket->isOpen()) {
            throw new FailureException('Could not connect to server.');
        }

        try {
            if ($uri->getScheme() === 'https') {
                $cryptoMethod = isset($options['crypto_method'])
                    ? (int) $options['crypto_method']
                    : self::DEFAULT_CRYPTO_METHOD;

                yield $socket->enableCrypto($cryptoMethod);
            }

            yield $this->requester->request($socket, $request, $options);
        } finally {
            $socket->close();
        }
    }
}
