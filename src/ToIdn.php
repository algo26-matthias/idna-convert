<?php
namespace Algo26\IdnaConvert;

use Algo26\IdnaConvert\Exception\InvalidCharacterException;
use Algo26\IdnaConvert\Exception\InvalidIdnVersionException;
use Algo26\IdnaConvert\Punycode\ToPunycode;
use Algo26\IdnaConvert\TranscodeUnicode\TranscodeUnicode;

class toIdn extends AbstractIdnaConvert implements IdnaConvertInterface
{
    /** @var TranscodeUnicode */
    private $unicodeTransCoder;

    /** @var ToPunycode */
    private $punycodeEncoder;

    /**
     * @throws InvalidIdnVersionException
     */
    public function __construct($idnVersion = null)
    {
        $this->unicodeTransCoder = new TranscodeUnicode();
        $this->punycodeEncoder = new ToPunycode($idnVersion);
    }

    /**
     * @param string $host
     *
     * @return string
     * @throws InvalidCharacterException
     * @throws Exception\AlreadyPunycodeException
     */
    public function convert(string $host): string
    {
        if (strlen($host) === 0) {
            return $host;
        }

        $decoded = $this->unicodeTransCoder->convert($host, 'utf8', 'ucs4array');

        // Anchors for iteration
        $lastBegin = 0;
        // Output string
        $output = '';
        foreach ($decoded as $k => $v) {
            // Make sure to use just the plain dot
            switch ($v) {
                case 0x2F: // /
                case 0x3A: // :
                case 0x3F: // ?
                case 0x40: // @
                    // Neither email addresses nor URLs allowed in strict mode
                    throw new InvalidCharacterException('Neither email addresses nor URLs are allowed', 205);
                    break;
                case 0x3002:
                case 0xFF0E:
                case 0xFF61:
                    $decoded[$k] = 0x2E;
                // Right, no break here, the above are converted to dots anyway
                // Stumbling across an anchoring character
                case 0x2E:
                    // Skip first char
                    if ($k) {
                        $encoded = $this->punycodeEncoder->convert(array_slice($decoded, $lastBegin, (($k) - $lastBegin)));
                        if ($encoded) {
                            $output .= $encoded;
                        } else {
                            $output .= $this->unicodeTransCoder->convert(array_slice($decoded, $lastBegin, (($k) - $lastBegin)), 'ucs4array', 'utf8');
                        }
                        $output .= chr($decoded[$k]);
                    }
                    $lastBegin = $k + 1;

            }
        }
        // Catch the rest of the string
        if ($lastBegin) {
            $inputLength = sizeof($decoded);
            $encoded = $this->punycodeEncoder->convert(array_slice($decoded, $lastBegin, (($inputLength) - $lastBegin)));
            if ($encoded) {
                $output .= $encoded;
            } else {
                $output .= $this->unicodeTransCoder->convert(array_slice($decoded, $lastBegin, (($inputLength) - $lastBegin)), 'ucs4array', 'utf8');
            }

            return $output;
        }

        if (false !== ($output = $this->punycodeEncoder->convert($decoded))) {
            return $output;
        }

        return $this->unicodeTransCoder->convert($decoded, 'ucs4array', 'utf8');
    }
}
