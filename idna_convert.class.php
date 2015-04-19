<?php
/* ------------------------------------------------------------------------- */
/* idna_convert.class.php - Encode / Decode punycode based domain names      */
/* (c) 2004 blue birdy, Berlin (http://bluebirdy.de)                         */
/* All rights reserved                                                       */
/* v0.1.6                                                                    */
/* ------------------------------------------------------------------------- */

/*
 * This PHP class is derived work from the IDN extension for PHP, originally
 * written by JPNIC in C++ and the ANSI C code from RFC3492, written by
 * Adam M. Costello.
 * PHP port: Copyright 2004 blue birdy
 * All rights reserved.
 *
 *
 * By using this file, you agree to the terms and conditions set forth bellow.
 *
 *                         LICENSE TERMS AND CONDITIONS
 *
 * The following License Terms and Conditions apply, unless a different
 * license is obtained from
 * blue birdy, c/o M. Sommerfeld, Schmidstr. 7, 10179 Berlin, Germany.
 *
 * 1. Use, Modification and Redistribution (including distribution of any
 *    modified or derived work) in source and/or binary forms is permitted
 *    under these License Terms and Conditions.
 *
 * 2. Redistribution of source code must retain the copyright notices as they
 *    appear in the source code file, these License Terms and Conditions.
 *
 * 3. Redistribution in binary form must reproduce the Copyright Notice,
 *    these License Terms and Conditions, in the documentation and/or other
 *    materials provided with the distribution.  For the purposes of binary
 *    distribution the "Copyright Notice" refers to the following language:
 *    "(c) 2004 blue birdy, Berlin (http://bluebirdy.de)"
 *
 * 4. The name of blue birdy may not be used to endorse or promote products
 *    derived from this software without specific prior written approval of
 *    blue birdy.
 *
 * 5. Disclaimer/Limitation of Liability: THIS SOFTWARE IS PROVIDED "AS IS"
 *    AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 *    LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A
 *    PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL BLUE BIRDY BE
 *    LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 *    CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR
 *    BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY,
 *    WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR
 *    OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
 *    ADVISED OF THE POSSIBILITY OF SUCH DAMAGES.
 */



class idna_convert
{
    // Internal settings, do not mess with them
    var $punycode_prefix = 'xn--';
    var $invalid_ucs =     0x80000000;
    var $max_ucs =         0x10ffff;
    var $base =            36;
    var $tmin =            1;
    var $tmax =            26;
    var $skew =            38;
    var $damp =            700;
    var $initial_bias =    72;
    var $initial_n =       0x80;
    var $error =           FALSE;

    // The constructor
    function idna_convert()
    {
        return TRUE;
    }

    // Decode a given Domain name
    function decode($encoded)
    {
        // Clean up input
        $encoded = trim($encoded);
        // Call actual wrapper
        return $this->_do_job($encoded, 'decode');
    }

    // Encode a given Domain name
    function encode($decoded)
    {
        // Clean up input
        $decoded = preg_replace('!ß!', 'ss', strtolower(trim($decoded)));
        // Call actual wrapper
        return $this->_do_job($decoded, 'encode');
    }

    // Use this method to get the last error ocurred
    function get_last_error()
    {
        return $this->error;
    }

    // Wrapper class to provide extended functionality
    // This allows for processing complete email addresses and domain names
    function _do_job($input, $mode)
    {
        $method = '_'.$mode;
        // Maybe it is an email address
        if (strpos($input, '@')) {
            list($email_pref, $input) = explode('@', $input, 2);
            $email_pref .= '@';
        } else {
            $email_pref = '';
        }
        // Process any substring
        $arr = explode('.', $input);
        foreach($arr as $k => $v) {
            $conv = $this->$method($v);
            if ($conv) $arr[$k] = $conv;
        }
        return $email_pref . join('.', $arr);
    }

    // The actual decoding algorithm
    function _decode($encoded)
    {
        // We do need to find the Punycode prefix
        if (!preg_match('!^'.preg_quote($this->punycode_prefix, '!').'!', $encoded)) {
            $this->_error('This is not a punycode string');
            return FALSE;
        }
        $encode_test = preg_replace('!^'.preg_quote($this->punycode_prefix, '!').'!', '', $encoded);
        // If nothing left after removing the prefix, it is hopeless
        if (!$encode_test) {
            $this->_error('The given encoded string was empty');
            return FALSE;
        }
        // Find last occurence of the delimiter
        $delim_pos = strrpos($encoded, '-');
        $decoded = ($delim_pos > strlen($this->punycode_prefix))
                 ? substr($encoded, strlen($this->punycode_prefix), ($delim_pos - strlen($this->punycode_prefix)))
                 : '';

        $deco_len = strlen($decoded);
        $enco_len = strlen($encoded);

        // Wandering through the strings; init
        $is_first = TRUE;
        $bias     = $this->initial_bias;
        $idx      = 0;
        $char     = $this->initial_n;

        //while ($enco_idx < $enco_len) {
        for ($enco_idx = ($delim_pos) ? ($delim_pos + 1) : 0; $enco_idx < $enco_len; ++$deco_len) {
            for ($old_idx = $idx, $w = 1, $k = $this->base; 1 ; $k += $this->base) {
                $digit = $this->_decode_digit($encoded{$enco_idx++});
                $idx += $digit * $w;
                $t = ($k <= $bias) ? $this->tmin :
                        (($k >= $bias + $this->tmax) ? $this->tmax : ($k - $bias));
                if ($digit < $t) break;
                $w = (int) ($w * ($this->base - $t));
            }
            $bias = $this->_adapt($idx - $old_idx, $deco_len + 1, $is_first);
            $is_first = FALSE;
            $char += ($idx / ($deco_len + 1)) % 256;
            $idx %= ($deco_len + 1);
            if ($deco_len) {
                for ($i = $deco_len; $i > $idx; $i--) {
                    $decoded{$i} = $decoded{($i - 1)};
                }
            }
            $decoded{$idx++} = chr($char);
        }
        // When trying to put a char into a string on an offset > strlen by $string{$offset},
        // PHP will automagically convert the string to an array.
        // This happens, when the first char to be decoded is not in the first offset
        if (is_array($decoded)) $decoded = join('', $decoded);

        return $decoded;
    }

