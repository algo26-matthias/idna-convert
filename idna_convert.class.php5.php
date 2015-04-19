<?php

// {{{ license

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */
//
// +----------------------------------------------------------------------+
// | This library is free software; you can redistribute it and/or modify |
// | it under the terms of the GNU Lesser General Public License as       |
// | published by the Free Software Foundation; either version 2.1 of the |
// | License, or (at your option) any later version.                      |
// |                                                                      |
// | This library is distributed in the hope that it will be useful, but  |
// | WITHOUT ANY WARRANTY; without even the implied warranty of           |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU    |
// | Lesser General Public License for more details.                      |
// |                                                                      |
// | You should have received a copy of the GNU Lesser General Public     |
// | License along with this library; if not, write to the Free Software  |
// | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307 |
// | USA.                                                                 |
// +----------------------------------------------------------------------+
//

// }}}


/**
 * Encode/decode Internationalized Domain Names.
 *
 * The class allows to convert internationalized domain names
 * (see RFC 3490 for details) as they can be used with various registries worldwide
 * to be translated between their original (localized) form and their encoded form
 * as it will be used in the DNS (Domain Name System).
 *
 * The class provides two public methods, encode() and decode(), which do exactly
 * what you would expect them to do. You are allowed to use complete domain names,
 * simple strings and complete email addresses as well. That means, that you might
 * use any of the following notations:
 *
 * - www.nörgler.com
 * - xn--nrgler-wxa
 * - xn--brse-5qa.xn--knrz-1ra.info
 *
 * Unicode input might be given as either UTF-8 string, UCS-4 string or UCS-4
 * array. Unicode output is available in the same formats.
 * You can select your preferred format via {@link set_paramter()}.
 *
 * ACE input and output is always expected to be ASCII.
 *
 * @author  Markus Nix <mnix@docuverse.de>
 * @author  Matthias Sommerfeld <mso@phlylabs.de>
 * @package Net
 * @version $Id: IDNA.php,v 0.4.2 2005/10/13 18:30 phlylabs_de Exp $
 */

class Net_IDNA_php5
{
    // {{{ npdata
    /**
     * Holds all relevant mapping tables, loaded from a seperate file on construct
     * mapped to nothing, See RFC3454 for details
     *
     * @var array
     * @access private
     */
    private static $_np_ = array();
    // }}}

    // {{{ properties
    /**
     * @var string
     * @access private
     */
    private $_punycode_prefix = 'xn--';

    /**
     * @access private
     */
    private $_invalid_ucs = 0x80000000;

    /**
     * @access private
     */
    private $_max_ucs = 0x10FFFF;

    /**
     * @var int
     * @access private
     */
    private $_base = 36;

    /**
     * @var int
     * @access private
     */
    private $_tmin = 1;

    /**
     * @var int
     * @access private
     */
    private $_tmax = 26;

    /**
     * @var int
     * @access private
     */
    private $_skew = 38;

    /**
     * @var int
     * @access private
     */
    private $_damp = 700;

    /**
     * @var int
     * @access private
     */
    private $_initial_bias = 72;

    /**
     * @var int
     * @access private
     */
    private $_initial_n = 0x80;

    /**
     * @var int
     * @access private
     */
    private $_slast;

    /**
     * @access private
     */
    private $_sbase = 0xAC00;

    /**
     * @access private
     */
    private $_lbase = 0x1100;

    /**
     * @access private
     */
    private $_vbase = 0x1161;

    /**
     * @access private
     */
    private $_tbase = 0x11a7;

    /**
     * @var int
     * @access private
     */
    private $_lcount = 19;

    /**
     * @var int
     * @access private
     */
    private $_vcount = 21;

    /**
     * @var int
     * @access private
     */
    private $_tcount = 28;

    /**
     * vcount * tcount
     *
     * @var int
     * @access private
     */
    private $_ncount = 588;

    /**
     * lcount * tcount * vcount
     *
     * @var int
     * @access private
     */
    private $_scount = 11172;

    /**
     * Default encoding for encode()'s input and decode()'s output is UTF-8;
     * Other possible encodings are ucs4_string and ucs4_array
     * See {@link setParams()} for how to select these
     *
     * @var bool
     * @access private
     */
    private $_api_encoding = 'utf8';

    /**
     * Overlong UTF-8 encodings are forbidden
     *
     * @var bool
     * @access private
     */
    private $_allow_overlong = false;

