<?php

declare(strict_types=1);

namespace Algo26\IdnaConvert\Test\unit;

use Algo26\IdnaConvert\NamePrep\NormalizationData2003;
use Algo26\IdnaConvert\NamePrep\NormalizationData2008;
use Algo26\IdnaConvert\NamePrep\UnicodeNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** @covers \Algo26\IdnaConvert\NamePrep\UnicodeNormalizer */
final class UnicodeNormalizerTest extends TestCase
{
    private UnicodeNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new UnicodeNormalizer();
    }

    public function testCanonicalCompositionUsesTheDedicatedUnicodeTable(): void
    {
        self::assertSame([0x1F0], $this->normalizer->normalize([0x6A, 0x30C]));
    }

    public function testEveryGeneratedCompositionPairIsApplied(): void
    {
        $versions = [
            2003 => NormalizationData2003::COMPOSITIONS,
            2008 => NormalizationData2008::COMPOSITIONS,
        ];
        foreach ($versions as $idnVersion => $compositionTable) {
            $normalizer = new UnicodeNormalizer($idnVersion);
            foreach ($compositionTable as $starter => $compositions) {
                foreach ($compositions as $combiningCodePoint => $composite) {
                    self::assertSame(
                        $normalizer->normalize([$composite]),
                        $normalizer->normalize([$starter, $combiningCodePoint]),
                        sprintf(
                            'IDNA%d failed to compose U+%04X and U+%04X',
                            $idnVersion,
                            $starter,
                            $combiningCodePoint,
                        ),
                    );
                }
            }
        }
    }

    public function testCanonicalOrderingHappensBeforeComposition(): void
    {
        self::assertSame(
            [0xC0, 0x315],
            $this->normalizer->normalize([0x41, 0x315, 0x300]),
        );
    }

    public function testCanonicalOrderingSupportsLeadingNonStarters(): void
    {
        self::assertSame([0x300], $this->normalizer->normalize([0x300]));
    }

    public function testIdna2008UsesCurrentUnicodeNormalizationData(): void
    {
        self::assertSame([0x1B06], $this->normalizer->normalize([0x1B05, 0x1B35]));
        self::assertSame(
            [0x1B05, 0x1B35],
            (new UnicodeNormalizer(2003))->normalize([0x1B05, 0x1B35]),
        );
    }

    public function testEqualCombiningClassBlocksComposition(): void
    {
        self::assertSame(
            [0x41, 0x30B, 0x30A],
            $this->normalizer->normalize([0x41, 0x30B, 0x30A]),
        );
    }

    public function testCompositionExclusionsRemainDecomposed(): void
    {
        self::assertSame([0x915, 0x93C], $this->normalizer->normalize([0x958]));
    }

    public function testCompatibilityNormalizationIsExplicit(): void
    {
        self::assertSame([0xAA], $this->normalizer->normalize([0xAA]));
        self::assertSame([0x61], (new UnicodeNormalizer(2003))->normalize([0xAA]));
    }

    public function testUnsupportedIdnaVersionIsRejected(): void
    {
        self::expectException(InvalidArgumentException::class);

        new UnicodeNormalizer(1999);
    }

    public function testHangulIsComposedAlgorithmically(): void
    {
        self::assertSame([0xAC01], $this->normalizer->normalize([0x1100, 0x1161, 0x11A8]));
        self::assertSame([0xAC1D], $this->normalizer->normalize([0xAC1C, 0x11A8]));
        self::assertSame([0xAC01, 0x11A8], $this->normalizer->normalize([0xAC01, 0x11A8]));
        self::assertSame([0xAC01], $this->normalizer->normalize([0xAC01]));
    }
}
