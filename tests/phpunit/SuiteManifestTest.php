<?php

class SuiteManifestTest extends PHPUnit_Framework_TestCase
{
    private static $expectedFiles = array(
        'tests/phpunit/GuardScriptsTest.php',
        'tests/phpunit/StatsdataAuthenticationTest.php',
        'tests/phpunit/StatsdataConfigTest.php',
        'tests/phpunit/StatsdataFrontHookTest.php',
        'tests/phpunit/StatsdataInstallTest.php',
        'tests/phpunit/StatsdataMetadataTest.php',
        'tests/phpunit/SuiteManifestTest.php',
        'tests/phpunit/UpgradeScriptsTest.php',
    );

    public function testHardCodedFileListAndTestCount()
    {
        $root = $GLOBALS['STATS_ROOT'];
        $files = glob($root . '/tests/phpunit/*Test.php');
        $relative = array();
        foreach ($files as $path) {
            $relative[] = substr($path, strlen($root) + 1);
        }
        sort($relative);
        $this->assertSame(self::$expectedFiles, $relative);

        $count = 0;
        foreach ($relative as $file) {
            $class = basename($file, '.php');
            require_once $root . '/' . $file;
            $reflection = new ReflectionClass($class);
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if (strpos($method->getName(), 'test') === 0) {
                    ++$count;
                }
            }
        }
        $this->assertSame(36, $count);
    }
}
