<?php

declare(strict_types=1);

namespace Algo26\IdnaConvert\Test\unit;

use Algo26\IdnaConvert\Exception\InvalidCharacterException;
use Algo26\IdnaConvert\TranscodeUnicode\TranscodeUnicode;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Algo26\IdnaConvert\TranscodeUnicode\ByteLengthTrait
 * @covers \Algo26\IdnaConvert\TranscodeUnicode\TranscodeUnicode
 */
final class TranscodeUnicodeTest extends TestCase
{
    private TranscodeUnicode $transcoder;

    protected function setUp(): void
    {
        $this->transcoder = new TranscodeUnicode();
    }

    public function testEncodingNamesAreCaseInsensitive(): void
    {
        self::assertSame([0xE4], $this->transcoder->convert('ä', 'UTF8', 'UCS4ARRAY'));
    }

    public function testIdenticalEncodingReturnsInputUnchanged(): void
    {
        self::assertSame('unchanged', $this->transcoder->convert('unchanged', 'UTF8', 'utf8'));
    }

    public function testInvalidInputEncodingIsRejected(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionCode(300);
        self::expectExceptionMessage('Invalid input format invalid');

        $this->transcoder->convert('value', 'invalid', TranscodeUnicode::FORMAT_UTF8);
    }

    public function testInvalidOutputEncodingIsRejected(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionCode(301);
        self::expectExceptionMessage('Invalid output format invalid');

        $this->transcoder->convert('value', TranscodeUnicode::FORMAT_UTF8, 'invalid');
    }

    /**
     * @dataProvider providerUtf8Boundaries
     */
    public function testUtf8RoundTripAtByteBoundaries(int $codePoint, string $expectedUtf8): void
    {
        $encoded = $this->transcoder->convert(
            [$codePoint],
            TranscodeUnicode::FORMAT_UCS4_ARRAY,
            TranscodeUnicode::FORMAT_UTF8,
        );

        self::assertSame($expectedUtf8, $encoded);
        self::assertSame(
            [$codePoint],
            $this->transcoder->convert(
                $encoded,
                TranscodeUnicode::FORMAT_UTF8,
                TranscodeUnicode::FORMAT_UCS4_ARRAY,
            ),
        );
    }

    /**
     * @dataProvider providerMalformedUtf8
     */
    public function testMalformedUtf8IsRejected(string $input, int $expectedCode): void
    {
        self::expectException(InvalidCharacterException::class);
        self::expectExceptionCode($expectedCode);

        $this->transcoder->convert(
            $input,
            TranscodeUnicode::FORMAT_UTF8,
            TranscodeUnicode::FORMAT_UCS4_ARRAY,
        );
    }

    public function testOutOfRangeUcs4IsRejected(): void
    {
        self::expectException(InvalidCharacterException::class);
        self::expectExceptionCode(305);

        $this->transcoder->convert(
            [1 << 21],
            TranscodeUnicode::FORMAT_UCS4_ARRAY,
            TranscodeUnicode::FORMAT_UTF8,
        );
    }

    public function testSafeModeReplacesMalformedUtf8WithTheConfiguredCodePoint(): void
    {
        self::assertSame(
            [0xFFFD, 0x41],
            $this->transcoder->convert(
                "\xE2A",
                TranscodeUnicode::FORMAT_UTF8,
                TranscodeUnicode::FORMAT_UCS4_ARRAY,
                true,
                0xFFFD,
            ),
        );
    }

    public function testSafeModeEncodesItsReplacementCodePointAsUtf8(): void
    {
        self::assertSame(
            "\xEF\xBF\xBD",
            $this->transcoder->convert(
                [1 << 21],
                TranscodeUnicode::FORMAT_UCS4_ARRAY,
                TranscodeUnicode::FORMAT_UTF8,
                true,
                0xFFFD,
            ),
        );
    }

    public function testDefaultSafeCodePointDoesNotLeakFromAnEarlierCall(): void
    {
        $this->transcoder->convert(
            "\x80",
            TranscodeUnicode::FORMAT_UTF8,
            TranscodeUnicode::FORMAT_UCS4_ARRAY,
            true,
            0xFFFD,
        );

        self::assertSame(
            [0xFFFC],
            $this->transcoder->convert(
                "\x80",
                TranscodeUnicode::FORMAT_UTF8,
                TranscodeUnicode::FORMAT_UCS4_ARRAY,
                true,
            ),
        );
    }

    public static function providerUtf8Boundaries(): array
    {
        return [
            'last ASCII code point' => [0x7F, "\x7F"],
            'first two-byte code point' => [0x80, "\xC2\x80"],
            'last two-byte code point' => [0x7FF, "\xDF\xBF"],
            'first three-byte code point' => [0x800, "\xE0\xA0\x80"],
            'last code point before surrogates' => [0xD7FF, "\xED\x9F\xBF"],
            'first code point after surrogates' => [0xE000, "\xEE\x80\x80"],
            'last three-byte code point' => [0xFFFF, "\xEF\xBF\xBF"],
            'first four-byte code point' => [0x10000, "\xF0\x90\x80\x80"],
            'largest Unicode code point' => [0x10FFFF, "\xF4\x8F\xBF\xBF"],
        ];
    }

    public static function providerMalformedUtf8(): array
    {
        return [
            'continuation byte without start byte' => ["\x80", 303],
            'overlong two-byte sequence' => ["\xC0\x80", 303],
            'UTF-16 surrogate' => ["\xED\xA0\x80", 304],
            'above Unicode maximum' => ["\xF4\x90\x80\x80", 304],
            'truncated sequence' => ["\xE2\x82", 302],
            'ASCII in continuation position' => ["\xE2A", 302],
        ];
    }
}
