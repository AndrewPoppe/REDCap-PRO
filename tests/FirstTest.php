<?php

namespace YaleREDCap\REDCapPRO;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once __DIR__ . '/../../../redcap_connect.php';

class YourExternalModuleTest extends \ExternalModules\ModuleBaseTest
{
    function testYourMethod()
    {
        $expected = 'expected value';
        $actual1 = $this->module->yourMethod();

        // Shorter syntax without explicitly specifying "->module" is also supported.
        $actual2 = $this->yourMethod();

        $this->assertSame($expected, $actual1);
        $this->assertSame($expected, $actual2);
    }
}
