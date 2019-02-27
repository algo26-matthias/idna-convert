<?php
namespace Algo26\IdnaConvert\test;

use Algo26\IdnaConvert\IdnaConvert;
use PHPUnit\Framework\TestCase;

class IdnaConvertDecodeTest extends TestCase
{
    /**
     * @dataProvider providerUtf8
     */
    public function testEncodeUtf8($decoded, $expectEncoded)
    {
        $idnaConv = new IdnaConvert();
        $encoded = $idnaConv->decode($decoded);

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
            ['xn--mller-kva', 'müller'],
            ['xn--weienbach-i1a', 'weißenbach'],
            ['xn----9mcj9fole', 'يوم-جيد'],
            ['xn----2hckbod3a', 'יום-טוב'],
        ];
    }
}