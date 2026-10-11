<?php

namespace ParaTest\Otr;

use SimpleXMLElement;
use SplFileInfo;

final readonly class Events
{
    public static function fromFile(SplFileInfo $logFile): self
    {
        assert($logFile->isFile() && 0 < (int) $logFile->getSize());

        $logFileContents = file_get_contents($logFile->getPathname());
        assert($logFileContents !== false);
$xml = new SimpleXMLElement($logFileContents);
var_dump($xml->children('e', true));
foreach ($xml->children('e', true) as $event) {
    var_dump($event);exit();
}
var_dump('end');exit;
print_r($xml);exit;
        return self::parseTestSuite(
            new SimpleXMLElement($logFileContents),
            true,
        );
    }
}