<?php

declare(strict_types=1);

namespace Algo26\IdnaConvert\NamePrep;

use Algo26\IdnaConvert\Exception\InvalidCharacterException;

/** @internal */
final class IdnaProcessor2008
{
    public function __construct(
        private readonly bool $checkHyphens = true,
        private readonly ?bool $checkBidi = null,
        private readonly UnicodeNormalizer $normalizer = new UnicodeNormalizer(2008),
    ) {
    }

    /**
     * Apply non-transitional UTS #46 mapping and validate the resulting label according to IDNA2008.
     *
     * @param list<int> $input
     *
     * @return list<int>
     *
     * @throws InvalidCharacterException
     */
    public function process(array $input): array
    {
        $mapped = [];
        foreach ($input as $codePoint) {
            if (isset(IdnaData2008::MAPPINGS[$codePoint])) {
                $mapping = IdnaData2008::MAPPINGS[$codePoint];
                if (is_int($mapping)) {
                    $mapped[] = $mapping;
                } else {
                    foreach ($mapping as $replacement) {
                        $mapped[] = $replacement;
                    }
                }

                continue;
            }
            if (UnicodeRange::contains($codePoint, IdnaData2008::IGNORED)) {
                continue;
            }
            $mapped[] = $codePoint;
        }

        $label = $this->normalizer->normalize($mapped);
        $this->validateHyphens($label);
        $this->validateCodePoints($label);
        $this->validateContexts($label);
        if ($this->checkBidi !== false) {
            $this->validateBidi($label, $this->checkBidi === true);
        }

        return $label;
    }

    /** @param list<int> $label */
    private function validateCodePoints(array $label): void
    {
        if ($label !== [] && UnicodeRange::contains($label[0], NormalizationData2008::MARK_RANGES)) {
            throw new InvalidCharacterException('An IDNA label must not start with a combining mark', 101);
        }

        foreach ($label as $codePoint) {
            if (UnicodeRange::contains($codePoint, IdnaData2008::IDNA_DISALLOWED)) {
                $this->throwProhibited($codePoint);
            }
        }
    }

    /** @param list<int> $label */
    private function validateContexts(array $label): void
    {
        $containsJapaneseScript = !in_array(0x30FB, $label, true) || $this->containsJapaneseScript($label);
        $hasArabicIndic = false;
        $hasExtendedArabicIndic = false;

        foreach ($label as $position => $codePoint) {
            $hasArabicIndic = $hasArabicIndic || (0x0660 <= $codePoint && $codePoint <= 0x0669);
            $hasExtendedArabicIndic = $hasExtendedArabicIndic || (0x06F0 <= $codePoint && $codePoint <= 0x06F9);
            $valid = match ($codePoint) {
                0x200C => $this->isValidZeroWidthNonJoiner($label, $position),
                0x200D => $this->isPrecededByVirama($label, $position),
                0x00B7 => ($label[$position - 1] ?? null) === 0x006C
                    && ($label[$position + 1] ?? null) === 0x006C,
                0x0375 => isset($label[$position + 1]) && $this->hasScript($label[$position + 1], 'Greek'),
                0x05F3, 0x05F4 => isset($label[$position - 1]) && $this->hasScript($label[$position - 1], 'Hebrew'),
                0x30FB => $containsJapaneseScript,
                default => true,
            };
            if (!$valid) {
                throw new InvalidCharacterException(
                    sprintf('Context rule failed for U+%08X', $codePoint),
                    101,
                );
            }
        }

        if ($hasArabicIndic && $hasExtendedArabicIndic) {
            throw new InvalidCharacterException('Arabic-Indic digit sets must not be mixed in an IDNA label', 101);
        }
    }

    /** @param list<int> $label */
    private function isValidZeroWidthNonJoiner(array $label, int $position): bool
    {
        if ($this->isPrecededByVirama($label, $position)) {
            return true;
        }

        $before = $position - 1;
        while ($before >= 0 && $this->joiningType($label[$before]) === 'T') {
            --$before;
        }
        $after = $position + 1;
        $length = count($label);
        while ($after < $length && $this->joiningType($label[$after]) === 'T') {
            ++$after;
        }

        return $before >= 0
            && $after < $length
            && in_array($this->joiningType($label[$before]), ['L', 'D'], true)
            && in_array($this->joiningType($label[$after]), ['R', 'D'], true);
    }

