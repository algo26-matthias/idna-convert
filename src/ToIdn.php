<?php

declare(strict_types=1);

namespace Algo26\IdnaConvert;

use Algo26\IdnaConvert\Exception\InvalidCharacterException;
use Algo26\IdnaConvert\Exception\InvalidIdnVersionException;
use Algo26\IdnaConvert\NamePrep\IdnaData2008;
use Algo26\IdnaConvert\NamePrep\NamePrep;
use Algo26\IdnaConvert\NamePrep\UnicodeRange;
use Algo26\IdnaConvert\Punycode\FromPunycode;
use Algo26\IdnaConvert\Punycode\ToPunycode;
use Algo26\IdnaConvert\TranscodeUnicode\Ucs4Codec;

class ToIdn extends AbstractIdnaConvert implements IdnaConvertInterface
{
    private Ucs4Codec $ucs4Codec;

    private ToPunycode $punycodeEncoder;
    private FromPunycode $punycodeDecoder;
    private ?NamePrep $idna2008Mapper = null;
    private int $idnVersion;

    /**
     * @throws InvalidIdnVersionException
     */
    public function __construct(
        ?int $idnVersion = null,
        private readonly bool $useStd3AsciiRules = false,
        private readonly bool $checkHyphens = true,
        private readonly bool $verifyDnsLength = true,
    ) {
        $this->idnVersion = $idnVersion ?? 2008;
        $this->ucs4Codec = new Ucs4Codec();
        $this->punycodeEncoder = new ToPunycode($idnVersion, $useStd3AsciiRules, $checkHyphens);
        $this->punycodeDecoder = new FromPunycode();
        if ($this->idnVersion === 2008) {
            $this->idna2008Mapper = new NamePrep(2008, false, false);
        }
    }

    /**
     * @throws InvalidCharacterException
     * @throws Exception\AlreadyPunycodeException
     */
    public function convert(string $host): string
    {
        if (strlen($host) === 0) {
            if ($this->verifyDnsLength) {
                throw new InvalidCharacterException('A domain name must not be empty', 105);
            }

            return $host;
        }

        if (
            str_contains($host, '/')
            || str_contains($host, ':')
            || str_contains($host, '?')
            || str_contains($host, '@')
        ) {
            throw new InvalidCharacterException('Neither email addresses nor URLs are allowed', 205);
        }

        // These three punctuation characters are treated like the dot
        $host = str_replace(['。', '．', '｡'], '.', $host);

        // Operate per label
        $hostLabels = explode('.', $host);
        foreach ($hostLabels as $index => $label) {
            $isRootLabel = $label === '' && $index === array_key_last($hostLabels) && str_ends_with($host, '.');
            if ($label === '' && (!$isRootLabel || $this->verifyDnsLength)) {
                throw new InvalidCharacterException('A domain name must not contain empty labels', 105);
            }
            if ($isRootLabel) {
                continue;
            }
        }

        $preparedLabels = $this->configureDomainBidiValidation($hostLabels);
        foreach ($hostLabels as $index => $label) {
            if ($label === '') {
                continue;
            }

            $encoded = $this->encodeLabel($label, $preparedLabels[$index] ?? null);
            if ($encoded === null || $encoded === '') {
                throw new InvalidCharacterException('A domain name must not contain labels that map to empty', 105);
            }
            $hostLabels[$index] = $encoded;
            if ($this->verifyDnsLength && strlen($hostLabels[$index]) > 63) {
                throw new InvalidCharacterException('An encoded domain label must not exceed 63 bytes', 106);
            }
        }

        $encodedHost = implode('.', $hostLabels);
        $dnsName = str_ends_with($encodedHost, '.') ? substr($encodedHost, 0, -1) : $encodedHost;
        if ($this->verifyDnsLength && (strlen($dnsName) < 1 || strlen($dnsName) > 253)) {
            throw new InvalidCharacterException('An encoded domain name must contain between 1 and 253 bytes', 106);
        }

        return $encodedHost;
    }

    /**
     * @param array{string, string|null}|null $preparedLabel
     *
     * @throws InvalidCharacterException
     */
    private function encodeLabel(string $label, ?array $preparedLabel = null): ?string
    {
        if ($this->idnVersion === 2008) {
            if ($preparedLabel === null) {
                throw new InvalidCharacterException('IDNA2008 label preparation failed', 107);
            }
            [$unicodeLabel, $canonicalALabel] = $preparedLabel;
            $encoded = $this->punycodeEncoder->convert($this->ucs4Codec->decode($unicodeLabel));
            if ($canonicalALabel !== null && $encoded !== $canonicalALabel) {
                throw new InvalidCharacterException('The input contains a non-canonical A-label', 107);
            }

            return $encoded;
        }

        if (!str_starts_with(strtolower($label), ToPunycode::PUNYCODE_PREFIX)) {
            return $this->punycodeEncoder->convert($this->ucs4Codec->decode($label));
        }

        $lowercaseLabel = strtolower($label);
        $decoded = $this->decodeALabel($lowercaseLabel);
        $encoded = $this->punycodeEncoder->convert($this->ucs4Codec->decode($decoded));
        if ($encoded === null || $encoded !== $lowercaseLabel) {
            throw new InvalidCharacterException('The input contains a non-canonical A-label', 107);
        }

        return $encoded;
    }

    /**
     * @param list<string> $labels
     *
     * @return array<int, array{string, string|null}>
     */
    private function configureDomainBidiValidation(array $labels): array
    {
        if ($this->idnVersion !== 2008) {
            return [];
        }

        $isBidiDomain = false;
        $preparedLabels = [];
        foreach ($labels as $index => $label) {
            if ($label === '') {
                continue;
            }
            $preparedLabels[$index] = $this->prepareIdna2008Label($label);
            [$unicodeLabel] = $preparedLabels[$index];
            $codePoints = $this->ucs4Codec->decode($unicodeLabel);
            foreach ($codePoints as $codePoint) {
                if (
                    UnicodeRange::contains($codePoint, IdnaData2008::BIDI_CLASSES['R'])
                    || UnicodeRange::contains($codePoint, IdnaData2008::BIDI_CLASSES['AL'])
                    || UnicodeRange::contains($codePoint, IdnaData2008::BIDI_CLASSES['AN'])
                ) {
                    $isBidiDomain = true;
                    break;
                }
            }
        }

        $this->punycodeEncoder = new ToPunycode(
            2008,
            $this->useStd3AsciiRules,
            $this->checkHyphens,
            $isBidiDomain,
        );

        return $preparedLabels;
    }

    /**
     * @return array{string, string|null}
     *
     * @throws InvalidCharacterException
     */
    private function prepareIdna2008Label(string $label): array
    {
        // UTS #46 recognizes A-labels only after mapping and normalization.
        if ($this->idna2008Mapper === null) {
            throw new InvalidCharacterException('IDNA2008 mapping is unavailable', 107);
        }
        $mapped = $this->ucs4Codec->encode($this->idna2008Mapper->do($this->ucs4Codec->decode($label)));
        if (!str_starts_with($mapped, ToPunycode::PUNYCODE_PREFIX)) {
            return [$mapped, null];
        }

        return [$this->decodeALabel($mapped), $mapped];
    }

    /** @throws InvalidCharacterException */
    private function decodeALabel(string $label): string
    {
        $decoded = $this->punycodeDecoder->convert($label);
        if ($decoded === false || preg_match('/[^\x00-\x7F]/', $decoded) !== 1) {
            throw new InvalidCharacterException('The input contains an invalid A-label', 107);
        }

        return $decoded;
    }
}
