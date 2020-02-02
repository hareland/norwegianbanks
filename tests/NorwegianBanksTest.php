<?php

namespace Ariselseng\NorwegianBanks\Tests;

use Ariselseng\NorwegianBanks\NorwegianBanks;
use Ariselseng\NorwegianBanks\NorwegianBanksStatic;

class NorwegianBanksTest extends \PHPUnit_Framework_TestCase
{
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
    protected $notRealAccountNumber = '1234.56.78903';
    protected $notRealAccountNumberWithSpaces = '1234 56 78903';
    protected $notRealAccountNumberUnformatted = '12345678903';

    private $norwegianBanks;

    public function __construct()
    {
        $this->norwegianBanks = new NorwegianBanks();
    }

    public function testGetFormattedAccountNumber()
    {
        $this->assertEquals($this->notRealAccountNumber, $this->norwegianBanks->getFormattedAccountNumber($this->notRealAccountNumberUnformatted));
        $this->assertEquals($this->notRealAccountNumber, NorwegianBanksStatic::getFormattedAccountNumber($this->notRealAccountNumberUnformatted));
        $this->assertEquals($this->notRealAccountNumberWithSpaces, $this->norwegianBanks->getFormattedAccountNumber($this->notRealAccountNumberUnformatted, ' '));
        $this->assertEquals($this->notRealAccountNumberWithSpaces, NorwegianBanksStatic::getFormattedAccountNumber($this->notRealAccountNumberUnformatted, ' '));
    }

    public function testGetBankCodeByPrefix()
    {
        foreach ($this->accounts as $account) {
            $this->assertEquals($account['bankCode'], $this->norwegianBanks->getBankCodeByPrefix(substr($account['number'], 0, 4)));
            $this->assertEquals($account['bankCode'], NorwegianBanksStatic::getBankCodeByPrefix(substr($account['number'], 0, 4)));
        }
        $this->assertEquals(null, $this->norwegianBanks->getBankCodeByPrefix('0000'));
        $this->assertEquals(null, NorwegianBanksStatic::getBankCodeByPrefix('0000'));
    }

    public function testGetBankByAccountNumber()
    {

        foreach ($this->accounts as $account) {
            $this->assertAttributeEquals($account['bankCode'], 'bankCode', $this->norwegianBanks->getBankByAccountNumber($account['number']));
            $this->assertAttributeEquals($account['bankCode'], 'bankCode', NorwegianBanksStatic::getBankByAccountNumber($account['number']));
        }

        $this->assertEquals(null, $this->norwegianBanks->getBankByAccountNumber($this->notRealAccountNumber));
        $this->assertEquals(null, NorwegianBanksStatic::getBankByAccountNumber($this->notRealAccountNumber));
    }

    public function testValidate()
    {

        foreach ($this->accounts as $account) {
            $this->assertTrue($this->norwegianBanks->validateAccountNumber($account['number']));
            $this->assertTrue(NorwegianBanksStatic::validateAccountNumber($account['number']));

        }

        $this->assertFalse($this->norwegianBanks->validateAccountNumber($this->notRealAccountNumber));
        $this->assertFalse(NorwegianBanksStatic::validateAccountNumber($this->notRealAccountNumber));

    }
}
