<?php declare(strict_types=1);

namespace AP\HttpClient\Tests;


use AP\HttpClient\AsyncPool;
use AP\HttpClient\Request;
use PHPUnit\Framework\TestCase;

final class MainTest extends TestCase
{
    public function testFirst(): void
    {
        $pool = new AsyncPool();

        $obj = new TestObject('Anton', 12);

        $request1 = (new Request("http://sc.local/test?op1"))
            ->setTimeout(0.2)
            ->setBodyJSON($obj);

        $request2 = (new Request("http://sc.local/test?op2"))
            ->setTimeout(0.1)
            ->setBodyJSON($obj);

        $name1 = $pool->addRequest($request1);
        $name2 = $pool->addRequest($request2);


        $request2_again = $pool->wait($name2);
        $request1_again = $pool->wait($name1);

        usleep(100000);

        $pool->exec();

        print_r($request1_again->getLog());
        print_r($request2_again->getLog());

        $this->assertTrue(true);
    }

}
