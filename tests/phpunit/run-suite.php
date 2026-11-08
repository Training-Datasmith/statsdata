<?php
/**
 * In-container PHPUnit runner: sorted pass plus seeded shuffles.
 */

$root = getenv('STATS_ROOT');
if (!$root) {
    $root = dirname(dirname(__DIR__));
}
$root = realpath($root);
if ($root === false) {
    fwrite(STDERR, "STATS_ROOT is not a directory\n");
    exit(1);
}

$phar = getenv('PHPUNIT_PHAR');
if (!$phar || !is_file($phar)) {
    fwrite(STDERR, "PHPUNIT_PHAR must point to verified phpunit-4.8.36.phar\n");
    exit(1);
}

$relativeFiles = array(
    'tests/phpunit/GuardScriptsTest.php',
    'tests/phpunit/StatsdataAuthenticationTest.php',
    'tests/phpunit/StatsdataConfigTest.php',
    'tests/phpunit/StatsdataFrontHookTest.php',
    'tests/phpunit/StatsdataInstallTest.php',
    'tests/phpunit/StatsdataMetadataTest.php',
    'tests/phpunit/SuiteManifestTest.php',
    'tests/phpunit/UpgradeScriptsTest.php',
);

$absoluteFiles = array();
foreach ($relativeFiles as $file) {
    $absoluteFiles[] = $root . '/' . $file;
}

$diagnosticPatterns = array(
    'Notice:',
    'Warning:',
    'Deprecated:',
    'Strict Standards:',
    'Parse error:',
    'Fatal error:',
);

$runDir = sys_get_temp_dir() . '/statsdata-phpunit-' . getmypid() . '-' . mt_rand();
mkdir($runDir, 0700, true);

$runs = array(
    array('label' => 'sorted', 'files' => $absoluteFiles),
    array('label' => 'shuffle-20261108', 'files' => shuffleWithSeed($absoluteFiles, 20261108)),
    array('label' => 'shuffle-20261109', 'files' => shuffleWithSeed($absoluteFiles, 20261109)),
);

$lastSummary = null;
foreach ($runs as $run) {
    $xmlPath = $runDir . '/phpunit-' . $run['label'] . '.xml';
    writePhpUnitXml($xmlPath, $root, $run['files']);
    $cmd = 'php -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 '
        . escapeshellarg($phar) . ' --configuration ' . escapeshellarg($xmlPath);
    $result = runCommand($cmd, $root);
    validateOutput($result, $diagnosticPatterns);
    $lastSummary = $result;
}

fwrite(STDOUT, "All runs passed.\n");
exit(0);

function shuffleWithSeed($files, $seed)
{
    $copy = $files;
    srand((int) $seed);
    shuffle($copy);

    return $copy;
}

function writePhpUnitXml($path, $root, $files)
{
    $bootstrap = $root . '/tests/phpunit/bootstrap.php';
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<phpunit bootstrap="' . htmlspecialchars($bootstrap, ENT_QUOTES, 'UTF-8') . '"';
    $xml .= ' colors="false" stopOnFailure="true" backupGlobals="false">' . "\n";
    $xml .= '  <testsuites>' . "\n";
    $xml .= '    <testsuite name="module-tests">' . "\n";
    foreach ($files as $file) {
        $xml .= '      <file>' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '</file>' . "\n";
    }
    $xml .= '    </testsuite>' . "\n";
    $xml .= '  </testsuites>' . "\n";
    $xml .= '</phpunit>' . "\n";
    file_put_contents($path, $xml);
}

function runCommand($cmd, $cwd)
{
    $descriptors = array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    );
    $proc = proc_open($cmd, $descriptors, $pipes, $cwd);
    if (!is_resource($proc)) {
        fwrite(STDERR, "Failed to start: $cmd\n");
        exit(1);
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);

    return array(
        'exit' => (int) $exit,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'combined' => $stdout . $stderr,
    );
}

function validateOutput($result, $diagnosticPatterns)
{
    if ($result['exit'] !== 0) {
        fwrite(STDERR, $result['combined']);
        exit(1);
    }
    foreach ($diagnosticPatterns as $pattern) {
        if (strpos($result['combined'], $pattern) !== false) {
            fwrite(STDERR, "Diagnostic found ($pattern):\n" . $result['combined']);
            exit(1);
        }
    }
    $badSummaries = array('Skipped', 'Incomplete', 'Risky', 'WARNINGS', 'There were');
    foreach ($badSummaries as $token) {
        if (stripos($result['combined'], $token) !== false) {
            fwrite(STDERR, "Forbidden summary token ($token):\n" . $result['combined']);
            exit(1);
        }
    }
    $lines = preg_split('/\r\n|\r|\n/', trim($result['combined']));
    $okLine = null;
    foreach (array_reverse($lines) as $line) {
        if (preg_match('/^OK \(\d+ tests, \d+ assertions\)$/', $line)) {
            $okLine = $line;
            break;
        }
    }
    if ($okLine === null) {
        fwrite(STDERR, "Missing exact OK summary line:\n" . $result['combined']);
        exit(1);
    }
    if (!preg_match('/^OK \(36 tests, \d+ assertions\)$/', $okLine)) {
        fwrite(STDERR, "Summary must be exact OK (36 tests, N assertions); got: $okLine\n");
        exit(1);
    }
}
