<?php
namespace Algo26\IdnaConvert\Test;

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
                'Strings "%s" and "%s" do not match',
                $expectEncoded, $encoded
            )
        );
    }

    /**
     * @dataProvider providerUtf8Idna2003
     */
    public function testEncodeUtf8Idna2003($decoded, $expectEncoded)
    {
        $idnaConv = new IdnaConvert();
        $idnaConv->setIdnVersion(2003);
        $encoded = $idnaConv->encode($decoded);

        $this->assertEquals(
            $expectEncoded,
            $encoded,
            sprintf(
                'Strings "%s" and "%s" do not match',
                $expectEncoded, $encoded
            )
        );
    }

    public function providerUtf8()
    {
        return [
            ['', ''],
            ['dass.de', 'dass.de'],
            ['müller', 'xn--mller-kva'],
            ['weißenbach', 'xn--weienbach-i1a'],
            ['يوم-جيد', 'xn----9mcj9fole'],
            ['יום-טוב', 'xn----2hckbod3a'],
            ['idndomainäaöoüuexample.de', 'xn--idndomainaouexample-owb39ane.de'],
            ['öko.de', 'xn--ko-eka.de'],
            ['æšŧüø.no', 'xn--6ca0bl71b4a.no'],
            ['ìåíèäæìúíò.com', 'xn--4cabegsede9b0e.com'],
            ['мениджмънт.com', 'xn--d1abegsede9b0e.com'],
            ['3+1', '3+1'],
            ['www.bäckermüller.de', 'www.xn--bckermller-q5a70a.de'],
            ['ı', 'xn--cfa'],
            ['ekşisözlük', 'xn--ekiszlk-d1a0dy4d'],
            ['rådetforstørrefærdselssikkerhed', 'xn--rdetforstrrefrdselssikkerhed-znc6bz8b'],
            ['kaşkavalcı.com', 'xn--kakavalc-0kb76b.com'],
            ['πι.gr', 'xn--uxan.gr'],
            ['księgowość.pl', 'xn--ksigowo-c5a1nq1a.pl'],
            ['регистрациядоменов.com', 'xn--80aebfcdsb1blidpdoq4e1i.com'],
            ['国际域名.公司', 'xn--eqr31enth05q.xn--55qx5d'],
            ['áéíóöúü.hu', 'xn--1caqmypyo.hu'],
            ['áéíóöőúüű.hu', 'xn--1caqmypyo29d8i.hu'],
            ['대출.com', 'xn--vk1bq81c.com'],
            ['Ｔシャツ', 'xn--t-mfutbzh'],
            ['www.குண்டுபாப்பா.com', 'www.xn--clcul3aaa2lcuc4kf.com'],
            ['한국', 'xn--3e0b707e'],
            ['파티하임.com', 'xn--xu5bx2sncw5i.com'],
            ['가가', 'xn--o39aa'],
            ['מילון-ראשׁי.תיבות.וקיצורים', 'xn----5gc8bsteqom5gm.xn--5dbik1ed.xn--9dbalbu5cfl'],
            ['írjajézuskának', 'xn--rjajzusknak-r7a3h5b'],
            ['น้ำหอม', 'xn--q3cq3aix1l2a'],
            ['สำนวน', 'xn--q3ca5bk4b5k'],
            ['chambres-dhôtes.com', 'xn--chambres-dhtes-bpb.com'],
            ['น้ำใสใจจริง.com', 'xn--72cba0e8bxb3cu4kb6d6b.com'],
            ['bären-mögen-füsse.de', 'xn--bren-mgen-fsse-5hb70axd.de'],
            ['daß.de', 'xn--da-hia.de'],
        ];
    }

    public function providerUtf8Idna2003()
    {
        return [
            ['daß.de', 'dass.de'],
            ['dass.de', 'dass.de'],
            ['müller', 'xn--mller-kva'],
            ['weißenbach', 'weissenbach'],
        ];
    }
}