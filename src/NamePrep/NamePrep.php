<?php

namespace Algo26\IdnaConvert\NamePrep;

use Algo26\IdnaConvert\Exception\InvalidIdnVersionException;

class NamePrep
{
    const sBase = 0xAC00;
    const lBase = 0x1100;
    const vBase = 0x1161;
    const tBase = 0x11A7;
    const lCount = 19;
    const vCount = 21;
    const tCount = 28;
    const nCount = 588;   // vCount * tCount
    const sCount = 11172; // lCount * tCount * vCount
    const sLast = self::sBase + self::lCount * self::vCount * self::tCount;

    /** @var NamePrepDataInterface */
    private $namePrepData;

    /**
     * @param int
     *
     * @throws \Algo26\IdnaConvert\Exception\InvalidIdnVersionException
     */
    public function __construct($idnVersion = 2008)
    {
        if ($idnVersion !== 2003
            && $idnVersion !== 2008) {
            throw new InvalidIdnVersionException('IDN version must bei either 2003 or 2008');
        }

        $namePrepDataClass = sprintf('NamePrepData%d', $idnVersion);
        $this->namePrepData = new $namePrepDataClass();
    }
    
    public function do(array $input)
    {
        $output = [];
        //
        // Mapping
        // Walking through the input array, performing the required steps on each of
        // the input chars and putting the result into the output array
        // While mapping required chars we apply the canonical ordering
        foreach ($input as $v) {
            // Map to nothing == skip that code point
            if (in_array($v, $this->namePrepData->mapToNothing)) {
                continue;
            }
            // Try to find prohibited input
            if (in_array($v, $this->namePrepData->prohibit) || in_array($v, $this->namePrepData->generalProhibited)) {
                throw new \InvalidArgumentException(sprintf('NAMEPREP: Prohibited input U+%08X', $v), 101);
            }
            foreach ($this->namePrepData->prohibitRanges as $range) {
                if ($range[0] <= $v && $v <= $range[1]) {
                    throw new \InvalidArgumentException(sprintf('NAMEPREP: Prohibited input U+%08X', $v), 102);
                }
            }

            if (0xAC00 <= $v && $v <= 0xD7AF) {
                // Hangul syllable decomposition
                foreach ($this->hangulDecompose($v) as $out) {
                    $output[] = (int) $out;
                }
            } elseif (isset($this->namePrepData->replaceMaps[$v])) {
                foreach ($this->applyCanonicalOrdering($this->namePrepData->replaceMaps[$v]) as $out) {
                    $output[] = (int) $out;
                }
            } else {
                $output[] = (int) $v;
            }
        }
        // Before applying any Combining, try to rearrange any Hangul syllables
        $output = $this->hangulCompose($output);
        //
        // Combine code points
        //
        $last_class = 0;
        $last_starter = 0;
        $out_len = count($output);
        for ($i = 0; $i < $out_len; ++$i) {
            $class = $this->getCombiningClass($output[$i]);
            if ((!$last_class || $last_class > $class) && $class) {
                // Try to match
                $seq_len = $i - $last_starter;
                $out = $this->combine(array_slice($output, $last_starter, $seq_len));
                // On match: Replace the last starter with the composed character and remove
                // the now redundant non-starter(s)
                if ($out) {
                    $output[$last_starter] = $out;
                    if (count($out) != $seq_len) {
                        for ($j = $i + 1; $j < $out_len; ++$j) {
                            $output[$j - 1] = $output[$j];
                        }
                        unset($output[$out_len]);
                    }
                    // Rewind the for loop by one, since there can be more possible compositions
                    $i--;
                    $out_len--;
                    $last_class = ($i == $last_starter) ? 0 : $this->getCombiningClass($output[$i - 1]);

                    continue;
                }
            }
            // The current class is 0
            if (!$class) {
                $last_starter = $i;
            }
            $last_class = $class;
        }

        return $output;
    }


