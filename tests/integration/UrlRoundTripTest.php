<?php

declare(strict_types=1);

namespace Algo26\IdnaConvert\Test\integration;

use Algo26\IdnaConvert\AbstractIdnaConvert;
use Algo26\IdnaConvert\ToIdn;
use Algo26\IdnaConvert\ToUnicode;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Algo26\IdnaConvert\AbstractIdnaConvert
 * @covers \Algo26\IdnaConvert\NamePrep\CaseFolding
 * @covers \Algo26\IdnaConvert\NamePrep\NamePrep
 * @covers \Algo26\IdnaConvert\NamePrep\NamePrepProcessor2003
 * @covers \Algo26\IdnaConvert\NamePrep\IdnaProcessor2008
 * @covers \Algo26\IdnaConvert\NamePrep\UnicodeNormalizer
 * @covers \Algo26\IdnaConvert\Punycode\AbstractPunycode
 * @covers \Algo26\IdnaConvert\Punycode\FromPunycode
 * @covers \Algo26\IdnaConvert\Punycode\ToPunycode
 * @covers \Algo26\IdnaConvert\ToIdn
 * @covers \Algo26\IdnaConvert\ToUnicode
 * @covers \Algo26\IdnaConvert\TranscodeUnicode\ByteLengthTrait
 * @covers \Algo26\IdnaConvert\TranscodeUnicode\TranscodeUnicode
 * @covers \Algo26\IdnaConvert\TranscodeUnicode\Ucs4Codec
 */
final class UrlRoundTripTest extends TestCase
{
    /**
     * @dataProvider providerUrl
     */
    public function testUrlSurvivesIdnRoundTrip(string $url): void
    {
        $encoded = (new ToIdn(2008))->convertUrl($url);
        $decoded = (new ToUnicode())->convertUrl($encoded);

        self::assertSame($url, $decoded);
    }

    public static function providerUrl(): array
    {
        return [
            'all URL components' => [
                'https://üser:päßword@müller.example:8443/'
                . 'müller.example?next=müller.example#müller.example',
            ],
            'network path reference' => [
                '//πι.example/gnörz/lörz/?next=πι.example#fragment',
            ],
            'empty query and fragment' => [
                'https://ñandú.example/path?#',
            ],
        ];
    }
}
