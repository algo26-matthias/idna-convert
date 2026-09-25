<?php

declare(strict_types=1);

namespace Algo26\IdnaConvert\Test\unit;

use Algo26\IdnaConvert\Exception\AlreadyPunycodeException;
use Algo26\IdnaConvert\Exception\InvalidCharacterException;
use Algo26\IdnaConvert\Exception\Std3AsciiRulesViolationException;
use Algo26\IdnaConvert\Punycode\AbstractPunycode;
use Algo26\IdnaConvert\Punycode\FromPunycode;
use Algo26\IdnaConvert\Punycode\ToPunycode;
use Algo26\IdnaConvert\TranscodeUnicode\TranscodeUnicode;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Algo26\IdnaConvert\NamePrep\CaseFolding
 * @covers \Algo26\IdnaConvert\NamePrep\IdnaProcessor2008
 * @covers \Algo26\IdnaConvert\NamePrep\NamePrep
 * @covers \Algo26\IdnaConvert\NamePrep\NamePrepProcessor2003
 * @covers \Algo26\IdnaConvert\NamePrep\UnicodeNormalizer
 * @covers \Algo26\IdnaConvert\NamePrep\UnicodeRange
 * @covers \Algo26\IdnaConvert\Punycode\AbstractPunycode
 * @covers \Algo26\IdnaConvert\Punycode\FromPunycode
 * @covers \Algo26\IdnaConvert\Punycode\ToPunycode
 * @covers \Algo26\IdnaConvert\TranscodeUnicode\ByteLengthTrait
 * @covers \Algo26\IdnaConvert\TranscodeUnicode\TranscodeUnicode
 * @covers \Algo26\IdnaConvert\TranscodeUnicode\Ucs4Codec
 */
final class PunycodeTest extends TestCase
{
    private TranscodeUnicode $transcoder;

    protected function setUp(): void
    {
        $this->transcoder = new TranscodeUnicode();
    }

    public function testPrefixIsExposed(): void
    {
        self::assertSame(AbstractPunycode::PUNYCODE_PREFIX, (new FromPunycode())->getPunycodePrefix());
    }

    /**
     * @throws InvalidCharacterException
     */
    public function testDecoderRejectsStringsWithoutPayload(): void
    {
        $decoder = new FromPunycode();

        self::assertFalse($decoder->convert('example'));
        self::assertFalse($decoder->convert('xn--'));
        self::assertFalse($decoder->convert("xn-- \t\n"));
    }

    /**
     * @dataProvider providerInvalidPunycodeDigits
     */
    public function testDecoderRejectsCharactersOutsidePunycodeDigitRanges(string $character): void
    {
        self::expectException(InvalidCharacterException::class);
        self::expectExceptionMessage('encountered invalid digit at #4');

        (new FromPunycode())->convert('xn--' . $character);
    }

    public function testPunycodeDigitsAreCaseInsensitiveAtBothLetterBoundaries(): void
    {
        $decoder = new FromPunycode();

        self::assertSame($decoder->convert('xn--a'), $decoder->convert('xn--A'));
        self::assertSame($decoder->convert('xn--za'), $decoder->convert('xn--ZA'));
    }

    public function testEncoderHandlesAnEmptySequence(): void
    {
        self::assertNull((new ToPunycode())->convert([]));
    }

    public function testEncoderDoesNotEnableStd3RulesByDefault(): void
    {
        self::assertSame('xn--4ca', (new ToPunycode())->convert([0xE4]));
    }

    /**
     * @dataProvider providerRoundTripLabels
     */
    public function testPunycodeReferenceEncodingAndRoundTrip(string $label, string $expected): void
    {
        $encoded = $this->encode($label);

        self::assertSame($expected, $encoded);
        self::assertSame($label, (new FromPunycode())->convert($encoded));
    }

    /**
     * @dataProvider providerValidStd3Labels
     */
    public function testStd3AcceptsValidAsciiLabels(string $label): void
    {
        self::assertSame($label, $this->encode($label, true));
    }

    /**
     * @dataProvider providerLabelsWithEdgeHyphens
     */
    public function testStd3RejectsLeadingAndTrailingHyphens(string $label): void
    {
        self::expectException(Std3AsciiRulesViolationException::class);
        self::expectExceptionCode(103);

        $this->encode($label, true);
    }

    public function testStd3ReportsTheInvalidCharacterOffset(): void
    {
        self::expectException(Std3AsciiRulesViolationException::class);
        self::expectExceptionCode(104);
        self::expectExceptionMessage('Character at offset 3 is outside the legal range');

        $this->encode('abc:', true, 2003);
    }

    public function testEncoderRejectsAnExistingPunycodePrefix(): void
    {
        self::expectException(AlreadyPunycodeException::class);
        self::expectExceptionCode(100);

        $this->encode('xn--example');
    }

    public static function providerValidStd3Labels(): array
    {
        return [
            'lowercase' => ['example'],
            'lowercase boundaries' => ['az'],
            'digit boundaries' => ['09'],
            'internal hyphen' => ['valid-label'],
        ];
    }

    public static function providerLabelsWithEdgeHyphens(): array
    {
        return [
            'leading' => ['-example'],
            'trailing' => ['example-'],
            'both' => ['-example-'],
        ];
    }

    public static function providerInvalidPunycodeDigits(): array
    {
        return [
            'before digits' => ['/'],
            'after digits' => [':'],
            'before uppercase letters' => ['@'],
            'after uppercase letters' => ['['],
            'before lowercase letters' => ['`'],
            'after lowercase letters' => ['{'],
        ];
    }

    public static function providerRoundTripLabels(): array
    {
        return [
            'Latin with non-ASCII character at the start' => ['äaaa', 'xn--aaa-pla'],
            'Latin with non-ASCII character in the middle' => ['mañana', 'xn--maana-pta'],
            'Latin with non-ASCII character at the end' => ['bücher', 'xn--bcher-kva'],
            'Greek' => ['παράδειγμα', 'xn--hxajbheg2az3al'],
            'Cyrillic' => ['россия', 'xn--h1alffa9f'],
            'CJK' => ['例え', 'xn--r8jz45g'],
            'Hangul' => ['한국', 'xn--3e0b707e'],
        ];
    }

    private function encode(string $label, bool $useStd3AsciiRules = false, int $idnVersion = 2008): ?string
    {
        $codePoints = $this->transcoder->convert(
            $label,
            TranscodeUnicode::FORMAT_UTF8,
            TranscodeUnicode::FORMAT_UCS4_ARRAY,
        );

        return (new ToPunycode($idnVersion, $useStd3AsciiRules))->convert($codePoints);
    }
}
