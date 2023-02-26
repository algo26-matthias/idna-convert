<?php
namespace Algo26\IdnaConvert\Test\integration;

use Algo26\IdnaConvert\Exception\AlreadyPunycodeException;
use Algo26\IdnaConvert\Exception\InvalidCharacterException;
use Algo26\IdnaConvert\Exception\InvalidIdnVersionException;
use Algo26\IdnaConvert\Exception\Std3AsciiRulesViolationException;
use Algo26\IdnaConvert\ToIdn;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Algo26\IdnaConvert\ToIdn
 */
class ToIdnTest extends TestCase
{
    /**
     * @dataProvider providerUtf8
     *
     * @throws AlreadyPunycodeException
     * @throws InvalidCharacterException
     * @throws InvalidIdnVersionException
     */
    public function testEncodeUtf8($decoded, $expectEncoded): void
    {
        $idnaConvert = new ToIdn();
        $encoded = $idnaConvert->convert($decoded);

        $this->assertEquals(
            $expectEncoded,
            $encoded,
            sprintf(
                'Strings "%s" and "%s" do not match',
                $expectEncoded,
                $encoded
            )
        );
    }

    /**
     * @dataProvider providerUtf8Idna2003
     *
     * @throws AlreadyPunycodeException
     * @throws InvalidCharacterException
     * @throws InvalidIdnVersionException
     */
    public function testEncodeUtf8Idna2003($decoded, $expectEncoded): void
    {
        $idnaConvert = new ToIdn(2003);
        $encoded = $idnaConvert->convert($decoded);

        $this->assertEquals(
            $expectEncoded,
            $encoded,
            sprintf(
                'Strings "%s" and "%s" do not match',
                $expectEncoded,
                $encoded
            )
        );
    }

    /**
     * @dataProvider providerEmailAddress
     *
     * @throws InvalidIdnVersionException
     */
    public function testEncodeEmailAddress($decoded, $expectEncoded): void
    {
        $idnaConvert = new ToIdn(2008);
        $encoded = $idnaConvert->convertEmailAddress($decoded);

        $this->assertEquals(
            $expectEncoded,
            $encoded,
            sprintf(
                'Strings "%s" and "%s" do not match',
                $expectEncoded,
                $encoded
            )
        );
    }

    /**
     * @dataProvider providerUrl
     *
     * @throws InvalidIdnVersionException
     */
    public function testEncodeUrl($decoded, $expectEncoded): void
    {
        $idnaConvert = new ToIdn(2008);
        $encoded = $idnaConvert->convertUrl($decoded);

        $this->assertEquals(
            $expectEncoded,
            $encoded,
            sprintf(
                'Strings "%s" and "%s" do not match',
                $expectEncoded,
                $encoded
            )
        );
    }

    /**
     * @dataProvider providerAlreadyPunycode
     */
    public function testThrowsAlreadyPunycodeException($decoded, $idnVersion): void
    {
        self::expectException(AlreadyPunycodeException::class);

        $idnaConvert = new ToIdn($idnVersion);
        $idnaConvert->convert($decoded);
    }

    /**
     * @dataProvider providerInvalidCharacter
     */
    public function testThrowsInvalidCharacterException($sequence): void
    {
        self::expectException(InvalidCharacterException::class);

        $idnaConvert = new ToIdn(2008);
        $idnaConvert->convert($sequence);
    }

    /**
     * @dataProvider providerStd3AsciiRulesViolation
     */
    public function testThrowsStd3AsciiRulesException($sequence): void
    {
        self::expectException(Std3AsciiRulesViolationException::class);

        $idnaConvert = new ToIdn(2008, true);
        $idnaConvert->convert($sequence);
    }

