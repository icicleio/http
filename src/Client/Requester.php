<?php
namespace Icicle\Http\Client;

use Icicle\Coroutine\Coroutine;
use Icicle\Http\Builder\Builder;
use Icicle\Http\Builder\BuilderInterface;
use Icicle\Http\Encoder\Encoder;
use Icicle\Http\Encoder\EncoderInterface;
use Icicle\Http\Exception\InvalidArgumentException;
use Icicle\Http\Exception\MessageHeaderSizeException;
use Icicle\Http\Message\RequestInterface;
use Icicle\Http\Parser\Parser;
use Icicle\Http\Parser\ParserInterface;
use Icicle\Socket\Client\ClientInterface as SocketClientInterface;

class Requester implements RequesterInterface
{
    const DEFAULT_MAX_HEADER_SIZE = 8192;
    const DEFAULT_CRYPTO_METHOD = STREAM_CRYPTO_METHOD_TLS_CLIENT;

    /**
     * @var \Icicle\Http\Parser\ParserInterface
     */
    private $parser;

    /**
     * @var \Icicle\Http\Encoder\EncoderInterface
     */
    private $encoder;

    /**
     * @var \Icicle\Http\Builder\BuilderInterface
     */
    private $builder;

    /**
     * @var int
     */
    private $maxHeaderSize = self::DEFAULT_MAX_HEADER_SIZE;

    /**
     * @var int
     */
    private $cryptoMethod = self::DEFAULT_CRYPTO_METHOD;

    /**
     * @param   mixed[] $options
     */
    public function __construct(array $options = null)
    {
        $this->maxHeaderSize = isset($options['max_header_size'])
            ? (int) $options['max_header_size']
            : self::DEFAULT_MAX_HEADER_SIZE;

        $this->cryptoMethod = isset($options['crypto_method'])
            ? (int) $options['crypto_method']
            : self::DEFAULT_CRYPTO_METHOD;

        $this->parser = isset($options['parser']) ? $options['parser'] : new Parser();
        if (!$this->parser instanceof ParserInterface) {
            throw new InvalidArgumentException(
                'Message parser must be an instance of Icicle\Http\Parser\ParserInterface'
            );
        }

        $this->encoder = isset($options['encoder']) ? $options['encoder'] : new Encoder();
        if (!$this->encoder instanceof EncoderInterface) {
            throw new InvalidArgumentException(
                'Message encoder must be an instance of Icicle\Http\Encoder\EncoderInterface'
            );
        }

        $this->builder = isset($options['builder']) ? $options['builder'] : new Builder();
        if (!$this->builder instanceof BuilderInterface) {
            throw new InvalidArgumentException(
                'Message builder must be an instance of Icicle\Http\Builder\BuilderInterface'
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function request(SocketClientInterface $client, RequestInterface $request, $timeout = self::DEFAULT_TIMEOUT)
    {
        return new Coroutine($this->run($client, $request, $timeout));
    }

    /**
     * @param   \Icicle\Socket\Client\ClientInterface $client
     * @param   \Icicle\Http\Message\RequestInterface $request
     * @param   float|null $timeout
     *
     * @return  \Generator
     *
     * @throws  \Icicle\Http\Exception\MessageHeaderSizeException
     */
    public function run(SocketClientInterface $client, RequestInterface $request, $timeout = null)
    {
        if ($request->getUri()->getScheme() === 'https') {
            yield $client->enableCrypto($this->cryptoMethod);
        }

        $request = $this->builder->buildOutgoingRequest($request);

        $data = $this->encoder->encodeRequest($request);

        yield $client->write($data);

        $stream = $request->getBody();

        if ($stream->isReadable()) {
            yield $stream->pipe($client, false);
        }

        $data = '';

        do {
            $data .= (yield $client->read(null, "\n", $timeout));

            if (strlen($data) > $this->maxHeaderSize) {
                throw new MessageHeaderSizeException('Request header too large.');
            }
        } while (!preg_match("/\r?\n\r?\n$/", $data));

        $response = $this->parser->parseResponse($data, $client);

        $response = $this->builder->buildIncomingResponse($response);

        yield $response;
    }
}