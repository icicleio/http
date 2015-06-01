<?php
namespace Icicle\Http\Client;

use Icicle\Http\Builder\Builder;
use Icicle\Http\Builder\BuilderInterface;
use Icicle\Http\Encoder\Encoder;
use Icicle\Http\Encoder\EncoderInterface;
use Icicle\Http\Message\RequestInterface;
use Icicle\Http\Reader\Reader;
use Icicle\Http\Reader\ReaderInterface;
use Icicle\Socket\Client\ClientInterface as SocketClientInterface;

class Requester implements RequesterInterface
{
    const DEFAULT_MAX_HEADER_SIZE = 8192;

    /**
     * @var \Icicle\Http\Reader\ReaderInterface
     */
    private $reader;

    /**
     * @var \Icicle\Http\Encoder\EncoderInterface
     */
    private $encoder;

    /**
     * @var \Icicle\Http\Builder\BuilderInterface
     */
    private $builder;

    /**
     * @var bool
     */
    private $allowPersistent = true;

    /**
     * @var int
     */
    private $maxHeaderSize = self::DEFAULT_MAX_HEADER_SIZE;

    /**
     * @var float|int
     */
    private $timeout = self::DEFAULT_TIMEOUT;

    /**
     * @param   mixed[] $options
     * @param   \Icicle\Http\Reader\ReaderInterface|null $reader
     * @param   \Icicle\Http\Builder\BuilderInterface|null $builder
     * @param   \Icicle\Http\Encoder\EncoderInterface|null $encoder
     */
    public function __construct(
        array $options = null,
        ReaderInterface $reader = null,
        BuilderInterface $builder = null,
        EncoderInterface $encoder = null
    ) {
        $this->timeout = isset($options['timeout']) ? (float) $options['timeout'] : self::DEFAULT_TIMEOUT;
        $this->allowPersistent = isset($options['allow_persistent']) ? (bool) $options['allow_persistent'] : true;
        $this->maxHeaderSize = isset($options['max_header_size'])
            ? (int) $options['max_header_size']
            : self::DEFAULT_MAX_HEADER_SIZE;

        $this->reader =  $reader  ?: new Reader();
        $this->encoder = $encoder ?: new Encoder();
        $this->builder = $builder ?: new Builder();
    }

    /**
     * @inheritdoc
     */
    public function request(SocketClientInterface $client, RequestInterface $request, $timeout = self::DEFAULT_TIMEOUT)
    {
        $request = $this->builder->buildOutgoingRequest($request, $this->timeout, $this->allowPersistent);

        yield $client->write($this->encoder->encodeRequest($request));

        $stream = $request->getBody();

        if ($stream->isReadable()) {
            yield $stream->pipe($client, false);
        }

        $response = (yield $this->reader->readResponse($client, $this->maxHeaderSize, $this->timeout));

        yield $this->builder->buildIncomingResponse($response);
    }
}