    public function providerUtf8(): array
    {
        return [
            ['', ''],
            ['dass.example', 'dass.example'],
            ['müller', 'xn--mller-kva'],
            ['weißenbach', 'xn--weienbach-i1a'],
            ['يوم-جيد', 'xn----9mcj9fole'],
            ['יום-טוב', 'xn----2hckbod3a'],
            ['idndomainäaöoüuexample.example', 'xn--idndomainaouexample-owb39ane.example'],
            ['öko.example', 'xn--ko-eka.example'],
            ['æšŧüø.example', 'xn--6ca0bl71b4a.example'],
            ['ìåíèäæìúíò.example', 'xn--4cabegsede9b0e.example'],
            ['мениджмънт.example', 'xn--d1abegsede9b0e.example'],
            ['www.bäckermüller.example', 'www.xn--bckermller-q5a70a.example'],
            ['ı', 'xn--cfa'],
            ['ekşisözlük', 'xn--ekiszlk-d1a0dy4d'],
            ['rådetforstørrefærdselssikkerhed', 'xn--rdetforstrrefrdselssikkerhed-znc6bz8b'],
            ['kaşkavalcı.example', 'xn--kakavalc-0kb76b.example'],
            ['πι.example', 'xn--uxan.example'],
            ['księgowość.example', 'xn--ksigowo-c5a1nq1a.example'],
            ['регистрациядоменов.example', 'xn--80aebfcdsb1blidpdoq4e1i.example'],
            ['国际域名.公司', 'xn--eqr31enth05q.xn--55qx5d'],
            ['áéíóöúü.example', 'xn--1caqmypyo.example'],
            ['áéíóöőúüű.example', 'xn--1caqmypyo29d8i.example'],
            ['대출.example', 'xn--vk1bq81c.example'],
            ['Ｔシャツ', 'xn--t-mfutbzh'],
            ['www.குண்டுபாப்பா.example', 'www.xn--clcul3aaa2lcuc4kf.example'],
            ['한국', 'xn--3e0b707e'],
            ['파티하임.example', 'xn--xu5bx2sncw5i.example'],
            ['가가', 'xn--o39aa'],
            ['מילון-ראשׁי.תיבות.וקיצורים', 'xn----5gc8bsteqom5gm.xn--5dbik1ed.xn--9dbalbu5cfl'],
            ['írjajézuskának', 'xn--rjajzusknak-r7a3h5b'],
            ['น้ำหอม', 'xn--q3cq3aix1l2a'],
            ['สำนวน', 'xn--q3ca5bk4b5k'],
            ['chambres-dhôtes.example', 'xn--chambres-dhtes-bpb.example'],
            ['น้ำใสใจจริง.example', 'xn--72cba0e8bxb3cu4kb6d6b.example'],
            ['bären-mögen-füsse.example', 'xn--bren-mgen-fsse-5hb70axd.example'],
            ['daß.example', 'xn--da-hia.example'],
            ['dömäin.example', 'xn--dmin-moa0i.example'],
            ['äaaa.example', 'xn--aaa-pla.example'],
            ['aäaa.example', 'xn--aaa-qla.example'],
            ['aaäa.example', 'xn--aaa-rla.example'],
            ['aaaä.example', 'xn--aaa-sla.example'],
            ['déjà.vu.example', 'xn--dj-kia8a.vu.example'],
            ['efraín.example', 'xn--efran-2sa.example'],
            ['ñandú.example', 'xn--and-6ma2c.example'],
            ['Foo.âBcdéf.example', 'foo.xn--bcdf-9na9b.example'],
            ['موقع.وزارة-الاتصالات.مصر', 'xn--4gbrim.xn----ymcbaaajlc6dj7bxne2c.xn--wgbh1c'],
            ['fußball.example', 'xn--fuball-cta.example'],
            ['היפא18פאטאם', 'xn--18-uldcat6ad6bydd'],
            ['فرس18النهر', 'xn--18-dtd1bdi0h3ask'],
            ["\u{33c7}", 'xn--czk'],
            ["\u{37a}", 'xn--1va'],
            ['ídn', 'xn--dn-mja'],
            ['ëx.ídn', 'xn--x-ega.xn--dn-mja'],
            ['åþç', 'xn--5cae2e'],
            ['ăbĉ', 'xn--b-rhat'],
            ['ȧƀƈ', 'xn--lhaq98b'],
            ['ḁḃḉ', 'xn--2fges'],
            ['丿人尸', 'xn--xiqplj17a'],
            ['かがき', 'xn--u8jcd'],
            ['カガキ', 'xn--lckcd'],
            ['각', 'xn--p39a'],
            ['걩듆쀺', 'xn--o69aq2nl0j'],
            ['ꀊꀠꊸ', 'xn--6l7arby7j'],
            ['αβγ', 'xn--mxacd'],
            ['ἂἦὕ', 'xn--fng7dpg'],
            ['абв', 'xn--80acd'],
            ['աբգ', 'xn--y9acd'],
            ['აბგ', 'xn--lodcd'],
            ['∡↺⊂', 'xn--b7gxomk'],
            ['कखग', 'xn--11bcd'],
            ['কখগ', 'xn--p5bcd'],
            ['ਕਖਗ', 'xn--d9bcd'],
            ['કખગ', 'xn--0dccd'],
            ['କଖଗ', 'xn--ohccd'],
            ['கஙச', 'xn--clcid'],
            ['కఖగ', 'xn--zoccd'],
            ['ಕಖಗ', 'xn--nsccd'],
            ['കഖഗ', 'xn--bwccd'],
            ['කඛග', 'xn--3zccd'],
            ['กขฃ', 'xn--12ccd'],
            ['ກຂຄ', 'xn--p6ccg'],
            ['ཀཁག', 'xn--5cdcd'],
            ['ကခဂ', 'xn--nidcd'],
            ['កខគ', 'xn--i2ecd'],
            ['ᠠᠡᠢ', 'xn--26ecd'],
            ['ابة', 'xn--mgbcd'],
            ['אבג', 'xn--4dbcd'],
            ['ܐܑܒ', 'xn--9mbcd'],
            ['abcカガキ', 'xn--abc-mj4bfg'],
            ['åþçカガキ', 'xn--5cae2e328wfag'],
            ['¹1', '11'],
            ['Ⅵvi', 'vivi'],
            ['3002-test。ídn', '3002-test.xn--dn-mja'],
            ['ff0e-test．ídn', 'ff0e-test.xn--dn-mja'],
            ['ff61-test｡ídn', 'ff61-test.xn--dn-mja'],
        ];
    }

