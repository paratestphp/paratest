<?php
if (count($argv) < 1) {
    die("Usage: {$argv[0]} <number>");
}
$number = $argv[1];
$oldFiles = glob(__DIR__ . '/*Test.php');
foreach ($oldFiles as $test) {
    unlink($test);
}
$content = file_get_contents(__DIR__ . '/FastUnitTestTemplate.php');
for ($i = 1; $i <= $argv[1]; $i++) {
    $name = "FastUnit{$i}Test";
    $test = str_replace('FastUnitTestTemplate', $name, $content);
    file_put_contents(__DIR__ . "/{$name}.php", $test);
}
