<?php

class GuardScriptsTest extends PHPUnit_Framework_TestCase
{
    private static $guardPaths = array(
        'index.php',
        'js/index.php',
        'tests/index.php',
        'tests/phpstan/index.php',
        'translations/index.php',
        'upgrade/index.php',
    );

    private static $diagnosticPatterns = array(
        'Notice:',
        'Warning:',
        'Deprecated:',
        'Strict Standards:',
        'Parse error:',
        'Fatal error:',
    );

    public function testIndexInventoryMatchesTheSixGuards()
    {
        $found = array();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($GLOBALS['STATS_ROOT'], FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->getFilename() !== 'index.php') {
                continue;
            }
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($GLOBALS['STATS_ROOT']) + 1));
            if (strpos($path, '.git/') !== false || strpos($path, 'vendor/') !== false) {
                continue;
            }
            $found[] = $path;
        }
        sort($found);
        $this->assertSame(self::$guardPaths, $found);
    }

    public function testRootIndexExitsCleanly()
    {
        $this->runGuardChild('index.php');
    }

    public function testJsIndexExitsCleanly()
    {
        $this->runGuardChild('js/index.php');
    }

    public function testTestsIndexExitsCleanly()
    {
        $this->runGuardChild('tests/index.php');
    }

    public function testPhpstanIndexExitsCleanly()
    {
        $this->runGuardChild('tests/phpstan/index.php');
    }

    public function testTranslationsIndexExitsCleanly()
    {
        $this->runGuardChild('translations/index.php');
    }

    public function testUpgradeIndexExitsCleanly()
    {
        $this->runGuardChild('upgrade/index.php');
    }

    public function testModuleEntryExitsWhenPsVersionIsUndefined()
    {
        $this->runModuleGuardChild();
    }

    private function runGuardChild($relativePath)
    {
        $result = $this->runChildInclude($GLOBALS['STATS_ROOT'] . '/' . $relativePath, false);
        $this->assertInternalType('int', $result['exitCode']);
        $this->assertSame(0, $result['exitCode']);
        $this->assertSame('', $result['stdout']);
        $this->assertNoDiagnostics($result['stderr']);
    }

    private function runModuleGuardChild()
    {
        $result = $this->runChildInclude($GLOBALS['STATS_ROOT'] . '/statsdata.php', true);
        $this->assertSame(0, $result['exitCode']);
        $this->assertSame('', $result['stdout']);
        $this->assertNoDiagnostics($result['stderr']);
    }

    private function assertNoDiagnostics($text)
    {
        foreach (self::$diagnosticPatterns as $pattern) {
            $this->assertNotContains($pattern, $text, 'Unexpected diagnostic in child output');
        }
    }

    private function runChildInclude($target, $undefinePsVersion)
    {
        $tmpDir = sys_get_temp_dir() . '/statsdata-guard-' . getmypid() . '-' . mt_rand();
        mkdir($tmpDir, 0700, true);
        $wrapper = $tmpDir . '/wrapper.php';
        $body = '<?php require ' . var_export($target, true) . '; echo "SENTINEL";';
        file_put_contents($wrapper, $body);

        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );
        $cmd = 'php -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 '
            . escapeshellarg($wrapper);
        $proc = proc_open($cmd, $descriptors, $pipes);
        $this->assertInternalType('resource', $proc);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        unlink($wrapper);
        rmdir($tmpDir);

        return array(
            'exitCode' => (int) $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
        );
    }
}
