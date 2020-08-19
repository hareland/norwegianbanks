<?php

namespace Ariselseng\NorwegianBanks;

use Desarrolla2\Cache\File as FileCache;

class NorwegianBanks
{
    private const XlsFileUrl = 'https://www.bits.no/document/iban/';
    private const xlsFileTtl = 86400;

    private $xlsFilePath;
    private $banks;
    private $prefixToBankCode;

    public function __construct()
    {
        $this->xlsFilePath = sys_get_temp_dir() . '/.cache-norwegianbanks-norwegian-iban-bic-table.xlsx';
        $this->processData();
    }

    private function downloadData()
    {

        $xlsFileExists = file_exists($this->xlsFilePath);
        $xlsFileMTime = $xlsFileExists ? filemtime($this->xlsFilePath) : 0;
        $xlsTooOld = $xlsFileMTime <= (time() - self::xlsFileTtl);

        if ($xlsFileExists && $xlsTooOld) {
            $headers = [
                'If-Modified-Since' => gmdate('D, d M Y H:i:s T', $xlsFileMTime),
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
        } else if ($response->getStatusCode() === 304) {
            touch($this->xlsFilePath);
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
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader("Xlsx");
            $reader->setReadDataOnly(true);

            $spreadsheet = $reader->load($this->xlsFilePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray(null, false, false, false);
            $startKey = 1;

            // handle if header row is missing
            if (preg_match("/^\d{4}$/", $rows[0][0])) {
                $startKey = 0;
            }

            for ($i = $startKey; $i < count($rows); $i++) {
                if (is_null($rows[$i][1])) {
                    $rows[$i][1] = "n/a";
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

    /**
     * @param string $prefix
     * @return string|null
     */
    public function getBankCodeByPrefix(string $prefix)
    {

        if (!isset($this->prefixToBankCode[$prefix])) {
            return null;
        }
        return $this->prefixToBankCode[$prefix];
    }

    /**
     * @param string $account
     * @return NorwegianBank|null
     */
    public function getBankByAccountNumber(string $account)
    {
        $prefix = substr($account, 0, 4);
        $bankCode = $this->getBankCodeByPrefix($prefix);
        if (is_null($bankCode)) {
            return null;
        }
        return $this->banks[$this->getBankCodeByPrefix($prefix)];
    }

    /**
     * @param string $unformattedAccount
     * @param string $delimiter
     * @return string
     */
    public function getFormattedAccountNumber(string $unformattedAccount, string $delimiter = '.')
    {
        $onlyDigits = preg_replace('/[^0-9]/', '', $unformattedAccount);
        return substr($onlyDigits, 0, 4) . $delimiter . substr($onlyDigits, 4, 2) . $delimiter . substr($onlyDigits, 6);
    }

    /**
     * @param string $account
     * @param bool $validateBankPrefix
     * @return bool
     */
    public function validateAccountNumber(string $account, bool $validateBankPrefix = true)
    {

        $weights = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $onlyDigits = preg_replace('/[^0-9]/', '', $account);

        if (strlen($onlyDigits) !== 11) {
            return false;
        }

        $checkDigit = (int)substr($onlyDigits, -1, 1);

        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += (int)substr($onlyDigits, $i, 1) * $weights[$i];
        }

        $remainder = $sum % 11;

        if ($remainder === 0) {
            $checkDigitFromRemainder = $remainder;
        } else {
            $checkDigitFromRemainder = 11 - $remainder;
        }

        if ($checkDigit !== $checkDigitFromRemainder) {
            return false;
        }

        if (!$validateBankPrefix) {
            return true;
        }

        return !is_null($this->getBankByAccountNumber(substr($onlyDigits, 0, 4)));
    }

    /**
     * @return string[]
     */
    public function getAllPrefixes()
    {
        return array_map('strval', array_keys($this->prefixToBankCode));
    }

    /**
     * @return NorwegianBank[]|null
     */
    public function getAllBanks()
    {
        return $this->banks;
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
