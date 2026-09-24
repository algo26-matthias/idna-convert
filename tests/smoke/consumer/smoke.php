<?php

declare(strict_types=1);

use Algo26\IdnaConvert\ToIdn;
use Algo26\IdnaConvert\ToUnicode;
use Composer\InstalledVersions;

require __DIR__ . '/vendor/autoload.php';

if (InstalledVersions::isInstalled('jakeasmith/http_build_url')) {
    throw new RuntimeException('The abandoned http_build_url package must not be installed.');
}

$unicode = 'https://üser:päßword@müller.example:8443/'
    . 'müller.example?next=müller.example#müller.example';
$ascii = 'https://üser:päßword@xn--mller-kva.example:8443/'
    . 'müller.example?next=müller.example#müller.example';

$encoded = (new ToIdn(2008))->convertUrl($unicode);
if ($encoded !== $ascii) {
    throw new RuntimeException(sprintf('Unexpected encoded URL: %s', $encoded));
}

$decoded = (new ToUnicode())->convertUrl($encoded);
if ($decoded !== $unicode) {
    throw new RuntimeException(sprintf('Unexpected decoded URL: %s', $decoded));
}

echo "Consumer smoke test passed.\n";