    /** @param list<int> $label */
    private function isPrecededByVirama(array $label, int $position): bool
    {
        return $position > 0
            && isset(NormalizationData2008::COMBINING_CLASSES[$label[$position - 1]])
            && NormalizationData2008::COMBINING_CLASSES[$label[$position - 1]] === 9;
    }

    /** @param list<int> $label */
    private function containsJapaneseScript(array $label): bool
    {
        foreach ($label as $codePoint) {
            if (
                $this->hasScript($codePoint, 'Hiragana')
                || $this->hasScript($codePoint, 'Katakana')
                || $this->hasScript($codePoint, 'Han')
            ) {
                return true;
            }
        }

        return false;
    }

    /** @param list<int> $label */
    private function validateBidi(array $label, bool $force): void
    {
        if ($label === []) {
            return;
        }

        $isBidiLabel = false;
        foreach ($label as $codePoint) {
            if (
                UnicodeRange::contains($codePoint, IdnaData2008::BIDI_CLASSES['R'])
                || UnicodeRange::contains($codePoint, IdnaData2008::BIDI_CLASSES['AL'])
                || UnicodeRange::contains($codePoint, IdnaData2008::BIDI_CLASSES['AN'])
            ) {
                $isBidiLabel = true;
                break;
            }
        }
        if (!$force && !$isBidiLabel) {
            return;
        }

        $classes = array_map(fn (int $codePoint): ?string => $this->bidiClass($codePoint), $label);
        $first = $classes[0];
        $isRightToLeft = in_array($first, ['R', 'AL'], true);
        $allowed = $isRightToLeft
            ? ['R', 'AL', 'AN', 'EN', 'ES', 'CS', 'ET', 'ON', 'BN', 'NSM']
            : ['L', 'EN', 'ES', 'CS', 'ET', 'ON', 'BN', 'NSM'];
        if ((!$isRightToLeft && $first !== 'L') || array_diff($classes, $allowed) !== []) {
            throw new InvalidCharacterException('The label violates the IDNA2008 bidirectional text rule', 101);
        }

        $last = count($classes) - 1;
        while ($last >= 0 && $classes[$last] === 'NSM') {
            --$last;
        }
        $validEndClasses = $isRightToLeft ? ['R', 'AL', 'EN', 'AN'] : ['L', 'EN'];
        if ($last < 0 || !in_array($classes[$last], $validEndClasses, true)) {
            throw new InvalidCharacterException('The label violates the IDNA2008 bidirectional text rule', 101);
        }
        if ($isRightToLeft && in_array('AN', $classes, true) && in_array('EN', $classes, true)) {
            throw new InvalidCharacterException('Arabic and European digits must not be mixed in an RTL label', 101);
        }
    }

    private function joiningType(int $codePoint): ?string
    {
        foreach (IdnaData2008::JOINING_TYPES as $type => $ranges) {
            if ($this->isInRanges($codePoint, $ranges)) {
                return $type;
            }
        }

        return null;
    }

    private function bidiClass(int $codePoint): ?string
    {
        foreach (IdnaData2008::BIDI_CLASSES as $class => $ranges) {
            if ($this->isInRanges($codePoint, $ranges)) {
                return $class;
            }
        }

        return null;
    }

    private function hasScript(int $codePoint, string $script): bool
    {
        return UnicodeRange::contains($codePoint, IdnaData2008::SCRIPTS[$script]);
    }

    /** @param list<array{int, int}> $ranges */
    private function isInRanges(int $codePoint, array $ranges): bool
    {
        return UnicodeRange::contains($codePoint, $ranges);
    }

    /** @param list<int> $label */
    private function validateHyphens(array $label): void
    {
        if (!$this->checkHyphens || $label === []) {
            return;
        }
        if ($label[0] === 0x2D || $label[array_key_last($label)] === 0x2D) {
            throw new InvalidCharacterException('An IDNA label must not start or end with a hyphen', 101);
        }
        if (($label[2] ?? null) === 0x2D && ($label[3] ?? null) === 0x2D) {
            throw new InvalidCharacterException('An IDNA label must not contain hyphens in positions 3 and 4', 101);
        }
    }

    /** @throws InvalidCharacterException */
    private function throwProhibited(int $codePoint): never
    {
        throw new InvalidCharacterException(sprintf('Prohibited input U+%08X', $codePoint), 101);
    }
}
