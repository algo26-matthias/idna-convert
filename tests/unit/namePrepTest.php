<?php

namespace Algo26\IdnaConvert\Test;

use Algo26\IdnaConvert\Exception\InvalidIdnVersionException;
use Algo26\IdnaConvert\NamePrep\NamePrep;
use Algo26\IdnaConvert\TranscodeUnicode\TranscodeUnicode;
use PHPUnit\Framework\TestCase;

class namePrepTest extends TestCase
{
    /** @var TranscodeUnicode */
    private $uctc;

    public function setup()
    {
        $this->uctc = new TranscodeUnicode();
    }

    /**
     * @param array $from provided original string
     * @param array $expectedTo expected result
     *
     * @throws \Algo26\IdnaConvert\Exception\InvalidIdnVersionException
     * @dataProvider providerCases2003
     */
    public function testDo2003(array $from, array $expectedTo)
    {
        $namePrep = new NamePrep(2003);
        $to = $namePrep->do($from);

        $this->assertEquals(
            $expectedTo,
            $to,
            sprintf(
                'Sequences "%s" and "%s" do not match',
                $this->uctc->convert(
                    $expectedTo,
                    $this->uctc::ENCODING_UCS4_ARRAY,
                    $this->uctc::ENCODING_UTF8
                ),
                $this->uctc->convert(
                    $to,
                    $this->uctc::ENCODING_UCS4_ARRAY,
                    $this->uctc::ENCODING_UTF8
                )
            )
        );
    }

    public function providerCases2003()
    {
        return [
            [
                [
                    0x61, 0xAD, 0x34F, 0x1806, 0x180B, 0x180C, 0x180D, 0x200B, 0x200C,
                    0x200D, 0x2060, 0xFE00, 0xFE01, 0xFE02, 0xFE03, 0xFE04, 0xFE05, 0xFE06, 0xFE07,
                    0xFE08, 0xFE09, 0xFE0A, 0xFE0B, 0xFE0C, 0xFE0D, 0xFE0E, 0xFE0F, 0xFEFF, 0x61
                ],
                [
                    0x61, 0x61
                ]
            ]
        ];
    }
}
