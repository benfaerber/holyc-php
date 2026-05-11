#!/usr/bin/env php
<?php

namespace Holyc;

/* ---------- source ---------- */
require __DIR__ . "/Stringable.php";
require __DIR__ . "/Collection.php";
require __DIR__ . "/Token.php";
require __DIR__ . "/LexToken.php";
require __DIR__ . "/Lexer.php";
require __DIR__ . "/Tree.php";
require __DIR__ . "/Ast.php";
require __DIR__ . "/Parser.php";
require __DIR__ . "/CodeGen.php";

/* ---------- test framework ---------- */
require __DIR__ . "/tests/TestCase.php";
require __DIR__ . "/tests/helpers.php";
require __DIR__ . "/tests/CollectionTest.php";
require __DIR__ . "/tests/LexerTest.php";
require __DIR__ . "/tests/ParserTest.php";
require __DIR__ . "/tests/IntegrationTest.php";
require __DIR__ . "/tests/CodeGenTest.php";
require __DIR__ . "/tests/EndToEndTest.php";

/* ---------- arg parsing ---------- *
 *   --filter=STRING    only run tests whose name contains STRING
 *   --no-color         disable ANSI colours (also honours NO_COLOR env)
 */
$args    = $argv ?? [];
$noColor = in_array('--no-color', $args, true) || getenv('NO_COLOR');
$filter  = null;
foreach ($args as $a) {
    if (str_starts_with($a, '--filter=')) {
        $filter = substr($a, strlen('--filter='));
    }
}

/* ---------- ANSI helpers ---------- */
$useColor = !$noColor && stream_isatty(STDOUT);
$c = fn (string $code, string $s) => $useColor ? "\033[{$code}m{$s}\033[0m" : $s;
$dim    = fn (string $s) => $c('2',  $s);
$bold   = fn (string $s) => $c('1',  $s);
$green  = fn (string $s) => $c('32', $s);
$red    = fn (string $s) => $c('31', $s);
$yellow = fn (string $s) => $c('33', $s);

/* ---------- run ---------- */
$suites = [
    new CollectionTest(),
    new LexerTest(),
    new ParserTest(),
    new IntegrationTest(),
    new CodeGenTest(),
    new EndToEndTest(),
];

$start         = microtime(true);
$totalPass     = 0;
$totalFail     = 0;
$totalSkip     = 0;
$totalSkipped  = 0;
$totalAsserts  = 0;
$failures      = [];

echo "\n" . $bold("HolyC PHP — running tests") . "\n\n";

foreach ($suites as $suite) {
    $suiteName = $suite->name();
    $run       = $suite->run($filter);
    $results   = $run['results'];
    $totalSkipped += $run['skipped'];

    if (empty($results)) continue;

    echo $bold($suiteName) . "\n";

    foreach ($results as $r) {
        if ($r['status'] === 'pass') {
            $totalPass++;
            echo "  " . $green("ok  ") . " " . $r['name'] . "\n";
        } elseif ($r['status'] === 'skip') {
            $totalSkip++;
            echo "  " . $yellow("skip") . " " . $r['name']
               . "  " . $dim("(" . $r['error'] . ")") . "\n";
        } else {
            $totalFail++;
            $failures[] = ['suite' => $suiteName] + $r;
            echo "  " . $red("FAIL") . " " . $r['name']
               . ($r['where'] ? "  " . $dim("(" . $r['where'] . ")") : '')
               . "\n";
            foreach (explode("\n", (string) $r['error']) as $line) {
                echo "       " . $yellow($line) . "\n";
            }
        }
    }
    $totalAsserts += $suite->assertionCount();
    echo "\n";
}

$elapsed = (microtime(true) - $start) * 1000;

/* ---------- summary ---------- */
echo str_repeat('-', 60) . "\n";
if ($totalFail === 0) {
    $skipNote = $totalSkip > 0 ? ", " . $yellow("$totalSkip skipped") : '';
    printf(
        "%s  %s tests, %s assertions%s  %s\n",
        $green($bold('PASS')),
        $totalPass,
        $totalAsserts,
        $skipNote,
        $dim(sprintf('(%.1f ms)', $elapsed))
    );
    if ($totalSkipped > 0) {
        echo $dim("$totalSkipped skipped via --filter\n");
    }
    exit(0);
}

printf(
    "%s  %d passed, %d failed  %s\n\n",
    $red($bold('FAIL')),
    $totalPass, $totalFail,
    $dim(sprintf('(%.1f ms)', $elapsed))
);

echo $bold("Failures:") . "\n";
foreach ($failures as $i => $f) {
    printf(
        "%2d) %s::%s  %s\n",
        $i + 1,
        $f['suite'],
        $f['method'],
        $f['where'] ? $dim("(" . $f['where'] . ")") : ''
    );
    foreach (explode("\n", (string) $f['error']) as $line) {
        echo "    " . $yellow($line) . "\n";
    }
    echo "\n";
}
exit(1);
