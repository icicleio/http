<?php
namespace Icicle\Tests\Http\Encoder;

use Icicle\Http\Driver\Encoder\Http1Encoder;
use Icicle\Http\Message\BasicResponse;
use Icicle\Tests\Http\TestCase;
use Symfony\Component\Yaml\Yaml;

class Http1EncoderTest extends TestCase
{
    /**
     * @return array
     */
    public function getResponses()
    {
        return Yaml::parse(file_get_contents(dirname(dirname(__DIR__)) . '/data/responses/encoder.yml'));
    }

    /**
     * @dataProvider getResponses
     * @param string $filename
     * @param int $code
     * @param string $reason
     * @param string $protocolVersion
     * @param string[][] $headers
     */
    public function testEncodeResponse($filename, $code, $reason, $protocolVersion, $headers)
    {
        $encoder = new Http1Encoder();

        $encoded = $encoder->encodeResponse(new BasicResponse($code, $headers, null, $reason, $protocolVersion));

        $data = file_get_contents(dirname(dirname(__DIR__)) . '/data/' . $filename);
        list($data) = explode("\r\n\r\n", $data, 2);
        $data .= "\r\n\r\n";

        $this->assertEquals($data, $encoded);
    }
}
