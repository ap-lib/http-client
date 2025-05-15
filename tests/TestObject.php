<?php declare(strict_types=1);

namespace AP\HttpClient\Tests;

class TestObject
{
    public function __construct(
        public string $name,
        public int    $age,
    )
    {
    }

}
