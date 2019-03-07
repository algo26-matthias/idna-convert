<?php

namespace Algo26\IdnaConvert\EncodingHelper;

class ToUtf8 implements EncodingHelperInterface
{
    public function convert(
        string $string,
        string $encoding = 'ISO-8859-1',
        bool $safeMode = false
    ) {
        $safe = ($safeMode) ? $string : false;

        if (strtoupper($encoding) === 'UTF-8' || strtoupper($encoding) === 'UTF8') {
            return $string;
        }

        if (strtoupper($encoding) === 'ISO-8859-1') {
            return \utf8_encode($string);
        }

        if (strtoupper($encoding) === 'WINDOWS-1252') {
            return \utf8_encode($this->mapWindows1252ToIso8859_1($string));
        }

        if (strtoupper($encoding) === 'UNICODE-1-1-UTF-7') {
            $encoding = 'utf-7';
        }

        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($string, 'UTF-8', strtoupper($encoding));

            if ($converted) {
                return $converted;
            }
        }

        if (function_exists('iconv')) {
            $converted = @iconv(strtoupper($encoding), 'UTF-8', $string);
            if ($converted) {
                return $converted;
            }
        }
        if (function_exists('libiconv')) {
            $converted = @libiconv(strtoupper($encoding), 'UTF-8', $string);
            if ($converted) {
                return $converted;
            }
        }

        return $safe;
    }

    /**
     * Special treatment for our guys in Redmond
     * Windows-1252 is basically ISO-8859-1 -- with some exceptions, which get accounted for here
     *
     * @param  string $string Your input in Win1252
     *
     * @return string  The resulting ISO-8859-1 string
     * @since 0.0.1
     */
    private function mapWindows1252ToIso8859_1($string = '')
    {
        if ($string === '') {
            return '';
        }

        $return = '';

        for ($i = 0; $i < strlen($string); ++$i) {
            $c = ord($string{$i});
            switch ($c) {
                case 129:
                    $return .= chr(252);
                    break;
                case 132:
                    $return .= chr(228);
                    break;
                case 142:
                    $return .= chr(196);
                    break;
                case 148:
                    $return .= chr(246);
                    break;
                case 153:
                    $return .= chr(214);
                    break;
                case 154:
                    $return .= chr(220);
                    break;
                case 225:
                    $return .= chr(223);
                    break;
                default:
                    $return .= chr($c);
            }
        }

        return $return;
    }
}
