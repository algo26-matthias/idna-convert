<?php

namespace Algo26\IdnaConvert\EncodingHelper;

class FromUtf8 implements EncodingHelperInterface
{
    public function convert(
        string $string,
        string $encoding = 'ISO-8859-1',
        bool $safeMode = false
    ) {
        $safe = ($safeMode) ? $string : false;
        if (!$encoding) {
            $encoding = 'ISO-8859-1';
        }

        if (strtoupper($encoding) === 'UTF-8' || strtoupper($encoding) === 'UTF8') {
            return $string;
        }

        if (strtoupper($encoding) === 'ISO-8859-1') {
            return utf8_decode($string);
        }

        if (strtoupper($encoding) === 'WINDOWS-1252') {
            return self::mapIso8859_1ToWindows1252(utf8_decode($string));
        }

        if (strtoupper($encoding) === 'UNICODE-1-1-UTF-7') {
            $encoding = 'utf-7';
        }

        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($string, strtoupper($encoding), 'UTF-8');
            if ($converted) {
                return $converted;
            }
        }

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', strtoupper($encoding), $string);
            if ($converted) {
                return $converted;
            }
        }

        if (function_exists('libiconv')) {
            $converted = @libiconv('UTF-8', strtoupper($encoding), $string);
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
     * @param  string $string Your input in ISO-8859-1
     *
     * @return  string  The resulting Win1252 string
     * @since 0.0.1
     */
    private function mapIso8859_1ToWindows1252($string = '')
    {
        if ($string === '') {
            return '';
        }

        $return = '';
        for ($i = 0; $i < strlen($string); ++$i) {
            $c = ord($string{$i});
            switch ($c) {
                case 196:
                    $return .= chr(142);
                    break;
                case 214:
                    $return .= chr(153);
                    break;
                case 220:
                    $return .= chr(154);
                    break;
                case 223:
                    $return .= chr(225);
                    break;
                case 228:
                    $return .= chr(132);
                    break;
                case 246:
                    $return .= chr(148);
                    break;
                case 252:
                    $return .= chr(129);
                    break;
                default:
                    $return .= chr($c);
            }
        }

        return $return;
    }
}