    public function providerUtf8Idna2003(): array
    {
        return [
            ['daß.example', 'dass.example'],
            ['dass.example', 'dass.example'],
            ['Müller', 'xn--mller-kva'],
            ['weißenbach', 'weissenbach'],
            ['☃.example', 'xn--n3h.example'],
            ['fußball.example', 'fussball.example'],
            ["\u{33c7}", 'co.'],
            ["\u{37a}", 'xn-- -gmb'],
        ];
    }

    public function providerEmailAddress(): array
    {
        return [
            ['some.user@мениджмънт.example', 'some.user@xn--d1abegsede9b0e.example'],
            ['some.user@πι.example', 'some.user@xn--uxan.example'],
            ['söme.üser@daß.example', 'söme.üser@xn--da-hia.example'],
            ['some.user@foo.âbcdéf.example', 'some.user@foo.xn--bcdf-9na9b.example'],
        ];
    }

    public function providerUrl(): array
    {
        return [
            [
                'https://user:password@мениджмънт.example/home/international/test.html',
                'https://user:password@xn--d1abegsede9b0e.example/home/international/test.html'
            ],
            [
                'https://üser:päßword@πι.example/gnörz/lörz/',
                'https://üser:päßword@xn--uxan.example/gnörz/lörz/'
            ],
            [
                'https://user:password@daß.example/',
                'https://user:password@xn--da-hia.example/'
            ],
            [
                'https://user:password@foo.âbcdéf.example',
                'https://user:password@foo.xn--bcdf-9na9b.example'
            ],
            [
                'http://ñandú.example',
                'http://xn--and-6ma2c.example'
            ],
            [
                'file:///some/path/sömewhere/',
                'file:///some/path/sömewhere/'
            ],
        ];
    }

    public function providerAlreadyPunycode(): array
    {
        return [
            ['xn--ïdn', 2003],
            ['ⅹn--ädn', 2008],
            ['xN--ïdn', 2003],
            ['Xn--ïdn', 2003],
            ['XN--ïdn', 2003],
        ];
    }

    public function providerInvalidCharacter(): array
    {
        return [
            ['3+1'],
            ['abc+def'],
            ['do you copy?'],
            ['yes, minister!'],
        ];
    }

    public function providerStd3AsciiRulesViolation(): array
    {
        return [
            ['-hyphenated'],
            ['-hyphenated-'],
            ['hyphenated-'],
            ['negative-test-for-now'],
        ];
    }
}
