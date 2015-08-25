<?php
namespace Icicle\Tests\Http\Message;

use Icicle\Http\Message\Response;
use Icicle\Tests\Http\TestCase;

class ResponseTest extends TestCase
{
    /**
     * @return array Array of arrays of invalid status codes.
     */
    public function getInvalidStatusCodes()
    {
        return [
            [99], // Too Low
            [600], // Too High
            [null], // null
            [false], // boolean
            [3.14], // float
            [[404]], // array
            ['200+'], // string (non-numeric)
            [new \stdClass()], // object
        ];
    }

    /**
     * @return array Array of arrays of valid status codes and associated reasons.
     */
    public function getValidStatusCodes()
    {
        return [
            [200, 'OK'],
            [404, 'Not Found'],
            ['101', 'Switching Protocols'],
            ['500', 'Internal Server Error'],
        ];
    }

    /**
     * @dataProvider getInvalidStatusCodes
     * @expectedException \Icicle\Http\Exception\InvalidStatusException
     */
    public function testConstructWithInvalidStatus($code)
    {
        new Response($code);
    }

    /**
     * @dataProvider getValidStatusCodes
     */
    public function testConstructWithValidStatus($code, $reason)
    {
        $response = new Response($code);
        $this->assertSame((int) $code, $response->getStatusCode());
        $this->assertSame($reason, $response->getReasonPhrase());
    }

    /**
     * @dataProvider getValidStatusCodes
     */
    public function testConstructWithReason($code)
    {
        $reason = 'Custom Reason';

        $response = new Response($code, [], null, $reason);
        $this->assertSame((int) $code, $response->getStatusCode());
        $this->assertSame($reason, $response->getReasonPhrase());
    }

    /**
     * @dataProvider getValidStatusCodes
     */
    public function testWithStatus($code, $reason)
    {
        $response = new Response();
        $new = $response->withStatus($code);

        $this->assertNotSame($response, $new);
        $this->assertSame((int) $code, $new->getStatusCode());
        $this->assertSame($reason, $new->getReasonPhrase());
    }

    /**
     * @dataProvider getValidStatusCodes
     */
    public function testWithStatusWithReason($code)
    {
        $reason = 'Custom Reason';

        $response = new Response();
        $new = $response->withStatus($code, $reason);

        $this->assertNotSame($response, $new);
        $this->assertSame((int) $code, $new->getStatusCode());
        $this->assertSame($reason, $new->getReasonPhrase());
    }

    /**
     * @dataProvider getInvalidStatusCodes
     * @expectedException \Icicle\Http\Exception\InvalidStatusException
     */
    public function testWithStatusWithInvalidCode($code)
    {
        (new Response())->withStatus($code);
    }
}
