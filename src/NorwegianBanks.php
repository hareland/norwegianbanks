<?php

namespace Ariselseng\NorwegianBanks;

use Komakino\Modulus11\Modulus11;
use Desarrolla2\Cache\File as FileCache;

class NorwegianBanks
{
    private const XlsFileUrl = 'https://www.finansnorge.no/contentassets/cc4fabf26cea4569aa447aa9ae671efa/norwegian-iban-bic-table.xls';
    private const xlsFileTtl = 1440;

    private $xlsFilePath;
    private $banks;
    private $prefixToBankCode;

    public function __construct()
    {
        $this->xlsFilePath = sys_get_temp_dir() . '/.cache-norwegianbanks-norwegian-iban-bic-table.xls';
        $this->processData();
    }

    private function downloadData()
    {

        $xlsFileExists = file_exists($this->xlsFilePath);
        if ($xlsFileExists && filemtime($this->xlsFilePath) <= (time() - self::xlsFileTtl)) {
            $headers = [
                'If-Modified-Since' => gmdate('D, d M Y H:i:s T', filemtime($this->xlsFilePath)),
            ];
        } else if (!$xlsFileExists) {
            $headers = null;
            
        } else {
            return;
        }

        $response = (new \GuzzleHttp\Client())->get(self::XlsFileUrl, [
            'headers' => $headers,
        ]);

        if ($response->getStatusCode() === 200 && $response->getBody()->getSize() > 0) {
            file_put_contents($this->xlsFilePath, $response->getBody()->getContents(), LOCK_EX);
        }
    }

    private function processData()
    {
        $this->downloadData();
        $cacheDir = sys_get_temp_dir() . '/phpcache-norwegianbanks-' . filemtime($this->xlsFilePath);
        $hasCacheDir = file_exists($cacheDir);
        if (!$hasCacheDir) {
            $hasCacheDir = mkdir($cacheDir);
        }

        if ($hasCacheDir) {
            $cache = new FileCache($cacheDir);
            $banks = $cache->get('banks');
        }

        if (!$hasCacheDir || !isset($banks)) {
            $banks = [];
            $prefixToBankCode = [];
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader("Xls");
            $reader->setReadDataOnly(true);

            $spreadsheet = $reader->load($this->xlsFilePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray(null, false, false, false);

            for ($i = 0; $i < count($rows); $i++) {
                if (is_null($rows[$i][1])) {
                    continue;
                }
                if (!isset($banks[$rows[$i][1]])) {
                    $banks[$rows[$i][1]] = new NorwegianBank($rows[$i][1], $rows[$i][2], [$rows[$i][0]]);
                } else {
                    $banks[$rows[$i][1]]->addPrefix($rows[$i][0]);
                }
                $prefixToBankCode[$rows[$i][0]] = $rows[$i][1];
            }

            if ($hasCacheDir) {
                $cache->set('banks', $banks, self::xlsFileTtl);
                $cache->set('prefixToBankCode', $prefixToBankCode, self::xlsFileTtl);
            }

            $this->banks = $banks;
            $this->prefixToBankCode = $prefixToBankCode;
        } else {
            $this->banks = $banks;
            $this->prefixToBankCode = $cache->get('prefixToBankCode');
        }
    }
    public function getBankCodeByPrefix(string $prefix)
    {

        if (!isset($this->prefixToBankCode[$prefix])) {
            return null;
        }
        return $this->prefixToBankCode[$prefix];
    }

    public function getBankByAccountNumber(string $account)
    {
        $prefix = substr($account, 0, 4);
        $bankCode = $this->getBankCodeByPrefix($prefix);
        if (is_null($bankCode)) {
            return null;
        }
        return $this->banks[$this->getBankCodeByPrefix($prefix)];
    }

    public function getFormattedAccountNumber(string $unformattedAccount, string $delimiter = '.')
    {
        $onlyDigits = preg_replace('/[^0-9]/', '', $unformattedAccount);
        return substr($onlyDigits, 0, 4) . $delimiter . substr($onlyDigits, 4, 2) . $delimiter . substr($onlyDigits, 6);
    }

    public function validateAccountNumber(string $account)
    {
        $mod11 = Modulus11::validate($account);

        if (!$mod11) {
            return false;
        }

        return !is_null($this->getBankByAccountNumber(substr($account, 0, 4)));
    }
}

class NorwegianBanksStatic
{
    public static function __callStatic($method, $args)
    {
        $obj = new NorwegianBanks();
        return $obj->$method(...$args);
    }
}
