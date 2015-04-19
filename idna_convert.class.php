<?php
/* ------------------------------------------------------------------------- */
/* idna_convert.class.php - Encode / Decode punycode based domain names      */
/* (c) 2004 blue birdy, Berlin (http://bluebirdy.de)                         */
/* All rights reserved                                                       */
/* v0.2.1                                                                    */
/* ------------------------------------------------------------------------- */

/*
 * This PHP class is derived work from the IDN extension for PHP, originally
 * written by JPNIC in C++ and the ANSI C code from RFC3492, written by
 * Adam M. Costello.
 * PHP port: Copyright 2004 blue birdy
 * All rights reserved.
 *
 * By using this file, you agree to the terms and conditions set forth below
 *                        LICENSE TERMS AND CONDITIONS
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
    //
    // See set_parameter() for fetails of how to change the following settings
    // from within your script / application
    var $use_utf8 =        TRUE;  // Default input charset is UTF-8
    var $allow_overlong =  FALSE; // Overlong UTF-8 encodings are forbidden

    // The constructor
    function idna_convert()
    {
        return TRUE;
    }

    /**
    * Sets a new option value. Available options and values:
    * [utf8 - Use either UTF-8 or ISO-8859-1 as input (TRUE for UTF-8, FALSE
    *         otherwise); The output is always UTF-8]
    * [overlong - Unicode does not allow unnecessarily long encodings of chars,
    *             to allow this, set this parameter to TRUE, else to FALSE;
    *             default is FALSE.]
    *
    * @param    string    Parameter to set
    * @param    string    Value to use
    * @return   boolean   TRUE on success, FALSE otherwise
    * @access   public
    */
    function set_parameter($option, $value = FALSE)
    {
        switch ($option) {
        case 'utf8': $this->use_utf8 = ($value) ? TRUE : FALSE; break;
        case 'overlong': $this->allow_overlong = ($value) ? TRUE : FALSE; break;
        default: $this->_error('Unknown option '.$option); return FALSE;
        }
        return TRUE;
    }

    // Decode a given Domain name
    // Your input is expected to be 7bit only
    function decode($encoded)
    {
        // Clean up input
        $encoded = trim($encoded);
        // Call actual wrapper
        $decoded = $this->_do_job($encoded, 'decode');
        return $decoded;
    }

    // Encode a given Domain name
    // The output will be - on success - 7bit only
    function encode($decoded)
    {
        // Clean up input (This will be done by nameprep, once it is ready)
        // $decoded = str_replace('ß', 'ss', strtolower(trim($decoded)));
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
        if ($delim_pos > strlen($this->punycode_prefix)) {
            for ($k = strlen($this->punycode_prefix); $k < $delim_pos; ++$k) {
                $decoded[] = ord($encoded{$k});
            }
        } else {
            $decoded = array();
        }
        $deco_len = count($decoded); echo $deco_len.'<br />';
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
            if ($deco_len > 0) {
                // Make room for the decoded char
                for ($i = $deco_len; $i > $idx; $i--) {
                    $decoded[$i] = $decoded[($i - 1)];
                }
            }
            $decoded[$idx++] = $char;
        }
        return $this->ucs4_to_utf8($decoded);
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
        if (preg_match('!^'.preg_quote($this->punycode_prefix, '!u').'!', $decoded)) {
            $this->_error('This is already a punycode string');
            return FALSE;
        }
        // We will not try to encode strings consisting of basic code points only
        if (!preg_match('![^0-9a-zA-Z-]!u', $decoded)) {
            $this->_error('The given string does not contain encodable chars');
            return FALSE;
        }

        if ($this->use_utf8) {
            $decoded = $this->utf8_to_ucs4($decoded);
        } else {
            $d_s = array();
            for ($k = 0; $k < strlen($decoded); ++$k) {
                $d_s[$k] = $decoded{$k};
            }
            $decoded = &$d_s;
        }
        $deco_len  = count($decoded);
        if (!$deco_len) return FALSE; // UTF-8 to UCS conversion failed

        $codecount = 0; // How many chars have been consumed

        // Start with the prefix; copy it to output
        $encoded = $this->punycode_prefix;
        // Copy all basic code points to output
        for ($i = 0; $i < $deco_len; ++$i) {
            if (preg_match('![0-9a-zA-Z-]!', chr($decoded[$i]))) {
                $encoded .= chr($decoded[$i]);
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
                if ($decoded[$i] >= $cur_code && $decoded[$i] <= $next_code) {
                    $next_code = $decoded[$i];
                }
            }

            $delta += ($next_code - $cur_code) * ($codecount + 1);
            $cur_code = $next_code;

            // Scan input again and encode all characters whose code point is $cur_code
            for ($i = 0; $i < $deco_len; $i++) {
                if ($decoded[$i] < $cur_code) {
                    $delta++;
                } elseif ($decoded[$i] == $cur_code) {
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

    // This converts an UTF-8 encoded string to its UCS-4 representation
    // By talking about UCS-4 "strings" we mean arrays of 32bit integers representing
    // each of the "chars". This is due to PHP not being able to handle strings with
    // bit depth different from 8. This apllies to the reverse method ucs4_to_utf8(), too.
    // The following UTF-8 encodings are supported:
    // bytes bits  representation
    // 1        7  0xxxxxxx
    // 2       11  110xxxxx 10xxxxxx
    // 3       16  1110xxxx 10xxxxxx 10xxxxxx
    // 4       21  11110xxx 10xxxxxx 10xxxxxx 10xxxxxx
    // 5       26  111110xx 10xxxxxx 10xxxxxx 10xxxxxx 10xxxxxx
    // 6       31  1111110x 10xxxxxx 10xxxxxx 10xxxxxx 10xxxxxx 10xxxxxx
    // Each x represents a bit that can be used to store character data.
    function utf8_to_ucs4($input)
    {
        $output = array();
        $out_len = 0;
        $inp_len = strlen($input);
        $mode = 'next';
        for ($k = 0; $k < $inp_len; ++$k) {
            $v = ord($input{$k}); // Extract byte from input string
            // echo chr($v).' '.$this->show_bitmask($v).' '.join('.', $output).'<br />';

            if ($v < 128) { // We found an ASCII char - put into stirng as is
                $output[$out_len] = $v;
                ++$out_len;
                if ('add' == $mode) {
                    $this->_error('Conversion from UTF-8 to UCS-4 failed: malformed input at byte '.$k);
                    return FALSE;
                }
                continue;
            }
            if ('next' == $mode) { // Try to find the next start byte; determine the width of the Unicode char
                if ($v >> 5 == 6) { // &110xxxxx 10xxxxx
                    $mode = 'add';
                    $next_byte = 0; // Tells, how many times subsequent bitmasks must rotate 6bits to the left
                    $v = ($v - 192) << 6;
                } elseif ($v >> 4 == 14) { // &1110xxxx 10xxxxxx 10xxxxxx
                    $mode = 'add';
                    $next_byte = 1;
                    $v = ($v - 224) << 12;
                } elseif ($v >> 3 == 30) { // &11110xxx 10xxxxxx 10xxxxxx 10xxxxxx
                    $mode = 'add';
                    $next_byte = 2;
                    $v = ($v - 240) << 18;
                } elseif ($v >> 2 == 62) { // &111110xx 10xxxxxx 10xxxxxx 10xxxxxx 10xxxxxx
                    $mode = 'add';
                    $next_byte = 3;
                    $v = ($v - 248) << 24;
                } elseif ($v >> 1 == 126) { // &1111110x 10xxxxxx 10xxxxxx 10xxxxxx 10xxxxxx 10xxxxxx
                    $mode = 'add';
                    $next_byte = 4;
                    $v = ($v - 252) << 30;
                } else {
                    $this->_error('This might be UTF-8, but I don\'t understand it at byte '.$k);
                    return FALSE;
                }
                if ('add' == $mode) {
                    $output[$out_len] = (int) $v;
                    ++$out_len;
                    continue;
                }
            }
            if ('add' == $mode) {
                if ($v == 128 && !$this->allow_overlong) {
                    $this->_error('Bogus UTF-8 character detected (unnecessarily long encoding) at byte '.$k);
                    return FALSE;
                }
                if ($v >> 6 == 2) { // Bit mask must be 10xxxxxx
                    $v = ($v - 128) << ($next_byte * 6);
                    $output[($out_len - 1)] += $v;
                    --$next_byte;
                } else {
                    $this->_error('Conversion from UTF-8 to UCS-4 failed: malformed input at byte '.$k);
                    return FALSE;
                }
                if ($next_byte < 0) {
                    $mode = 'next';
                }
            }
        } // for
        return $output;
    }

    // Convert UCS-4 string into UTF-8 string
    // See utf8_to_ucs4() for details
    function ucs4_to_utf8($input)
    {
        $output = '';
        foreach ($input as $v) {
            // $v = ord($v);
            if ($v < 128) { // 7bit are transferred literally
                $output .= chr($v);
            } elseif ($v < 1 << 11) { // 2 bytes
                $output .= chr(192 + ($v >> 6)) . chr(128 + ($v & 63));
            } elseif ($v < 1 << 16) { // 3 bytes
                $output .= chr(224 + ($v >> 12)) . chr(128 + (($v >> 6) & 63)) . chr(128 + ($v & 63));
            } elseif ($v < 1 << 21) { // 4 bytes
                $output .= chr(240 + ($v >> 18)) . chr(128 + (($v >> 12) & 63)) . chr(128 + (($v >> 6) & 63)) . chr(128 + ($v & 63));
            } elseif ($v < 1 << 26) { // 5 bytes
                $output .= chr(248 + ($v >> 24)) . chr(128 + (($v >> 18) & 63)) . chr(128 + (($v >> 12) & 63)) . chr(128 + (($v >> 6) & 63))
                                                 . chr(128 + ($v & 63));
            } elseif ($v < 1 << 31) { // 6 bytes
                $output .= chr(252 + ($v >> 30)) . chr(128 + (($v >> 24) & 63)) . chr(128 + (($v >> 18) & 63)) . chr(128 + (($v >> 12) & 63))
                                                 . chr(128 + (($v >> 6) & 63)) . chr(128 + ($v & 63));
            } else {
                $this->_error('Conversion from UCS-4 to UTF-8 failed: malformed input at byte '.$k);
                return FALSE;
            }
        }
        return $output;
    }

    // Gives you a bit representation of given Byte (8 bits), Word (16 bits) or DWord (32 bits)
    // Output width is automagically determined
    function show_bitmask ($octet)
    {
        if ($octet >= (1 << 16)) $w = 31;
        elseif ($octet >= (1 << 8)) $w = 15;
        else $w = 7;
        $return = '';
        for ($i = $w; $i > -1; $i--) {
            $return .= ($octet & (1 << $i)) ? 1 : '0';
        }
        return $return;
    }
}

?>