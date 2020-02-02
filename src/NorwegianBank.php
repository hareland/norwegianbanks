<?php

namespace Ariselseng\NorwegianBanks;


class NorwegianBank
{
    public $bankCode;
    public $bankName;
    public $prefixes;

    function __construct(string $bankCode, string $bankName, array $prefixes = [])
    {
        $this->bankCode = $bankCode;
        $this->bankName = $bankName;
        $this->prefixes = $prefixes;
    }
    public function addPrefix(string $prefix) {
        $this->prefixes[] = $prefix;
    }
}
