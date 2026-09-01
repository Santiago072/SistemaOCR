<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Container;

class ContainerTest extends TestCase
{
    public function testBindAndResolveSingleton(): void
    {
        $container = new Container();
        $dummyObj = new \stdClass();
        $dummyObj->name = "OCR Container";

        $container->singleton('dummy', fn() => $dummyObj);

        $resolved = $container->make('dummy');
        $this->assertSame($dummyObj, $resolved);
        $this->assertEquals("OCR Container", $resolved->name);
    }
}
