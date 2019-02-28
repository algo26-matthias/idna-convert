<?php
namespace Algo26\IdnaConvert\Interfaces;

interface UnicodeTranscoderInterface
{
    public static function convert($data, $from, $to, $safe_mode = false, $safe_char = 0xFFFC);

    public static function utf8_ucs4array($input);

    public static function ucs4array_utf8($input);

    public static function utf7imap_ucs4array($input);

    public static function utf7_ucs4array($input, $sc = '+');

    public static function ucs4array_utf7imap($input);

    public static function ucs4array_utf7($input, $sc = '+');

    public static function ucs4array_ucs4($input);

    public static function ucs4_ucs4array($input);
}