    /**
     * Behave strict or not
     *
     * @var bool
     * @access private
     */
    private $_strict_mode = false;

    /**
    * In case of error (not caught by Exception handling), the error message can be found here
    *
    * @var string
    * @access public
    */
    public $_error = false;
    // }}}


    // {{{ constructor
    /**
     * Constructor
     *
     * @param  array  $options
     * @access public
     * @see    setParams()
     */
    public function __construct($options = null)
    {
        $this->_slast = $this->_sbase + $this->_lcount * $this->_vcount * $this->_tcount;
        $this->_np_ = unserialize(file_get_contents(dirname(__FILE__).'/npdata.ser'));
        if (is_array($options)) {
            $this->setParams($options);
        }
    }
    // }}}


    /**
    * Sets a new option value. Available options and values:
    * [encoding - Use either UTF-8, UCS4 as array or UCS4 as string as input ('utf8' for UTF-8,
    *         'ucs4_string' and 'ucs4_array' respectively for UCS4); The output is always UTF-8]
    * [overlong - Unicode does not allow unnecessarily long encodings of chars,
    *             to allow this, set this parameter to true, else to false;
    *             default is false.]
    * [strict - true: strict mode, good for registration purposes - Causes errors
    *           on failures; false: loose mode, ideal for "wildlife" applications
    *           by silently ignoring errors and returning the original input instead
    *
    * @param    mixed     Parameter to set (string: single parameter; array of Parameter => Value pairs)
    * @param    string    Value to use (if parameter 1 is a string)
    * @return   boolean   true on success, false otherwise
    * @access   public
    */
    public function setParams($option, $value = false)
    {
        if (!is_array($option)) {
            $option = array($option => $value);
        }

        foreach ($option as $k => $v) {
            switch ($k) {
            case 'encoding':
                switch ($v) {
                case 'utf8':
                case 'ucs4_string':
                case 'ucs4_array':
                    $this->_api_encoding = $v;
                    break;

                default:
                    throw new Exception('Set Parameter: Unknown parameter '.$v.' for option '.$k);
                }

                break;

            case 'overlong':
                $this->_allow_overlong = ($v) ? true : false;
                break;

            case 'strict':
                $this->_strict_mode = ($v) ? true : false;
                break;

            default:
                return false;
            }
        }

        return true;
    }

    /**
     * Encode a given UTF-8 domain name.
     *
     * @param    string     $decoded     Domain name (UTF-8 or UCS-4)
     * [@param    string     $encoding    Desired input encoding, see {@link set_parameter}]
     * @return   string                  Encoded Domain name (ACE string)
     * @return   mixed                   processed string
     * @throws   Exception
     * @access   public
     */
    public function encode($decoded, $one_time_encoding = false)
    {
        // Forcing conversion of input to UCS4 array
        // If one time encoding is given, use this, else the objects property
        switch (($one_time_encoding) ? $one_time_encoding : $this->_api_encoding) {
        case 'utf8':
            $decoded = $this->_utf8_to_ucs4($decoded);
            break;
        case 'ucs4_string':
           $decoded = $this->_ucs4_string_to_ucs4($decoded);
        case 'ucs4_array': // No break; before this line. Catch case, but do nothing
           break;
        default:
            throw new Exception('Unsupported input format');
        }

        // No input, no output, what else did you expect?
        if (empty($decoded)) return '';

        // Anchors for iteration
        $last_begin = 0;
        // Output string
        $output = '';

        foreach ($decoded as $k => $v) {
            // Make sure to use just the plain dot
            switch($v) {
            case 0x3002:
            case 0xFF0E:
            case 0xFF61:
                $decoded[$k] = 0x2E;
                // It's right, no break here
                // The codepoints above have to be converted to dots anyway

            // Stumbling across an anchoring character
            case 0x2E:
            case 0x2F:
            case 0x3A:
            case 0x3F:
            case 0x40:
                // Neither email addresses nor URLs allowed in strict mode
                if ($this->_strict_mode) {
                   throw new Exception('Neither email addresses nor URLs are allowed in strict mode.');
                } else {
                    // Skip first char
                    if ($k) {
                        $encoded = '';
                        $encoded = $this->_encode(array_slice($decoded, $last_begin, (($k)-$last_begin)));
                        if ($encoded) {
                            $output .= $encoded;
                        } else {
                            $output .= $this->_ucs4_to_utf8(array_slice($decoded, $last_begin, (($k)-$last_begin)));
                        }
                        $output .= chr($decoded[$k]);
                    }
                    $last_begin = $k + 1;
                }
            }
        }
        // Catch the rest of the string
        if ($last_begin) {
            $inp_len = sizeof($decoded);
            $encoded = '';
            $encoded = $this->_encode(array_slice($decoded, $last_begin, (($inp_len)-$last_begin)));
            if ($encoded) {
                $output .= $encoded;
            } else {
                $output .= $this->_ucs4_to_utf8(array_slice($decoded, $last_begin, (($inp_len)-$last_begin)));
            }
            return $output;
        } else {
            if ($output = $this->_encode($decoded)) {
                return $output;
            } else {
                return $this->_ucs4_to_utf8($decoded);
            }
        }
    }

