<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class GoogleSheetsService
{
    private $client;
    private $service;

    public function __construct()
    {
        $credentialsPath = storage_path('app/google.json');

        // Note: Make sure `composer require google/apiclient` is installed.
        if (class_exists(\Google\Client::class) && file_exists($credentialsPath)) {
            $this->client = new \Google\Client();
            $this->client->setApplicationName('Webflow Sync');
            $this->client->addScope(\Google\Service\Sheets::SPREADSHEETS_READONLY);
            $this->client->setAuthConfig($credentialsPath);
            $this->client->setAccessType('offline');

            $this->service = new \Google\Service\Sheets($this->client);
        } else {
            Log::warning("Google Client not initialized. Check credentials path or install google/apiclient.");
        }
    }

    /**
     * Fetch all data from a specific sheet and return as an associative array
     * using the first row as headers.
     */
    public function getSheetData($spreadsheetId, $sheetName)
    {
        if (!$this->service) {
            throw new \Exception("Google Sheets Service is not initialized.");
        }

        $range = "{$sheetName}!A1:ZZ10000";
        $response = $this->service->spreadsheets_values->get($spreadsheetId, $range);
        $values = $response->getValues();

        if (empty($values)) {
            return [];
        }

        // First row is headers
        $headers = array_shift($values);
        $headers = array_map('trim', $headers);

        $data = [];
        foreach ($values as $row) {
            $rowData = [];
            foreach ($headers as $index => $header) {
                $rowData[$header] = $row[$index] ?? '';
            }
            // Only add rows that have some data (skip empty rows)
            if (count(array_filter($rowData)) > 0) {
                $data[] = $rowData;
            }
        }

        return $data;
    }
}
