<?php
namespace Algo26\IdnaConvert\TranscodeUnicode;

interface TranscodeUnicodeInterface
{
    public function convert(
        $data,
        string $from,
        string $to,
        bool $safeMode = false,
        int $safeCodepoint = 0xFFFC
    );
}
