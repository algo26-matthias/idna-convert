<?php
function test_auto_loader($className)
{
    if (preg_match(
        '!^Algo26/IdnaConvert/(.+)$!',
        str_replace('\\', '/', $className),
        $subclass)
    ) {
        $path = sprintf('%s/../src/%s.php',__DIR__, $subclass[1]);
        require_once $path;
    }
}

spl_autoload_register('test_auto_loader');