    /**
     * Decode a given ACE domain name.
     *
     * @param    string     $encoded     Domain name (ACE string)
     * [@param    string     $encoding    Desired output encoding, see {@link set_parameter}]
     * @return   string                  Decoded Domain name (UTF-8 or UCS-4)
     * @throws   Exception
     * @access   public
     */
    public function decode($input, $one_time_encoding = false)
    {
        // Optionally set
        if ($one_time_encoding) {
            switch ($one_time_encoding) {
            case 'utf8':
            case 'ucs4_string':
            case 'ucs4_array':
                break;
            default:
                $this->_error('Unknown encoding '.$one_time_encoding);
                return false;
            }
        }
        // Make sure to drop any newline characters around
        $input = trim($input);

        // Negotiate input and try to determine, wether it is a plain string,
        // an email address or something like a complete URL
        if (strpos($input, '@')) { // Maybe it is an email address
            // No no in strict mode
            if ($this->_strict_mode) {
                throw new Exception('Only simple domain name parts can be handled in strict mode');
            }
            list($email_pref, $input) = explode('@', $input, 2);
            $arr = explode('.', $input);
            foreach ($arr as $k => $v) {
                $conv = $this->_decode($v);
                if ($conv) $arr[$k] = $conv;
            }
            $return = $email_pref . '@' . join('.', $arr);
        } elseif (preg_match('![:\./]!', $input)) { // Or a complete domain name (with or without paths / parameters)
            // No no in strict mode
            if ($this->_strict_mode) {
                throw new Exception('Only simple domain name parts can be handled in strict mode');
            }
            $parsed = parse_url($input);
            if (isset($parsed['host'])) {
                $arr = explode('.', $parsed['host']);
                foreach ($arr as $k => $v) {
                    $conv = $this->_decode($v);
                    if ($conv) $arr[$k] = $conv;
                }
                $parsed['host'] = join('.', $arr);
                $return =
                        (empty($parsed['scheme']) ? '' : $parsed['scheme'].(strtolower($parsed['scheme']) == 'mailto' ? ':' : '://'))
                        .(empty($parsed['user']) ? '' : $parsed['user'].(empty($parsed['pass']) ? '' : ':'.$parsed['pass']).'@')
                        .$parsed['host']
                        .(empty($parsed['port']) ? '' : ':'.$parsed['port'])
                        .$parsed['path']
                        .(empty($parsed['query']) ? '' : '?'.$parsed['query'])
                        .(empty($parsed['fragment']) ? '' : '#'.$parsed['fragment']);
            } else { // parse_url seems to have failed, try without it
                $arr = explode('.', $input);
                foreach ($arr as $k => $v) {
                    $conv = $this->_decode($v);
                    if ($conv) $arr[$k] = $conv;
                }
                $return = join('.', $arr);
            }
        } else { // Otherwise we consider it being a pure domain name string
            $return = $this->_decode($input);
        }
        // The output is UTF-8 by default, other output formats need conversion here
        // If one time encoding is given, use this, else the objects property
        switch (($one_time_encoding) ? $one_time_encoding : $this->_api_encoding) {
        case 'utf8':
            return $return;
            break;
        case 'ucs4_string':
           return $this->_ucs4_to_ucs4_string($this->_utf8_to_ucs4($return));
           break;
        case 'ucs4_array':
            return $this->_utf8_to_ucs4($return);
            break;
        default:
            throw new Exception('Unsupported output format');
        }
    }


