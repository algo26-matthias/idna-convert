<?php

declare(strict_types=1);

namespace Algo26\IdnaConvert\Test\unit;

use Algo26\IdnaConvert\Exception\AlreadyPunycodeException;
use Algo26\IdnaConvert\Exception\Std3AsciiRulesViolationException;
use Algo26\IdnaConvert\Punycode\AbstractPunycode;
use Algo26\IdnaConvert\Punycode\FromPunycode;
use Algo26\IdnaConvert\Punycode\ToPunycode;
use Algo26\IdnaConvert\TranscodeUnicode\TranscodeUnicode;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Algo26\IdnaConvert\NamePrep\CaseFolding
 * @covers \Algo26\IdnaConvert\NamePrep\NamePrep
 * @covers \Algo26\IdnaConvert\Punycode\AbstractPunycode
 * @covers \Algo26\IdnaConvert\Punycode\FromPunycode
 * @covers \Algo26\IdnaConvert\Punycode\ToPunycode
 * @covers \Algo26\IdnaConvert\TranscodeUnicode\ByteLengthTrait
 * @covers \Algo26\IdnaConvert\TranscodeUnicode\TranscodeUnicode
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

    public function testDecoderRejectsStringsWithoutPayload(): void
    {
        $decoder = new FromPunycode();

        self::assertFalse($decoder->convert('example'));
        self::assertFalse($decoder->convert('xn--'));
        self::assertFalse($decoder->convert("xn-- \t\n"));
    }

    public function testEncoderHandlesAnEmptySequence(): void
    {
        self::assertNull((new ToPunycode())->convert([]));
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

        $this->encode('abcä', true);
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
            'digits' => ['123'],
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

    private function encode(string $label, bool $useStd3AsciiRules = false): ?string
    {
        $codePoints = $this->transcoder->convert(
            $label,
            TranscodeUnicode::FORMAT_UTF8,
            TranscodeUnicode::FORMAT_UCS4_ARRAY,
        );

        return (new ToPunycode(2008, $useStd3AsciiRules))->convert($codePoints);
    }
}