    // The actual encoding algorithm
    function _encode($decoded)
    {
        // No empty strings please
        if (!$decoded) {
            $this->_error('The given decoded string was empty');
            return FALSE;
        }
        // We cannot encode a domain name containing the Punycode prefix
        if (preg_match('!^'.preg_quote($this->punycode_prefix, '!').'!', $decoded)) {
            $this->_error('This is already a punycode string');
            return FALSE;
        }
        // We will not try to encode strings containing of basic code points only
        if (!preg_match('![\x80-\xff]!', $decoded)) {
            $this->_error('The given string does not contain encodable chars');
            return FALSE;
        }

        $deco_len  = strlen($decoded);
        $codecount = 0; // How many chars have been consumed

        // Start with the prefix; copy it to output
        $encoded = $this->punycode_prefix;
        // Copy all basic code points to output
        for ($i = 0; $i < $deco_len; ++$i) {
            if (preg_match('![0-9a-zA-Z-]!', $decoded{$i})) {
                $encoded .= $decoded{$i};
                $codecount++;
            }
        }
        // If we have basic code points in output, add an hyphen to the end
        if ($codecount) $encoded .= '-';

        // Now find and encode all non-basic code points
        $is_first  = TRUE;
        $cur_code  = $this->initial_n;
        $bias      = $this->initial_bias;
        $delta     = 0;
        while ($codecount < $deco_len) {
            // Find the smallest code point >= the current code point and
            // remember the last ouccrence of it in the input
            for ($i = 0, $next_code = $this->max_ucs; $i < $deco_len; $i++) {
                if (ord($decoded{$i}) >= $cur_code && ord($decoded{$i}) <= $next_code) {
                    $next_code = ord($decoded{$i});
                }
            }

            $delta += ($next_code - $cur_code) * ($codecount + 1);
            $cur_code = $next_code;

            // Scan input again and encode all characters whose code point is $cur_code
            for ($i = 0; $i < $deco_len; $i++) {
                if (ord($decoded{$i}) < $cur_code) {
                    $delta++;
                } elseif (ord($decoded{$i}) == $cur_code) {
                    for ($q = $delta, $k = $this->base; 1; $k += $this->base) {
                        $t = ($k <= $bias) ? $this->tmin :
                                (($k >= $bias + $this->tmax) ? $this->tmax : $k - $bias);
                        if ($q < $t) break;
                        $encoded .= $this->_encode_digit(ceil($t + (($q - $t) % ($this->base - $t))));
                        $q = ($q - $t) / ($this->base - $t);
                    }
                    $encoded .= $this->_encode_digit($q);
                    $bias = $this->_adapt($delta, $codecount+1, $is_first);
                    $codecount++;
                    $delta = 0;
                    $is_first = FALSE;
                }
            }
            $delta++;
            $cur_code++;
        }
        return $encoded;
    }

    // Adapt the bias according to the current code point and position
    function _adapt($delta, $npoints, $is_first)
    {
        $delta = $is_first ? ($delta / $this->damp) : ($delta / 2);
        $delta += $delta / $npoints;
        for ($k = 0; $delta > (($this->base - $this->tmin) * $this->tmax) / 2; $k += $this->base) {
            $delta = $delta / ($this->base - $this->tmin);
        }
        return $k + ($this->base - $this->tmin + 1) * $delta / ($delta + $this->skew);
    }

    //
    function _encode_digit($d)
    {
        return chr($d + 22 + 75 * ($d < 26));
    }

    //
    function _decode_digit($cp)
    {
        $cp = ord($cp);
        return ($cp - 48 < 10) ? $cp - 22 : (($cp - 65 < 26) ? $cp - 65 : (($cp - 97 < 26) ? $cp - 97 : $this->base));
    }

    // Internal error handling method
    function _error($error = '')
    {
        $this->error = $error;
    }
}

?>