    // {{{ private
    /**
     * The actual encoding algorithm.
     *
     * @return   string
     * @throws   Exception
     * @access   private
     */
    private function _encode($decoded)
    {
        // We cannot encode a domain name containing the Punycode prefix
        $extract = strlen($this->_punycode_prefix);
        $check_pref = $this->_utf8_to_ucs4($this->_punycode_prefix);
        $check_deco = array_slice($decoded, 0, $extract);

        if ($check_pref == $check_deco) {
            throw new Exception('This is already a punycode string');
        }
        // We will not try to encode strings consisting of basic code points only
        $encodable = false;
        foreach ($decoded as $k => $v) {
            if ($v > 0x7a) {
                $encodable = true;
                break;
            }
        }
        if (!$encodable) {
            if ($this->_strict_mode) {
                throw new Exception('The given string does not contain encodable chars');
            } else {
                return false;
            }
        }

        // Do NAMEPREP
        try {
            $decoded = $this->_nameprep($decoded);
        } catch (Exception $e) {
            // hmm, serious - rethrow
            throw $e;
        }

        $deco_len = count($decoded);

        // Empty array
        if (!$deco_len) {
            return false;
        }

        // How many chars have been consumed
        $codecount = 0;

        // Start with the prefix; copy it to output
        $encoded = $this->_punycode_prefix;

        $encoded = '';
        // Copy all basic code points to output
        for ($i = 0; $i < $deco_len; ++$i) {
            if (preg_match('![0-9a-zA-Z-]!', chr($decoded[$i]))) {
                $encoded .= chr($decoded[$i]);
                $codecount++;
            }
        }

        // All codepoints were basic ones
        if ($codecount == $deco_len) {
            return $encoded;
        }
        // Start with the prefix; copy it to output
        $encoded = $this->_punycode_prefix . $encoded;

        // If we have basic code points in output, add an hyphen to the end
        if ($codecount) {
            $encoded .= '-';
        }

        // Now find and encode all non-basic code points
        $is_first  = true;
        $cur_code  = $this->_initial_n;
        $bias      = $this->_initial_bias;
        $delta     = 0;

        while ($codecount < $deco_len) {
            // Find the smallest code point >= the current code point and
            // remember the last ouccrence of it in the input
            for ($i = 0, $next_code = $this->_max_ucs; $i < $deco_len; $i++) {
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
                } else if ($decoded[$i] == $cur_code) {
                    for ($q = $delta, $k = $this->_base; 1; $k += $this->_base) {
                        $t = ($k <= $bias)?
                            $this->_tmin :
                            (($k >= $bias + $this->_tmax)? $this->_tmax : $k - $bias);

                        if ($q < $t) {
                            break;
                        }

                        $encoded .= $this->_encodeDigit(ceil($t + (($q - $t) % ($this->_base - $t))));
                        $q = ($q - $t) / ($this->_base - $t);
                    }

                    $encoded .= $this->_encodeDigit($q);
                    $bias = $this->_adapt($delta, $codecount + 1, $is_first);
                    $codecount++;
                    $delta = 0;
                    $is_first = false;
                }
            }

            $delta++;
            $cur_code++;
        }

