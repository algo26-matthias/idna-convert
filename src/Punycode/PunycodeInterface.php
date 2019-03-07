<?php
namespace Algo26\IdnaConvert\Punycode;

interface PunycodeInterface 
{
    public function __construct(string $idnVersion);

    public function getPunycodePrefix();
}
