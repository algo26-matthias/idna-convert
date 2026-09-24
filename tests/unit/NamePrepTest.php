<?php

namespace Algo26\IdnaConvert\Test\unit;

use Algo26\IdnaConvert\Exception\InvalidCharacterException;
use Algo26\IdnaConvert\Exception\InvalidIdnVersionException;
use Algo26\IdnaConvert\NamePrep\NamePrep;
use Algo26\IdnaConvert\TranscodeUnicode\TranscodeUnicode;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Algo26\IdnaConvert\NamePrep\CaseFolding
 * @covers \Algo26\IdnaConvert\NamePrep\NamePrep
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

    public function testDirectlyProhibitedCharacterReportsItsCategory(): void
    {
        self::expectException(InvalidCharacterException::class);
        self::expectExceptionCode(101);
        self::expectExceptionMessage('Prohibited input U+000000A0');

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
                [0x130], [73, 775]
            ],
            [
                [0x0143, 0x037A], [324, 0x20, 953]
            ],
            [
                [0x2121, 0x33C6, 0x1D7BB], [116, 101, 108, 99, 8725, 107, 103, 963]
            ],
            [
                [0x1fb7], [913, 834, 953]
            ],
            [
                [0x6a, 0x30c, 0xAA], 'J̌ª'
            ],
            [
                "J̌", "J̌"
            ],
            [
                '̈́ͅ', '̈́ͅ'
            ],
            [
                [0xFEFF], ''
            ],
            [
                [0x221], 'ȡ'
            ],
            [
                [0x627, 0x31], 'ا1'
            ],
            [
                [0x627, 0x31, 0x628], [0x627, 0x31, 0x628]
            ]
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
