<?php
namespace Algo26\IdnaConvert\test;

use Algo26\IdnaConvert\IdnaConvert;
use PHPUnit\Framework\TestCase;

class IdnaConvertEncodeTest extends TestCase
{
    /**
     * @dataProvider providerUtf8
     */
    public function testEncodeUtf8($decoded, $expectEncoded)
    {
        $idnaConv = new IdnaConvert();
        $encoded = $idnaConv->encode($decoded);

        $this->assertEquals(
            $expectEncoded,
            $encoded,
            sprintf(
                'Strings "%s" and "$s" do not match',
                $expectEncoded, $encoded
            )
        );
    }

    public function providerUtf8()
    {
        return [
            ['müller', 'xn--mller-kva'],
            ['weißenbach', 'xn--weienbach-i1a'],
            ['يوم-جيد', 'xn----9mcj9fole'],
            ['יום-טוב', 'xn----2hckbod3a'],
        ];
    }
}