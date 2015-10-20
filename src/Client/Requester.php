<?php
namespace Icicle\Http\Client;

use Icicle\Http\Builder\Builder;
use Icicle\Http\Builder\BuilderInterface;
use Icicle\Http\Encoder\Encoder;
use Icicle\Http\Encoder\EncoderInterface;
use Icicle\Http\Message\RequestInterface;
use Icicle\Http\Reader\Reader;
use Icicle\Http\Reader\ReaderInterface;
use Icicle\Stream;
use Icicle\Socket\SocketInterface;

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
     * @param mixed[] $options
     */
    public function __construct(array $options = [])
    {
        $this->reader = isset($options['reader']) && $options['reader'] instanceof ReaderInterface
            ? $options['reader']
            : new Reader($options);

        $this->builder = isset($options['builder']) && $options['builder'] instanceof BuilderInterface
            ? $options['builder']
            : new Builder($options);

        $this->encoder = isset($options['encoder']) && $options['encoder'] instanceof EncoderInterface
            ? $options['encoder']
            : new Encoder($options);
    }

    /**
     * {@inheritdoc}
     */
    public function request(SocketInterface $socket, RequestInterface $request, array $options = [])
    {
        $timeout = isset($options['timeout']) ? (float) $options['timeout'] : self::DEFAULT_TIMEOUT;
        $allowPersistent = isset($options['allow_persistent']) ? (bool) $options['allow_persistent'] : true;

        /** @var \Icicle\Http\Message\RequestInterface $request */
        $request = (yield $this->builder->buildOutgoingRequest($request, $timeout, $allowPersistent));

        yield $socket->write($this->encoder->encodeRequest($request));

        $stream = $request->getBody();

        if ($stream->isReadable()) {
            yield Stream\pipe($stream, $socket, false, 0, null, $timeout);
        }

        $response = (yield $this->reader->readResponse($socket, $timeout));

        yield $this->builder->buildIncomingResponse($response);
    }
}