    /**
     * Decomposes a Hangul syllable
     * (see http://www.unicode.org/unicode/reports/tr15/#Hangul
     * @param    integer  32bit UCS4 code point
     * @return   array    Either Hangul Syllable decomposed or original 32bit value as one value array
     */
    private function hangulDecompose($char)
    {
        $sindex = (int) $char - self::sBase;
        if ($sindex < 0 || $sindex >= self::sCount) {
            return [$char];
        }

        $result = [];
        $result[] = (int) self::lBase + $sindex / self::nCount;
        $result[] = (int) self::vBase + ($sindex % self::nCount) / self::tCount;
        $T = intval(self::tBase + $sindex % self::tCount);
        if ($T != self::tBase) {
            $result[] = $T;
        }

        return $result;
    }

    /**
     * Ccomposes a Hangul syllable
     * (see http://www.unicode.org/unicode/reports/tr15/#Hangul
     * @param  array $input   Decomposed UCS4 sequence
     * @return array UCS4 sequence with syllables composed
     */
    private function hangulCompose($input)
    {
        $inp_len = count($input);
        if (!$inp_len) {
            return [];
        }

        $result = [];
        $last = (int) $input[0];
        $result[] = $last; // copy first char from input to output

        for ($i = 1; $i < $inp_len; ++$i) {
            $char = (int) $input[$i];
            $sindex = $last - self::sBase;
            $lindex = $last - self::lBase;
            $vindex = $char - self::vBase;
            $tindex = $char - self::tBase;
            // Find out, whether two current characters are LV and T
            if (0 <= $sindex && $sindex < self::sCount && ($sindex % self::tCount == 0) && 0 <= $tindex && $tindex <= self::tCount) {
                // create syllable of form LVT
                $last += $tindex;
                $result[(count($result) - 1)] = $last; // reset last
                continue; // discard char
            }
            // Find out, whether two current characters form L and V
            if (0 <= $lindex && $lindex < self::lCount && 0 <= $vindex && $vindex < self::vCount) {
                // create syllable of form LV
                $last = (int) self::sBase + ($lindex * self::vCount + $vindex) * self::tCount;
                $result[(count($result) - 1)] = $last; // reset last
                continue; // discard char
            }
            // if neither case was true, just add the character
            $last = $char;
            $result[] = $char;
        }

        return $result;
    }

    /**
     * Returns the combining class of a certain wide char
     * @param integer  $char  Wide char to check (32bit integer)
     * @return integer Combining class if found, else 0
     */
    private function getCombiningClass($char)
    {
        return isset($this->namePrepData->normalizeCombiningClasses[$char])
            ? $this->namePrepData->normalizeCombiningClasses[$char]
            : 0;
    }

    /**
     * Applies the canonical ordering of a decomposed UCS4 sequence
     * @param array  $input Decomposed UCS4 sequence
     * @return array Ordered USC4 sequence
     */
    private function applyCanonicalOrdering($input)
    {
        $swap = true;
        $size = count($input);
        while ($swap) {
            $swap = false;
            $last = $this->getCombiningClass(intval($input[0]));
            for ($i = 0; $i < $size - 1; ++$i) {
                $next = $this->getCombiningClass(intval($input[$i + 1]));
                if ($next !== 0 && $last > $next) {
                    // Move item leftward until it fits
                    for ($j = $i + 1; $j > 0; --$j) {
                        if ($this->getCombiningClass(intval($input[$j - 1])) <= $next) {
                            break;
                        }
                        $t = intval($input[$j]);
                        $input[$j] = intval($input[$j - 1]);
                        $input[$j - 1] = $t;
                        $swap = true;
                    }
                    // Reentering the loop looking at the old character again
                    $next = $last;
                }
                $last = $next;
            }
        }

        return $input;
    }

    /**
     * Do composition of a sequence of starter and non-starter
     * @param   array $input UCS4 Decomposed sequence
     * @return  array|false  Ordered USC4 sequence
     */
    private function combine($input)
    {
        $inp_len = count($input);
        if (0 === $inp_len) {
            return false;
        }

        foreach ($this->namePrepData->replaceMaps as $np_src => $np_target) {
            if ($np_target[0] !== $input[0]) {
                continue;
            }
            if (count($np_target) !== $inp_len) {
                continue;
            }
            $hit = false;
            foreach ($input as $k2 => $v2) {
                if ($v2 === $np_target[$k2]) {
                    $hit = true;
                } else {
                    $hit = false;
                    break;
                }
            }
            if ($hit) {
                return $np_src;
            }
        }

        return false;
    }
}
