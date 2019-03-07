<?php
namespace Algo26\IdnaConvert;

use Algo26\IdnaConvert\Punycode\FromPunycode;
use Algo26\IdnaConvert\TranscodeUnicode\TranscodeUnicode;

class toUnicode extends AbstractIdnaConvert implements IdnaConvertInterface
{
    /** @var TranscodeUnicode */
    private $unicodeTransCoder;

    /** @var FromPunycode */
    private $punycodeEncoder;

    /**
     * @throws Exception\InvalidIdnVersionException
     */
    public function __construct($idnVersion = null)
    {
        $this->unicodeTransCoder = new TranscodeUnicode();
        $this->punycodeEncoder = new FromPunycode($idnVersion);
    }

    public function convert(string $host): string
    {
        // Make sure to drop any newline characters around
        $input = trim($host);
        $return = $this->punycodeEncoder->convert($input);
        if (!$return) {
            $return = $input;
        }

        return $return;
    }

}
