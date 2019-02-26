<?php
 /*
 * @author  Matthias Sommerfeld <mso@phlylabs.de>
 * @copyright 2004-2019 algo26 Beratungs UG, Berlin, https://www.algo26.de
 */

namespace Algo26\IdnaConvert;

interface PunycodeInterface 
{
   
    public function __construct(NamePrepDataInterface $NamePrepData, UnicodeTranscoderInterface $UCTC);

    public function getPunycodePrefix();

    public function decode($encoded);

    public function encode($decoded);

}
