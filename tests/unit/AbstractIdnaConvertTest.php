<?php

declare(strict_types=1);

namespace Algo26\IdnaConvert\Test\unit;

use Algo26\IdnaConvert\AbstractIdnaConvert;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Algo26\IdnaConvert\AbstractIdnaConvert
 */
final class AbstractIdnaConvertTest extends TestCase
{
    private AbstractIdnaConvert $converter;

    protected function setUp(): void
    {
        $this->converter = new class extends AbstractIdnaConvert {
            public function convert(string $host): string
            {
                return 'converted-' . $host;
            }
        };
    }

    /**
     * @dataProvider providerUrl
     */
    public function testConvertUrlChangesOnlyTheHost(string $url, string $expected): void
    {
        self::assertSame($expected, $this->converter->convertUrl($url));
    }

    /**
     * @dataProvider providerUrlWithoutHost
     */
    public function testConvertUrlLeavesUrlsWithoutAHostUnchanged(string $url): void
    {
        self::assertSame($url, $this->converter->convertUrl($url));
    }

    /**
     * @dataProvider providerInvalidUrl
     */
    public function testConvertUrlRejectsInvalidUrls(string $url): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionCode(206);
        self::expectExceptionMessage('The given string does not look like a URL');

        $this->converter->convertUrl($url);
    }

    public function testConvertEmailAddressUsesTheLastAtSignAsSeparator(): void
    {
        self::assertSame(
            'local@department@converted-example.com',
            $this->converter->convertEmailAddress('local@department@example.com')
        );
    }

    public function testConvertEmailAddressRejectsAnAddressWithoutAtSign(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionCode(206);
        self::expectExceptionMessage('The given string does not look like an email address');

        $this->converter->convertEmailAddress('local.example.com');
    }

    public static function providerUrl(): array
    {
        return [
            'standard URL' => [
                'https://example.com/path',
                'https://converted-example.com/path',
            ],
            'uppercase scheme' => [
                'HTTPS://example.com/path',
                'HTTPS://converted-example.com/path',
            ],
            'username without password' => [
                'https://user@example.com/path',
                'https://user@converted-example.com/path',
            ],
            'empty password' => [
                'https://user:@example.com/path',
                'https://user:@converted-example.com/path',
            ],
            'encoded user info' => [
                'https://user%40name:p%C3%A4ss@example.com/path',
                'https://user%40name:p%C3%A4ss@converted-example.com/path',
            ],
            'port zero' => [
                'https://example.com:0/path',
                'https://converted-example.com:0/path',
            ],
            'highest valid port' => [
                'https://example.com:65535/path',
                'https://converted-example.com:65535/path',
            ],
            'empty port' => [
                'https://example.com:/path',
                'https://converted-example.com:/path',
            ],
            'network path reference' => [
                '//example.com/path',
                '//converted-example.com/path',
            ],
            'host repeated in remaining components' => [
                'https://example.com/example.com?next=example.com#example.com',
                'https://converted-example.com/example.com?next=example.com#example.com',
            ],
            'IPv6 literal' => [
                'https://[2001:db8::1]:8443/path',
                'https://[2001:db8::1]:8443/path',
            ],
        ];
    }

    public static function providerUrlWithoutHost(): array
    {
        return [
            'empty string' => [''],
            'relative path' => ['/müller.example/path'],
            'opaque URI' => ['mailto:user@müller.example'],
            'file URI' => ['file:///some/path/müller.example'],
        ];
    }

    public static function providerInvalidUrl(): array
    {
        return [
            'port above maximum' => ['https://example.com:65536/path'],
            'non-numeric port' => ['https://example.com:not-a-port/path'],
            'missing host' => ['https:///path'],
        ];
    }
}
