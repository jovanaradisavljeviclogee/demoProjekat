<?php

namespace Tests\Unit;

use Domain\Hello;
use Tests\TestCase;

class HelloTest extends TestCase
{
    public function test_hello_returns_expected_message(): void
    {
        $hello = new Hello();

        $this->assertSame(
            'Hello from Domain',
            $hello->message()
        );
    }
}
