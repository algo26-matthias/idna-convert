<?php

namespace Algo26\IdnaConvert\Test\unit;

use Algo26\IdnaConvert\Exception\InvalidCharacterException;
use Algo26\IdnaConvert\Exception\InvalidIdnVersionException;
use Algo26\IdnaConvert\NamePrep\NamePrep;
use Algo26\IdnaConvert\TranscodeUnicode\TranscodeUnicode;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Algo26\IdnaConvert\NamePrep\CaseFolding
 * @covers \Algo26\IdnaConvert\NamePrep\IdnaProcessor2008
 * @covers \Algo26\IdnaConvert\NamePrep\NamePrep
 * @covers \Algo26\IdnaConvert\NamePrep\NamePrepProcessor2003
 * @covers \Algo26\IdnaConvert\NamePrep\UnicodeNormalizer
 * @covers \Algo26\IdnaConvert\NamePrep\UnicodeRange
 * @covers \Algo26\IdnaConvert\TranscodeUnicode\ByteLengthTrait
 * @covers \Algo26\IdnaConvert\TranscodeUnicode\TranscodeUnicode
 */
class NamePrepTest extends TestCase
{
    /** @var TranscodeUnicode */
    private $uctc;

    /** @var NamePrep */
    private $namePrep2003;

    public function setup(): void
    {
        $this->uctc = new TranscodeUnicode();
        $this->namePrep2003 = new NamePrep(2003);
    }

