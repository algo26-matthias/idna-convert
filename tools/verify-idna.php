<?php

declare(strict_types=1);

use Algo26\IdnaConvert\Exception\InvalidCharacterException;
use Algo26\IdnaConvert\NamePrep\IdnaData2008;
use Algo26\IdnaConvert\NamePrep\UnicodeRange;
use Algo26\IdnaConvert\ToIdn;
use Algo26\IdnaConvert\TranscodeUnicode\Ucs4Codec;

require dirname(__DIR__) . '/vendor/autoload.php';

$arguments = $_SERVER['argv'] ?? [];
if (count($arguments) !== 2) {
    fwrite(STDERR, "Usage: php tools/verify-idna.php IdnaTestV2.txt\n");
    exit(1);
}

$testPath = $arguments[1];
$expectedChecksum = '0236b75c5b20dfd857b3b5cf75509887959d6ab00d6c7bda9b7dc3df518c1fde';
if (hash_file('sha256', $testPath) !== $expectedChecksum) {
    fwrite(STDERR, "Unexpected checksum for the Unicode 18.0.0 IDNA test data.\n");
    exit(1);
}

$lines = file($testPath, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    fwrite(STDERR, "Unable to read the IDNA test data.\n");
    exit(1);
}

$codec = new Ucs4Codec();
$converter = new ToIdn(2008, true, true, true);
$decodeField = static function (string $field) use ($codec): string {
    if ($field === '""') {
        return '';
    }

    $decoded = preg_replace_callback(
        '/\\\\u([0-9A-Fa-f]{4})|\\\\x\{([0-9A-Fa-f]+)\}/',
        static function (array $matches) use ($codec): string {
            $value = $matches[1] !== '' ? $matches[1] : $matches[2];

            return $codec->encode([intval($value, 16)]);
        },
        $field,
    );
    if ($decoded === null) {
        throw new RuntimeException('Unable to decode an IDNA test field');
    }

    return $decoded;
};
$isStrictlyDisallowed = static function (string $value) use ($codec): bool {
    foreach ($codec->decode($value) as $codePoint) {
        if (UnicodeRange::contains($codePoint, IdnaData2008::IDNA_DISALLOWED)) {
            return true;
        }
    }

    return false;
};

$assertions = 0;
$skipped = 0;
foreach ($lines as $lineNumber => $line) {
    $data = trim(explode('#', $line, 2)[0]);
    if ($data === '') {
        continue;
    }
    $fields = array_pad(array_map('trim', explode(';', $data)), 7, '');

    try {
        $source = $decodeField($fields[0]);
        $toUnicode = $fields[1] === '' ? $source : $decodeField($fields[1]);
        $toAscii = $fields[3] === '' ? $toUnicode : $decodeField($fields[3]);
        if ($isStrictlyDisallowed($toUnicode)) {
            ++$skipped;
            continue;
        }
    } catch (InvalidCharacterException) {
        // PHP strings cannot represent the intentionally ill-formed Unicode cases in the test file.
        ++$skipped;
        continue;
    }

    $unicodeStatus = $fields[2];
    $asciiStatus = $fields[4] === '' ? $unicodeStatus : $fields[4];
    $expectsFailure = $asciiStatus !== '' && $asciiStatus !== '[]';

    $actual = null;
    $conversionFailure = null;
    try {
        $actual = $converter->convert($source);
    } catch (Throwable $exception) {
        $conversionFailure = $exception;
    }
    if ($expectsFailure && $conversionFailure === null) {
        throw new RuntimeException(
            sprintf('Line %d should fail but returned %s', $lineNumber + 1, json_encode($actual)),
        );
    }
    if (!$expectsFailure && $conversionFailure !== null) {
        throw new RuntimeException(
            sprintf('Line %d unexpectedly failed: %s', $lineNumber + 1, $conversionFailure->getMessage()),
            0,
            $conversionFailure,
        );
    }
    if (!$expectsFailure && $actual !== $toAscii) {
        throw new RuntimeException(
            sprintf(
                'Line %d returned %s, expected %s',
                $lineNumber + 1,
                json_encode($actual),
                json_encode($toAscii),
            ),
        );
    }
    ++$assertions;
}

printf("Verified %d strict IDNA2008 ToASCII cases; skipped %d incompatible cases.\n", $assertions, $skipped);
