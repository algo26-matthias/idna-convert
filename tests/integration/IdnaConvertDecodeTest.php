<?php
namespace Algo26\IdnaConvert\Test;

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
                'Strings "%s" and "%s" do not match',
                $expectEncoded, $encoded
            )
        );
    }

    /**
     * @return array
     */
    public function providerUtf8()
    {
        return [
            ['xn--mller-kva', 'müller'],
            ['xn--weienbach-i1a', 'weißenbach'],
            ['xn----9mcj9fole', 'يوم-جيد'],
            ['xn----2hckbod3a', 'יום-טוב'],
            ['xn--idndomainaouexample-owb39ane.de', 'idndomainäaöoüuexample.de'],
            ['xn--ko-eka.de', 'öko.de'],
            ['xn--6ca0bl71b4a.no', 'æšŧüø.no'],
            ['xn--4cabegsede9b0e.com', 'ìåíèäæìúíò.com'],
            ['xn--d1abegsede9b0e.com', 'мениджмънт.com'],
            ['3+1', '3+1'],
            ['www.xn--bckermller-q5a70a.de', 'www.bäckermüller.de'],
            ['xn--cfa', 'ı'],
            ['xn--ekiszlk-d1a0dy4d', 'ekşisözlük'],
            ['xn--rdetforstrrefrdselssikkerhed-znc6bz8b', 'rådetforstørrefærdselssikkerhed'],
            ['xn--kakavalc-0kb76b.com', 'kaşkavalcı.com'],
            ['xn--uxan.gr', 'πι.gr'],
            ['xn--ksigowo-c5a1nq1a.pl', 'księgowość.pl'],
            ['xn--80aebfcdsb1blidpdoq4e1i.com', 'регистрациядоменов.com'],
            ['xn--eqr31enth05q.xn--55qx5d', '国际域名.公司'],
            ['xn--1caqmypyo.hu', 'áéíóöúü.hu'],
            ['xn--1caqmypyo29d8i.hu', 'áéíóöőúüű.hu'],
            ['xn--vk1bq81c.com', '대출.com'],
            ['xn--t-mfutbzh', 'tシャツ'],
            ['www.xn--clcul3aaa2lcuc4kf.com', 'www.குண்டுபாப்பா.com'],
            ['xn--3e0b707e', '한국'],
            ['xn--xu5bx2sncw5i.com', '파티하임.com'],
            ['xn--o39aa', '가가'],
            ['xn----5gc8bsteqom5gm.xn--5dbik1ed.xn--9dbalbu5cfl', 'מילון-ראשׁי.תיבות.וקיצורים'],
            ['xn--rjajzusknak-r7a3h5b', 'írjajézuskának'],
            ['xn--q3cq3aix1l2a', 'น้ําหอม'],
            ['xn--q3ca5bk4b5k', 'สํานวน'],
            ['xn--chambres-dhtes-bpb.com', 'chambres-dhôtes.com'],
            ['xn--72cba0e8bxb3cu4kb6d6b.com', 'น้ําใสใจจริง.com'],
            ['xn--bren-mgen-fsse-5hb70axd.de', 'bären-mögen-füsse.de'],
            ['xn--da-hia.de', 'daß.de'],
        ];
    }
}