    public function testInvalidIdnVersion()
    {
        $this->expectException(InvalidIdnVersionException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('IDN version must be either 2003 or 2008');
        new NamePrep(1999);
    }

    /** @dataProvider providerIdna2008Mappings */
    public function testIdna2008CompatibilityMapping(array $input, array $expected): void
    {
        self::assertSame($expected, (new NamePrep(2008))->do($input));
    }

    /** @dataProvider providerValidIdna2008Contexts */
    public function testValidIdna2008Contexts(array $input): void
    {
        self::assertSame($input, (new NamePrep(2008))->do($input));
    }

    /** @dataProvider providerInvalidIdna2008Labels */
    public function testInvalidIdna2008LabelsAreRejected(array $input): void
    {
        self::expectException(InvalidCharacterException::class);
        self::expectExceptionCode(101);

        (new NamePrep(2008))->do($input);
    }

    public function testDirectlyProhibitedCharacterReportsItsCategory(): void
    {
        self::expectException(InvalidCharacterException::class);
        self::expectExceptionCode(101);
        self::expectExceptionMessage('Prohibited output U+00000020');

        $this->namePrep2003->do([0xA0]);
    }

    /**
     * @dataProvider providerHangulComposition
     */
    public function testHangulCompositionHonoursUnicodeBoundaries(array $input, array $expected): void
    {
        self::assertSame($expected, $this->namePrep2003->do($input));
    }

    /**
     * @dataProvider providerProhibitedRangeBoundaries
     */
    public function testProhibitedRangeBoundariesReportTheirCategory(int $codePoint): void
    {
        self::expectException(InvalidCharacterException::class);
        self::expectExceptionCode(102);

        $this->namePrep2003->do([$codePoint]);
    }

    /**
     * @param array|string $from provided original string
     * @param array|string $expectedTo expected result
     *
     * @dataProvider providerMapping2003
     */
    public function testSuccess2003($from, $expectedTo)
    {
        if (!is_array($from)) {
            $from = $this->utf8ToUcs($from);
        }
        if (!is_array($expectedTo)) {
            $expectedTo = $this->utf8ToUcs($expectedTo);
        }

        $to = $this->namePrep2003->do($from);

        $this->assertEquals(
            $expectedTo,
            $to,
            sprintf(
                'Sequences "%s" and "%s" do not match',
                $this->ucsToUtf8($expectedTo),
                $this->ucsToUtf8($to)
            )
        );
    }

    /**
     * @param array|string $sequence as UTF-8 string or UCS-4 array
     *
     * @dataProvider providerProhibited
     */
    public function testProhibited($sequence)
    {
        if (!is_array($sequence)) {
            $sequence = $this->utf8ToUcs($sequence);
        }

        $this->expectException(InvalidCharacterException::class);
        $this->namePrep2003->do($sequence);
    }

    public static function providerMapping2003(): array
    {
        return [
            [
                [
                    0x61, 0xAD, 0x34F, 0x1806, 0x180B, 0x180C, 0x180D, 0x200B, 0x200C,
                    0x200D, 0x2060, 0xFE00, 0xFE01, 0xFE02, 0xFE03, 0xFE04, 0xFE05, 0xFE06, 0xFE07,
                    0xFE08, 0xFE09, 0xFE0A, 0xFE0B, 0xFE0C, 0xFE0D, 0xFE0E, 0xFE0F, 0xFEFF, 0x61
                ],
                [
                    0x61, 0x61
                ]
            ],
            [
                'CAFFEE-Del-maR', 'caffee-del-mar',
            ],
            [
                'ß', 'ss',
            ],
            [
                [0x130], [0x69, 0x307]
            ],
            [
                [0x0143], [0x144]
            ],
            [
                [0x2121, 0x33C6, 0x1D7BB], [116, 101, 108, 99, 8725, 107, 103, 963]
            ],
            [
                [0x1FB7], [0x1FB6, 0x3B9]
            ],
            [
                [0x6A, 0x30C, 0xAA], [0x1F0, 0x61]
            ],
            [
                "J̌", [0x1F0]
            ],
            [
                '̈́ͅ', [0x390]
            ],
            [
                [0xFEFF], ''
            ],
            [
                [0x221], 'ȡ'
            ],
            [
                [0x627, 0x31, 0x628], [0x627, 0x31, 0x628]
            ]
        ];
    }

    public static function providerIdna2008Mappings(): array
    {
        return [
            'case mapping' => [[0x41, 0xC4], [0x61, 0xE4]],
            'sharp s deviation is preserved' => [[0xDF], [0xDF]],
            'capital sharp s maps to sharp s' => [[0x1E9E], [0xDF]],
            'final sigma deviation is preserved' => [[0x3C2], [0x3C2]],
            'ignored soft hyphen' => [[0x61, 0xAD, 0x62], [0x61, 0x62]],
        ];
    }

    public static function providerValidIdna2008Contexts(): array
    {
        return [
            'Catalan middle dot' => [[0x6C, 0xB7, 0x6C]],
            'Greek lower numeral sign' => [[0x375, 0x3B1]],
            'Hebrew punctuation' => [[0x5D0, 0x5F3]],
            'Hebrew double punctuation' => [[0x5D0, 0x5F4]],
            'Katakana middle dot' => [[0x30AB, 0x30FB]],
            'joiner after virama' => [[0x915, 0x94D, 0x200D, 0x937]],
            'non-joiner in Arabic joining context' => [[0x628, 0x200C, 0x628]],
            'non-joiner skips transparent joining types' => [[0x628, 0x64B, 0x200C, 0x64B, 0x628]],
            'right-to-left label ending in European digits' => [[0x5D0, 0x31]],
            'right-to-left label ending in Arabic digits' => [[0x627, 0x660]],
            'right-to-left label ending in a non-spacing mark' => [[0x5D0, 0x5B0]],
        ];
    }

    public static function providerInvalidIdna2008Labels(): array
    {
        return [
            'disallowed symbol' => [[0x2221]],
            'source-disallowed control' => [[0x80]],
            'leading combining mark' => [[0x301, 0x61]],
            'middle dot outside Catalan context' => [[0x61, 0xB7, 0x61]],
            'middle dot only has a left l' => [[0x6C, 0xB7, 0x61]],
            'middle dot only has a right l' => [[0x61, 0xB7, 0x6C]],
            'middle dot at label start' => [[0xB7, 0x6C]],
            'middle dot at label end' => [[0x6C, 0xB7]],
            'Greek sign without Greek follower' => [[0x375, 0x61]],
            'Greek sign at label end' => [[0x3B1, 0x375]],
            'Hebrew punctuation without Hebrew predecessor' => [[0x61, 0x5F3]],
            'Hebrew double punctuation without Hebrew predecessor' => [[0x61, 0x5F4]],
            'Hebrew punctuation at label start' => [[0x5F3, 0x5D0]],
            'Hebrew double punctuation at label start' => [[0x5F4, 0x5D0]],
            'Katakana middle dot without Japanese script' => [[0x30FB]],
            'joiner without virama' => [[0x61, 0x200D, 0x62]],
            'non-joiner without joining context' => [[0x61, 0x200C, 0x62]],
            'non-joiner missing left joining character' => [[0x200C, 0x628]],
            'non-joiner missing right joining character' => [[0x628, 0x200C]],
            'mixed Arabic digit sets' => [[0x627, 0x660, 0x6F0]],
            'mixed Arabic and European digits in RTL label' => [[0x627, 0x660, 0x31]],
            'RTL character in LTR label' => [[0x61, 0x5D0]],
            'RTL label ending in punctuation' => [[0x5D0, 0x2D]],
            'RTL label containing an LTR character' => [[0x5D0, 0x61, 0x5D0]],
            'RTL label starting with an Arabic digit' => [[0x660, 0x627]],
        ];
    }

    public static function providerProhibited(): array
    {
        return [
            [
                [0x1680]
            ],
            [
                [0xA0]
            ],
            [
                [0x20]
            ],
            [
                [0x2000]
            ],
            [
                [0x3000]
            ],
            [
                [0x10]
            ],
            [
                [0x7F]
            ],
            [
                [0x85]
            ],
            [
                [0x180E]
            ],
            [
                [0x1D175]
            ],
            [
                [0xF123]
            ],
            [
                [0xF1234]
            ],
            [
                [0x10F234]
            ],
            [
                [0x8FFFE]
            ],
            [
                [0x10FFFF]
            ],
            [
                [0xDF42]
            ],
            [
                [0xFFFD]
            ],
            [
                [0x2FF5]
            ],
            [
                [0x200E]
            ],
            [
                [0x202A]
            ],
            'mapping creates prohibited space' => [[0x037A]],
            'leading spacing mark' => [[0x0903, 0x0915]],
            'mixed left-to-right and right-to-left characters' => [[0x05D0, 0x61]],
            'right-to-left label ending in a digit' => [[0x0627, 0x31]],
        ];
    }

    public static function providerProhibitedRangeBoundaries(): array
    {
        return [
            'first range lower boundary' => [0x80],
            'first range upper boundary' => [0x9F],
            'private-use lower boundary' => [0xE000],
            'private-use upper boundary' => [0xF8FF],
        ];
    }

    public static function providerHangulComposition(): array
    {
        return [
            'first leading and vowel Jamo' => [[0x1100, 0x1161], [0xAC00]],
            'last leading and vowel Jamo' => [[0x1112, 0x1175], [0xD788]],
            'first valid trailing Jamo' => [[0xAC00, 0x11A8], [0xAC01]],
            'last valid trailing Jamo' => [[0xAC00, 0x11C2], [0xAC1B]],
            'trailing Jamo lower boundary is not composed' => [[0xAC00, 0x11A7], [0xAC00, 0x11A7]],
            'trailing Jamo upper boundary is not composed' => [[0xAC00, 0x11C3], [0xAC00, 0x11C3]],
            'syllable lower boundary is not extended' => [[0xABE4, 0x11A8], [0xABE4, 0x11A8]],
            'syllable upper boundary is not extended' => [[0xD7A4, 0x11A8], [0xD7A4, 0x11A8]],
            'leading Jamo lower boundary is not composed' => [[0x10FF, 0x1161], [0x10FF, 0x1161]],
            'leading Jamo upper boundary is not composed' => [[0x1113, 0x1161], [0x1113, 0x1161]],
            'vowel Jamo lower boundary is not composed' => [[0x1100, 0x1160], [0x1100, 0x1160]],
            'vowel Jamo upper boundary is not composed' => [[0x1100, 0x1176], [0x1100, 0x1176]],
            'last precomposed Hangul syllable' => [[0xD7A3], [0xD7A3]],
            'code point after Hangul syllables' => [[0xD7A4], [0xD7A4]],
        ];
    }

    private function utf8ToUcs(string $string): array
    {
        return $this->uctc->convert(
            $string,
            $this->uctc::FORMAT_UTF8,
            $this->uctc::FORMAT_UCS4_ARRAY
        );
    }

    private function ucsToUtf8(array $array): string
    {
        return $this->uctc->convert(
            $array,
            $this->uctc::FORMAT_UCS4_ARRAY,
            $this->uctc::FORMAT_UTF8
        );
    }
}
