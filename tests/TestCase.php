<?php
namespace Icicle\Tests\Http;

/**
 * Abstract test class with method for creating callbacks.
 */
abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * Creates a callback that must be called $count times or the test will fail.
     *
     * @param   int $count Number of times the callback should be called.
     *
     * @return  callable Object that is callable and expects to be called the given number of times.
     */
    public function createCallback($count)
    {
        $mock = $this->getMock('Icicle\Tests\Http\Stub\CallbackStub');
        
        $mock->expects($this->exactly($count))
             ->method('__invoke');
        
        return $mock;
    }
}
