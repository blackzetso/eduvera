<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class HelperFunctionTest extends TestCase
{
    /**
     * A basic unit test example.
     */
    public function test_say_hello_returns_correct_string()
    {
        $this->assertEquals('Hello, Mohamed', say_hello('Mohamed'));
    }
}
