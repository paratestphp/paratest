<?php
$command = "'1asd' '2asdasd' '' '3asdasd'";
preg_match_all('/\'([^\']*)\'[ ]?/', $command, $matches);
var_export($matches);