        return $encoded;
    }

    /**
     * The actual decoding algorithm.
     *
     * @return   string
     * @throws   Exception
     * @access   private
     */
    private function _decode($encoded)
    {
        // We do need to find the Punycode prefix
        if (!preg_match('!^' . preg_quote($this->_punycode_prefix, '!') . '!', $encoded)) {
            return false;
        }

        $encode_test = preg_replace('!^' . preg_quote($this->_punycode_prefix, '!') . '!', '', $encoded);

        // If nothing left after removing the prefix, it is hopeless
        if (!$encode_test) {
            return false;
        }

        // Find last occurence of the delimiter
        $delim_pos = strrpos($encoded, '-');

        if ($delim_pos > strlen($this->_punycode_prefix)) {
            for ($k = strlen($this->_punycode_prefix); $k < $delim_pos; ++$k) {
                $decoded[] = ord($encoded{$k});
            }
        } else {
            $decoded = array();
        }

        $deco_len = count($decoded);
        $enco_len = strlen($encoded);

        // Wandering through the strings; init
        $is_first = true;
        $bias     = $this->_initial_bias;
        $idx      = 0;
        $char     = $this->_initial_n;

        for ($enco_idx = ($delim_pos)? ($delim_pos + 1) : 0; $enco_idx < $enco_len; ++$deco_len) {
            for ($old_idx = $idx, $w = 1, $k = $this->_base; 1 ; $k += $this->_base) {
                $digit = $this->_decodeDigit($encoded{$enco_idx++});
                $idx += $digit * $w;

                $t = ($k <= $bias) ?
                    $this->_tmin :
                    (($k >= $bias + $this->_tmax)? $this->_tmax : ($k - $bias));

                if ($digit < $t) {
                    break;
                }

                $w = (int)($w * ($this->_base - $t));
            }

            $bias      = $this->_adapt($idx - $old_idx, $deco_len + 1, $is_first);
            $is_first  = false;
            $char     += (int) ($idx / ($deco_len + 1));
            $idx      %= ($deco_len + 1);

            if ($deco_len > 0) {
                // Make room for the decoded char
                for ($i = $deco_len; $i > $idx; $i--) {
                    $decoded[$i] = $decoded[($i - 1)];
                }
            }

            $decoded[$idx++] = $char;
        }

        try {
            return $this->_ucs4_to_utf8($decoded);
        } catch (Exception $e) {
            // rethrow
            throw $e;
        }
    }

    /**
     * Adapt the bias according to the current code point and position.
     *
     * @access   private
     */
    private function _adapt($delta, $npoints, $is_first)
    {
        $delta = (int) ($is_first ? ($delta / $this->_damp) : ($delta / 2));
        $delta += (int) ($delta / $npoints);

        for ($k = 0; $delta > (($this->_base - $this->_tmin) * $this->_tmax) / 2; $k += $this->_base) {
            $delta = (int) ($delta / ($this->_base - $this->_tmin));
        }

        return (int) ($k + ($this->_base - $this->_tmin + 1) * $delta / ($delta + $this->_skew));
    }

    /**
     * Encoding a certain digit.
     *
     * @access   private
     */
    private function _encodeDigit($d)
    {
        return chr($d + 22 + 75 * ($d < 26));
    }

    /**
     * Decode a certain digit.
     *
     * @access   private
     */
    private function _decodeDigit($cp)
    {
        $cp = ord($cp);
        return ($cp - 48 < 10)? $cp - 22 : (($cp - 65 < 26)? $cp - 65 : (($cp - 97 < 26)? $cp - 97 : $this->_base));
    }

    /**
     * Do Nameprep according to RFC3491 and RFC3454.
     *
     * @param    array      $input       Unicode Characters
     * @return   string                  Unicode Characters, Nameprep'd
     * @throws   Exception
     * @access   private
     */
    private function _nameprep($input)
    {
        $output = array();

        // Walking through the input array, performing the required steps on each of
        // the input chars and putting the result into the output array
        // While mapping required chars we apply the cannonical ordering

        foreach ($input as $v) {
            // Map to nothing == skip that code point
            if (in_array($v, $this->_np_['map_nothing'])) {
                continue;
            }

            // Try to find prohibited input
            if (in_array($v, $this->_np_['prohibit']) || in_array($v, $this->_np_['general_prohibited'])) {
                throw new Exception('NAMEPREP: Prohibited input U+' . sprintf('%08X', $v));
            }

            foreach ($this->_np_['prohibit_ranges'] as $range) {
                if ($range[0] <= $v && $v <= $range[1]) {
                    throw new Exception('NAMEPREP: Prohibited input U+' . sprintf('%08X', $v));
                }
            }

            // Hangul syllable decomposition
            if (0xAC00 <= $v && $v <= 0xD7AF) {
                foreach ($this->_hangulDecompose($v) as $out) {
                    $output[] = $out;
                }
            } else if (isset($this->_np_['replacemaps'][$v])) { // There's a decomposition mapping for that code point
                foreach ($this->_applyCannonicalOrdering($this->_np_['replacemaps'][$v]) as $out) {
                    $output[] = $out;
                }
            } else {
                $output[] = $v;
            }
        }

        // Combine code points

        $last_class   = 0;
        $last_starter = 0;
        $out_len      = count($output);

        for ($i = 0; $i < $out_len; ++$i) {
            $class = $this->_getCombiningClass($output[$i]);

            if ((!$last_class || $last_class != $class) && $class) {
                // Try to match
                $seq_len = $i - $last_starter;
                $out = $this->_combine(array_slice($output, $last_starter, $seq_len));

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
                    $last_class = ($i == $last_starter)? 0 : $this->_getCombiningClass($output[$i - 1]);

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
     * (see http://www.unicode.org/unicode/reports/tr15/#Hangul).
     *
     * @param    integer    $char        32bit UCS4 code point
     * @return   array                   Either Hangul Syllable decomposed or original 32bit
     *                                   value as one value array
     * @access   private
     */
    private function _hangulDecompose($char)
    {
        $sindex = $char - $this->_sbase;

        if ($sindex < 0 || $sindex >= $this->_scount) {
            return array($char);
        }

        $result   = array();
        $T        = $this->_tbase + $sindex % $this->_tcount;
        $result[] = (int)($this->_lbase +  $sindex / $this->_ncount);
        $result[] = (int)($this->_vbase + ($sindex % $this->_ncount) / $this->_tcount);

        if ($T != $this->_tbase) {
            $result[] = $T;
        }

        return $result;
    }

    /**
     * Ccomposes a Hangul syllable
     * (see http://www.unicode.org/unicode/reports/tr15/#Hangul).
     *
     * @param    array      $input       Decomposed UCS4 sequence
     * @return   array                   UCS4 sequence with syllables composed
     * @access   private
     */
    private function _hangulCompose($input)
    {
        $inp_len = count($input);

        if (!$inp_len) {
            return array();
        }

        $result   = array();
        $last     = $input[0];
        $result[] = $last; // copy first char from input to output

        for ($i = 1; $i < $inp_len; ++$i) {
            $char = $input[$i];

            // Find out, wether two current characters from L and V
            $lindex = $last - $this->_lbase;

            if (0 <= $lindex && $lindex < $this->_lcount) {
                $vindex = $char - $this->_vbase;

                if (0 <= $vindex && $vindex < $this->_vcount) {
                    // create syllable of form LV
                    $last    = ($this->_sbase + ($lindex * $this->_vcount + $vindex) * $this->_tcount);
                    $out_off = count($result) - 1;
                    $result[$out_off] = $last; // reset last

                    // discard char
                    continue;
                }
            }

            // Find out, wether two current characters are LV and T
            $sindex = $last - $this->_sbase;

            if (0 <= $sindex && $sindex < $this->_scount && ($sindex % $this->_tcount) == 0) {
                $tindex = $char - $this->_tbase;

                if (0 <= $tindex && $tindex <= $this->_tcount) {
                    // create syllable of form LVT
                    $last += $tindex;
                    $out_off = count($result) - 1;
                    $result[$out_off] = $last; // reset last

                    // discard char
                    continue;
                }
            }

            // if neither case was true, just add the character
            $last = $char;
            $result[] = $char;
        }

        return $result;
    }

    /**
     * Returns the combining class of a certain wide char.
     *
     * @param    integer    $char        Wide char to check (32bit integer)
     * @return   integer                 Combining class if found, else 0
     * @access   private
     */
    private function _getCombiningClass($char)
    {
        return isset($this->_np_['norm_combcls'][$char]) ? $this->_np_['norm_combcls'][$char] : 0;
    }

    /**
     * Apllies the cannonical ordering of a decomposed UCS4 sequence.
     *
     * @param    array      $input       Decomposed UCS4 sequence
     * @return   array                   Ordered USC4 sequence
     * @access   private
     */
    private function _applyCannonicalOrdering($input)
    {
        $swap = true;
        $size = count($input);

        while ($swap) {
            $swap = false;
            $last = $this->_getCombiningClass($input[0]);

            for ($i = 0; $i < $size - 1; ++$i) {
                $next = $this->_getCombiningClass($input[$i + 1]);

                if ($next != 0 && $last > $next) {
                    // Move item leftward until it fits
                    for ($j = $i + 1; $j > 0; --$j) {
                        if ($this->_getCombiningClass($input[$j - 1]) <= $next) {
                            break;
                        }

                        $t = $input[$j];
                        $input[$j] = $input[$j - 1];
                        $input[$j - 1] = $t;
                        $swap = 1;
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
     * Do composition of a sequence of starter and non-starter.
     *
     * @param    array      $input       UCS4 Decomposed sequence
     * @return   array                   Ordered USC4 sequence
     * @access   private
     */
    private function _combine($input)
    {
        $inp_len = count($input);

        // Is it a Hangul syllable?
        if (1 != $inp_len) {
            $hangul = $this->_hangulCompose($input);

            // This place is probably wrong
            if (count($hangul) != $inp_len) {
                return $hangul;
            }
        }

        foreach ($this->_np_['replacemaps'] as $np_src => $np_target) {
            if ($np_target[0] != $input[0]) {
                continue;
            }

            if (count($np_target) != $inp_len) {
                continue;
            }

            $hit = false;

            foreach ($input as $k2 => $v2) {
                if ($v2 == $np_target[$k2]) {
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

    /**
     * This converts an UTF-8 encoded string to its UCS-4 (array) representation
     * By talking about UCS-4 we mean arrays of 32bit integers representing
     * each of the "chars". This is due to PHP not being able to handle strings with
     * bit depth different from 8. This applies to the reverse method _ucs4_to_utf8(), too.
     * The following UTF-8 encodings are supported:
     *
     * bytes bits  representation
     * 1        7  0xxxxxxx
     * 2       11  110xxxxx 10xxxxxx
     * 3       16  1110xxxx 10xxxxxx 10xxxxxx
     * 4       21  11110xxx 10xxxxxx 10xxxxxx 10xxxxxx
     * 5       26  111110xx 10xxxxxx 10xxxxxx 10xxxxxx 10xxxxxx
     * 6       31  1111110x 10xxxxxx 10xxxxxx 10xxxxxx 10xxxxxx 10xxxxxx
     *
     * Each x represents a bit that can be used to store character data.
     *
     * @access   private
     */
    private function _utf8_to_ucs4($input)
    {
        $output = array();
        $out_len = 0;
        $inp_len = strlen($input);
        $mode = 'next';
        $test = 'none';
        for ($k = 0; $k < $inp_len; ++$k) {
            $v = ord($input{$k}); // Extract byte from input string

            if ($v < 128) { // We found an ASCII char - put into stirng as is
                $output[$out_len] = $v;
                ++$out_len;
                if ('add' == $mode) {
                    $this->_error('Conversion from UTF-8 to UCS-4 failed: malformed input at byte '.$k);
                    return false;
                }
                continue;
            }
            if ('next' == $mode) { // Try to find the next start byte; determine the width of the Unicode char
                $start_byte = $v;
                $mode = 'add';
                $test = 'range';
                if ($v >> 5 == 6) { // &110xxxxx 10xxxxx
                    $next_byte = 0; // Tells, how many times subsequent bitmasks must rotate 6bits to the left
                    $v = ($v - 192) << 6;
                } elseif ($v >> 4 == 14) { // &1110xxxx 10xxxxxx 10xxxxxx
                    $next_byte = 1;
                    $v = ($v - 224) << 12;
                } elseif ($v >> 3 == 30) { // &11110xxx 10xxxxxx 10xxxxxx 10xxxxxx
                    $next_byte = 2;
                    $v = ($v - 240) << 18;
                } elseif ($v >> 2 == 62) { // &111110xx 10xxxxxx 10xxxxxx 10xxxxxx 10xxxxxx
                    $next_byte = 3;
                    $v = ($v - 248) << 24;
                } elseif ($v >> 1 == 126) { // &1111110x 10xxxxxx 10xxxxxx 10xxxxxx 10xxxxxx 10xxxxxx
                    $next_byte = 4;
                    $v = ($v - 252) << 30;
                } else {
                    $this->_error('This might be UTF-8, but I don\'t understand it at byte '.$k);
                    return false;
                }
                if ('add' == $mode) {
                    $output[$out_len] = (int) $v;
                    ++$out_len;
                    continue;
                }
            }
            if ('add' == $mode) {
                if (!$this->_allow_overlong && $test == 'range') {
                    $test = 'none';
                    if (($v < 0xA0 && $start_byte == 0xE0) || ($v < 0x90 && $start_byte == 0xF0) || ($v > 0x8F && $start_byte == 0xF4)) {
                        $this->_error('Bogus UTF-8 character detected (out of legal range) at byte '.$k);
                        return false;
                    }
                }
                if ($v >> 6 == 2) { // Bit mask must be 10xxxxxx
                    $v = ($v - 128) << ($next_byte * 6);
                    $output[($out_len - 1)] += $v;
                    --$next_byte;
                } else {
                    $this->_error('Conversion from UTF-8 to UCS-4 failed: malformed input at byte '.$k);
                    return false;
                }
                if ($next_byte < 0) {
                    $mode = 'next';
                }
            }
        } // for
        return $output;
    }

    /**
     * Convert UCS-4 array into UTF-8 string.
     *
     * @throws   Exception
     * @access   private
     */
    private function _ucs4_to_utf8($input)
    {
        $output = '';

        foreach ($input as $v) {
            // $v = ord($v);

            if ($v < 128) {
                // 7bit are transferred literally
                $output .= chr($v);
            } else if ($v < 1 << 11) {
                // 2 bytes
                $output .= chr(192 + ($v >> 6))
                    . chr(128 + ($v & 63));
            } else if ($v < 1 << 16) {
                // 3 bytes
                $output .= chr(224 + ($v >> 12))
                    . chr(128 + (($v >> 6) & 63))
                    . chr(128 + ($v & 63));
            } else if ($v < 1 << 21) {
                // 4 bytes
                $output .= chr(240 + ($v >> 18))
                    . chr(128 + (($v >> 12) & 63))
                    . chr(128 + (($v >>  6) & 63))
                    . chr(128 + ($v & 63));
            } else if ($v < 1 << 26) {
                // 5 bytes
                $output .= chr(248 + ($v >> 24))
                    . chr(128 + (($v >> 18) & 63))
                    . chr(128 + (($v >> 12) & 63))
                    . chr(128 + (($v >>  6) & 63))
                    . chr(128 + ($v & 63));
            } else if ($v < 1 << 31) {
                // 6 bytes
                $output .= chr(252 + ($v >> 30))
                    . chr(128 + (($v >> 24) & 63))
                    . chr(128 + (($v >> 18) & 63))
                    . chr(128 + (($v >> 12) & 63))
                    . chr(128 + (($v >>  6) & 63))
                    . chr(128 + ($v & 63));
            } else {
                throw new Exception('Conversion from UCS-4 to UTF-8 failed: malformed input at byte ' . $k);
            }
        }

        return $output;
    }

    /**
     * Convert UCS-4 array into UCS-4 string
     *
     * @throws   Exception
     * @access   private
     */
    private function _ucs4_to_ucs4_string($input)
    {
        $output = '';
        // Take array values and split output to 4 bytes per value
        // The bit mask is 255, which reads &11111111
        foreach ($input as $v) {
            $output .= ($v & (255 << 24) >> 24) . ($v & (255 << 16) >> 16) . ($v & (255 << 8) >> 8) . ($v & 255);
        }
        return $output;
    }

    /**
     * Convert UCS-4 strin into UCS-4 garray
     *
     * @throws   Exception
     * @access   private
     */
    private function _ucs4_string_to_ucs4($input)
    {
        $output = array();

        $inp_len = strlen($input);
        // Input length must be dividable by 4
        if ($inp_len % 4) {
            throw new Exception('Input UCS4 string is broken');
            return false;
        }

        // Empty input - return empty output
        if (!$inp_len) return $output;

        for ($i = 0, $out_len = -1; $i < $inp_len; ++$i) {
            // Increment output position every 4 input bytes
            if (!$i % 4) {
                $out_len++;
                $output[$out_len] = 0;
            }
            $output[$out_len] += ord($input{$i}) << (8 * (3 - ($i % 4) ) );
        }
        return $output;
    }

    /**
     * Echo hex representation of UCS4 sequence.
     *
     * @param    array      $input       UCS4 sequence
     * @param    boolean    $include_bit Include bitmask in output
     * @return   void
     * @static
     * @access   private
     */
    private static function _showHex($input, $include_bit = false)
    {
        foreach ($input as $k => $v) {
            echo '[', $k, '] => ', sprintf('%X', $v);

            if ($include_bit) {
                echo ' (', self::_showBitmask($v), ')';
            }

            echo "\n";
        }
    }

    /**
     * Gives you a bit representation of given Byte (8 bits), Word (16 bits) or DWord (32 bits)
     * Output width is automagically determined
     *
     * @static
     * @access   private
     */
    private static function _showBitmask($octet)
    {
        if ($octet >= (1 << 16)) {
            $w = 31;
        } else if ($octet >= (1 << 8)) {
            $w = 15;
        } else {
            $w = 7;
        }

        $return = '';

        for ($i = $w; $i > -1; $i--) {
            $return .= ($octet & (1 << $i))? 1 : '0';
        }

        return $return;
    }
    // }}}}
}

?>