<?php

declare(strict_types=1);

namespace Algo26\IdnaConvert\Test\unit;

use Algo26\IdnaConvert\Exception\InvalidCharacterException;
use Algo26\IdnaConvert\Validation\IdnaValidator;
use Algo26\IdnaConvert\Validation\InputValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Algo26\IdnaConvert\Validation\InputValidator
 * @covers \Algo26\IdnaConvert\Validation\IdnaValidator
 * @covers \Algo26\IdnaConvert\Validation\Ucs4Validator
 * @covers \Algo26\IdnaConvert\Validation\PunycodeValidator
 * @covers \Algo26\IdnaConvert\TranscodeUnicode\ByteLengthTrait
 * @covers \Algo26\IdnaConvert\TranscodeUnicode\TranscodeUnicode
 * @covers \Algo26\IdnaConvert\NamePrep\IdnaProcessor2008
 * @covers \Algo26\IdnaConvert\NamePrep\NamePrep
 * @covers \Algo26\IdnaConvert\NamePrep\UnicodeNormalizer
 * @covers \Algo26\IdnaConvert\NamePrep\UnicodeRange
 * @covers \Algo26\IdnaConvert\Punycode\AbstractPunycode
 * @covers \Algo26\IdnaConvert\Punycode\FromPunycode
 * @covers \Algo26\IdnaConvert\Punycode\ToPunycode
 * @covers \Algo26\IdnaConvert\ToIdn
 * @covers \Algo26\IdnaConvert\TranscodeUnicode\Ucs4Codec
 */
final class InputValidatorTest extends TestCase
{
    private InputValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new InputValidator();
    }

    /**
     * @throws InvalidCharacterException
     */
    public function testValidRepresentationsAreAccepted(): void
    {
        $this->validator->validateUtf8('müller');
        $this->validator->validateUcs4([0x6D, 0xFC, 0x6C, 0x6C, 0x65, 0x72]);
        $this->validator->validateUcs4String("\x00\x00\x00\x61");
        $this->validator->validatePunycode('xn--mller-kva');

        self::addToAssertionCount(4);
    }

    public function testMalformedUtf8IsRejected(): void
    {
        self::expectException(InvalidCharacterException::class);
        self::expectExceptionCode(303);

        $this->validator->validateUtf8("\xFF");
    }

    /** @dataProvider providerInvalidUcs4 */
    public function testInvalidUcs4IsRejected(array $input, string $exception, int $code): void
    {
        self::expectException($exception);
        self::expectExceptionCode($code);

        $this->validator->validateUcs4($input);
    }

    public function testMalformedUcs4StringIsRejected(): void
    {
        self::expectException(InvalidCharacterException::class);
        self::expectExceptionCode(306);

        $this->validator->validateUcs4String("\x00");
    }

    public function testInvalidPunycodeIsRejected(): void
    {
        self::expectException(InvalidCharacterException::class);
        self::expectExceptionCode(307);

        $this->validator->validatePunycode('xn--');
    }

    public function testPunycodeWithNonAsciiOrWhitespaceIsRejected(): void
    {
        self::expectException(InvalidCharacterException::class);
        self::expectExceptionCode(307);

        $this->validator->validatePunycode("xn--mller-kva ");
    }

    public function testIdnaValidatorUsesTheCoreRules(): void
    {
        $validator = new IdnaValidator();

        $validator->validateLabel('müller');
        $validator->validateDomain('müller.example');

        self::addToAssertionCount(2);
    }

    public function testIdnaValidatorRejectsInvalidLabels(): void
    {
        self::expectException(InvalidCharacterException::class);

        (new IdnaValidator())->validateLabel('-invalid');
    }

    public function testIdnaValidatorRejectsDotsInLabels(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionCode(105);

        (new IdnaValidator())->validateLabel('one.two');
    }

    public static function providerInvalidUcs4(): array
    {
        return [
            'surrogate' => [[0xD800], InvalidCharacterException::class, 305],
            'above Unicode range' => [[0x110000], InvalidCharacterException::class, 305],
            'non-list' => [['codePoint' => 0x61], InvalidArgumentException::class, 308],
            'non-integer' => [['0x61'], InvalidArgumentException::class, 308],
        ];
    }
}
