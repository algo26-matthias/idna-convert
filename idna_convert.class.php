<?php
/* ------------------------------------------------------------------------- */
/* idna_convert.class.php - Encode / Decode punycode based domain names      */
/* (c) 2004 blue birdy, Berlin (http://bluebirdy.de)                         */
/* The code is based loosely on work of the Japan Network Information Center */
/* All rights reserved                                                       */
/* v0.1.2                                                                    */
/* ------------------------------------------------------------------------- */

/*
 * C++ Original: Copyright (c) 2001, 2002 Japan Network Information Center.
 * This PHP class is derived work from the IDN extension for PHP, originally
 * written by JPNIC in C++.
 * Since this original work closely implements the algorithms from RFC 3492
 * as this PHP code does, we consider it being just an add on for all of you
 * whose hosting provider refuses to use the original extension.
 * PHP port: Copyright 2004 blue birdy
 * All rights reserved.
 *
 *
 * By using this file, you agree to the terms and conditions set forth bellow.
 *
 *                         LICENSE TERMS AND CONDITIONS
 *
 * The following License Terms and Conditions apply, unless a different
 * license is obtained from blue birdy,
 * c/o M. Sommerfeld, Schmidstr. 7, 10179 Berlin, Germany,
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
    var $punycode_prefix =       'xn--';
    var $invalid_ucs =           0x80000000;
    var $max_ucs =               0x10ffff;
    var $punycode_base =         36;
    var $punycode_tmin =         1;
    var $punycode_tmax =         26;
    var $punycode_skew =         38;
    var $punycode_damp =         700;
    var $punycode_initial_bias = 72;
    var $punycode_initial_n =    0x80;
    var $punycode_base36 =       'abcdefghijklmnopqrstuvwxyz0123456789';
    var $error =                 FALSE;

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

        // We need it later on, believe me
        $this->encoded = $encoded;

        // Wandering through the strings; init
        $is_first = TRUE;
        $bias     = $this->punycode_initial_bias;
        $deco_idx = 0;
        $idx      = 0;
        $enco_idx = $delim_pos+1;
        $char     = $this->punycode_initial_n;

        while ($enco_idx < $enco_len) {
            $len = $this->_getwc($enco_idx, $enco_len - $enco_idx, $bias, &$delta);
            if (!$len) {
                $this->_error('Invalid encoding');
                return FALSE;
            }
            $enco_idx += $len;
            $bias = $this->_adapt($delta, $deco_len + 1, $is_first);
            $is_first = FALSE;
            $idx += $delta;
            $char += ($idx / ($deco_len + 1)) % 256;
            $deco_idx = $idx % ($deco_len + 1);

            if ($deco_len > 0) {
                for ($i = $deco_len; $i > $deco_idx; $i--) {
                    $decoded{$i} = $decoded{($i - 1)};
                }
                $decoded{$deco_idx} = chr($char);
            } else {
                $decoded = chr($char);
            }
            $deco_len++;
            $idx = $deco_idx + 1;
        }
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
        // We will not try to encode string containing of basic code points only
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
        $cur_code  = $this->punycode_initial_n;
        $bias      = $this->punycode_initial_bias;
        $delta     = 0;
        while ($codecount < $deco_len) {
            $limit = -1;
            $rest  =  0;
            $enco_len  = strlen($encoded);
            $next_code = $this->max_ucs;
            // Find the smallest code point >= the current code point and
            // remember the last ouccrence of it in the input
            for ($i = $deco_len - 1; $i >= 0; $i--) {
                if (ord($decoded{$i}) >= $cur_code && ord($decoded{$i}) <= $next_code) {
                    $next_code = ord($decoded{$i});
                    $limit     = $i;
                }
            }
            // There must be such code point.
            if (!($limit+1)) {
                $this->_error('Codepoint out of range, I am helpless');
                return FALSE;
            }

            $delta += ($next_code - $cur_code) * ($codecount + 1);
            $cur_code = $next_code;

            // Scan input again and encode all characters whose code point is $cur_code
            for ($i = 0, $rest = $codecount; $i < $deco_len; $i++) {
                    if (ord($decoded{$i}) < $cur_code) {
                        $delta++;
                        $rest--;
                    } elseif (ord($decoded{$i}) == $cur_code) {
                        $sz = $this->_putwc($enco_len, $delta, $bias);
                        if (!$sz) {
                            $this->_error('Invalid input string; cannot encode it');
                            return FALSE;
                        }
                        $encoded .= $sz;
                        $codecount++;
                        $bias = $this->_adapt($delta, $codecount, $is_first);
                        $delta = 0;
                        $is_first = FALSE;
                    }
            }
            $delta += $rest + 1;
            $cur_code++;
        }
        return $encoded;
    }

    // Convert Delta and Bias back to char and position
    function _getwc($char, $len, $bias, &$delta)
    {
        $orglen = $len;
        $v = 0;
        $w = 1;
        for ($k = $this->punycode_base - $bias; $len > 0; $k += $this->punycode_base) {
            $c = ord($this->encoded{$char});
            ++$char;
            $t = ($k < $this->punycode_tmin) ? $this->punycode_tmin :
                 (($k > $this->punycode_tmax) ? $this->punycode_tmax : $k);

            $len--;
            if (ord('a') <= $c && $c <= ord('z')) {
                $c = $c - ord('a');
            } elseif (ord('A') <= $c && $c <= ord('Z')) {
                $c = $c - ord('A');
            } elseif (ord('0') <= $c && $c <= ord('9')) {
                $c = $c - ord('0') + 26;
            } else {
                $c = -1;
            }
            if ($c < 0) return FALSE; // invalid character
            $v += $c * $w;
            if ($c < $t) {
                $delta = $v;
                return ($orglen - $len);
            }
            $w  = $w * ($this->punycode_base - $t);
        }
        return FALSE; // final character missing
    }

    // Convert char and position to base36 string
    function _putwc($len, $delta, $bias)
    {
        $return = '';
        for ($k = $this->punycode_base - $bias; 1; $k += $this->punycode_base) {
            $t = ($k < $this->punycode_tmin) ? $this->punycode_tmin :
                 (($k > $this->punycode_tmax) ? $this->punycode_tmax : $k);
            if ($delta < $t) break;
            if ($len < 1) return FALSE;
            $add = ($t + (($delta - $t) % ($this->punycode_base - $t)));
            $return .= $this->punycode_base36{$add};
            $len--;
            $delta = ($delta - $t) / ($this->punycode_base - $t);
        }
        if ($len < 1) return FALSE;
        $add = $delta;
        $return .= $this->punycode_base36{$add};
        return $return;
    }

    // Adapt the bias according to the current code point and position
    function _adapt($delta, $npoints, $is_first)
    {
        $k = 0;
        $delta = $is_first ? ($delta / $this->punycode_damp) : ($delta / 2);
        $delta += $delta / $npoints;
        while ($delta > (($this->punycode_base - $this->punycode_tmin) * $this->punycode_tmax) / 2) {
            $delta = $delta / ($this->punycode_base - $this->punycode_tmin);
            $k++;
        }
        return ($this->punycode_base * $k +
                ((($this->punycode_base - $this->punycode_tmin + 1) * $delta) /
                 ($delta + $this->punycode_skew)));
    }

    // Internal error handling method
    function _error($error = '')
    {
        $this->error = $error;
    }
}

?>