<?php

namespace Ariselseng\NorwegianBanks\Tests;

use Ariselseng\NorwegianBanks\NorwegianBanks;
use Ariselseng\NorwegianBanks\NorwegianBanksStatic;
use PHPUnit\Framework\TestCase;

class NorwegianBanksTest extends TestCase
{
    private $norwegianBanks;
    protected $notRealAccountNumber = '1234.56.78903';
    protected $notRealAccountNumberWithSpaces = '1234 56 78903';
    protected $notRealAccountNumberUnformatted = '12345678903';

    protected $accounts = [
        [
            'bankCode' => 'DNBANOKK',
            'number' => '1594 22 87248'
        ],
        [
            'bankCode' => 'NDEANOKK',
            'number' => '61050659274'
        ],
        [
            'bankCode' => 'SPSONO22',
            'number' => '3000.27.79419'
        ],
    ];


    public function setUp()
    {
        $this->norwegianBanks = new NorwegianBanks();
    }

    public function testGetFormattedAccountNumber()
    {
        $this->assertEquals($this->notRealAccountNumber, $this->norwegianBanks->getFormattedAccountNumber($this->notRealAccountNumberUnformatted));
        $this->assertEquals($this->notRealAccountNumberWithSpaces, $this->norwegianBanks->getFormattedAccountNumber($this->notRealAccountNumberUnformatted, ' '));
    }

    public function testGetBankCodeByPrefix()
    {
        foreach ($this->accounts as $account) {
            $this->assertEquals($account['bankCode'], $this->norwegianBanks->getBankCodeByPrefix(substr($account['number'], 0, 4)));
        }
        $this->assertEquals(null, $this->norwegianBanks->getBankCodeByPrefix('0000'));
    }

    public function testGetBankByAccountNumber()
    {

        foreach ($this->accounts as $account) {
            $this->assertAttributeEquals($account['bankCode'], 'bankCode', $this->norwegianBanks->getBankByAccountNumber($account['number']));
            $this->assertAttributeEquals($account['bankCode'], 'bankCode', NorwegianBanksStatic::getBankByAccountNumber($account['number']));
        }

        $this->assertEquals(null, $this->norwegianBanks->getBankByAccountNumber($this->notRealAccountNumber));
    }

    public function testValidate()
    {

        foreach ($this->accounts as $account) {
            $this->assertTrue($this->norwegianBanks->validateAccountNumber($account['number']));
        }

        $this->assertFalse($this->norwegianBanks->validateAccountNumber($this->notRealAccountNumber));

    }

    public function testGetAllPrefixes() {
        $prefixes = $this->norwegianBanks->getAllPrefixes();
        $this->assertIsArray($prefixes);
        $this->assertNotContains('Bank identifier', $prefixes);
        $this->assertContains('1594', $prefixes);
    }
}
