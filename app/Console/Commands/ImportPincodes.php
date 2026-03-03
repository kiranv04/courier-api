<?php

namespace App\Console\Commands;

use App\Models\ServiceablePincode;
use Illuminate\Console\Command;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Reader\XLSX\Options;

class ImportPincodes extends Command
{
    protected $signature   = 'pincodes:import';
    protected $description = 'Import serviceable pincodes from Excel files in database/data/';

    private const STATE_MAP = [
        'ANDAMAN & NICOBAR ISLANDS'                => 1,
        'ANDAMAN AND NICOBAR ISLANDS'              => 1,
        'ANDHRA PRADESH'                           => 2,
        'ARUNACHAL PRADESH'                        => 3,
        'ASSAM'                                    => 4,
        'BIHAR'                                    => 5,
        'CHANDIGARH'                               => 6,
        'CHHATTISGARH'                             => 7,
        'CHHATISGARH'                              => 7,
        'DADRA AND NAGAR HAVELI'                   => 8,
        'DADRA AND NAGAR HAVELI AND DAMAN & DIU'   => 8,
        'DADRA AND NAGAR HAVELI AND DAMAN AND DIU' => 8,
        'DELHI'                                    => 9,
        'GOA'                                      => 10,
        'GUJARAT'                                  => 11,
        'HARYANA'                                  => 12,
        'HIMACHAL PRADESH'                         => 13,
        'JAMMU AND KASHMIR'                        => 14,
        'JHARKHAND'                                => 15,
        'KARNATAKA'                                => 16,
        'KERALA'                                   => 17,
        'LADAKH'                                   => 18,
        'LEH LADHAK'                               => 18,
        'LEH LADAKH'                               => 18,
        'LAKSHADWEEP'                              => 19,
        'MADHYA PRADESH'                           => 20,
        'MAHARASHTRA'                              => 21,
        'MANIPUR'                                  => 22,
        'MEGHALAYA'                                => 23,
        'MIZORAM'                                  => 24,
        'NAGALAND'                                 => 25,
        'ODISHA'                                   => 26,
        'ORISSA'                                   => 26,
        'PONDICHERRY'                              => 27,
        'PUDUCHERRY'                               => 27,
        'PUNJAB'                                   => 28,
        'RAJASTHAN'                                => 29,
        'SIKKIM'                                   => 30,
        'TAMIL NADU'                               => 31,
        'TAMILNADU'                                => 31,
        'TELANGANA'                                => 32,
        'TRIPURA'                                  => 33,
        'UTTAR PRADESH'                            => 34,
        'UTTARAKHAND'                              => 35,
        'UTTARANCHAL'                              => 35,
        'WEST BENGAL'                              => 36,
    ];

    private const SERVICE_MAP = [
        'I/B and O/B' => 'both',
        'I/B'         => 'inbound',
        'NONE'        => 'none',
    ];

    private const FILES = [
        [
            'filename'    => 'APEX.xlsx',
            'prefix'      => 'apex',
            'service_col' => 'A00',
            'zone_col'    => 'APEXZONE',
        ],
        [
            'filename'    => 'SURFACE.xlsx',
            'prefix'      => 'surface',
            'service_col' => 'E00',
            'zone_col'    => 'SFCZONE',
        ],
        [
            'filename'    => 'DP_PIN_CODE_.xlsx',
            'prefix'      => 'dp',
            'service_col' => 'D00',
            'zone_col'    => 'DPZONE',
        ],
    ];

    public function handle(): int
    {
        $dataPath = database_path('data');

        foreach (self::FILES as $file) {
            if (! file_exists($dataPath . '/' . $file['filename'])) {
                $this->error("Missing file: {$dataPath}/{$file['filename']}");
                return self::FAILURE;
            }
        }

        $this->info('Starting pincode import...');

        $unmappedStates = [];
        $records        = [];

        foreach (self::FILES as $file) {
            $path   = $dataPath . '/' . $file['filename'];
            $prefix = $file['prefix'];

            $this->info("Reading {$file['filename']}...");

            $options = new Options();
            $options->SHOULD_FORMAT_DATES = false;

            $reader = new Reader($options);
            $reader->open($path);

            $headers  = null;
            $colIndex = [];
            $rowCount = 0;

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $rowObj) {
                    $cells = $rowObj->getCells();
                    $row   = array_map(fn($cell) => $cell->getValue(), $cells);

                    // First row = headers
                    if ($headers === null) {
                        $headers  = array_map('trim', $row);
                        $colIndex = array_flip($headers);
                        continue;
                    }

                    $get = fn(string $col) => isset($colIndex[$col])
                        ? (trim((string) ($row[$colIndex[$col]] ?? '')) ?: null)
                        : null;

                    $pincode = $get('CPINCODE');

                    if ($pincode === null) {
                        continue;
                    }

                    $stateRaw = strtoupper((string) $get('CSTATE'));
                    $stateId  = self::STATE_MAP[$stateRaw] ?? null;

                    if ($stateId === null && $stateRaw !== '') {
                        $unmappedStates[$stateRaw] = true;
                    }

                    $rawService   = (string) ($get($file['service_col']) ?? 'NONE');
                    $serviceValue = self::SERVICE_MAP[$rawService] ?? 'none';

                    $tat   = $get('TAT');
                    $tat   = $tat !== null ? (int) $tat : null;
                    $isEdl = strtoupper((string) $get('EDL')) === 'Y';

                    if (! isset($records[$pincode])) {
                        $records[$pincode] = [
                            'pincode'         => $pincode,
                            'state_id'        => $stateId,
                            'area_code'       => $get('CAREA'),
                            'area_name'       => $get('CSCRCDDESC'),
                            'region'          => $get('CREGION'),
                            'is_edl'          => $isEdl,
                            'ecom_zone'       => $get('ECOMZONE'),
                            'apex_service'    => null,
                            'apex_tat'        => null,
                            'apex_zone'       => null,
                            'surface_service' => null,
                            'surface_tat'     => null,
                            'surface_zone'    => null,
                            'dp_service'      => null,
                            'dp_tat'          => null,
                            'dp_zone'         => null,
                        ];
                    }

                    $records[$pincode]["{$prefix}_service"] = $serviceValue;
                    $records[$pincode]["{$prefix}_tat"]     = $tat;
                    $records[$pincode]["{$prefix}_zone"]    = $get($file['zone_col']);

                    $rowCount++;

                    if ($rowCount % 1000 === 0) {
                        $this->line("  → {$rowCount} rows processed...");
                    }
                }
                break; // Only process the first sheet
            }

            $reader->close();
            $this->info("  ✓ Done. {$rowCount} rows processed.");
        }

        if (! empty($unmappedStates)) {
            $this->warn('Unmapped state names (stored with state_id = null):');
            foreach (array_keys($unmappedStates) as $name) {
                $this->warn("  → \"{$name}\"");
            }
        }

        $this->info('Writing ' . count($records) . ' records to database...');

        $chunks = array_chunk(array_values($records), 500);
        $bar    = $this->output->createProgressBar(count($chunks));
        $bar->start();

        foreach ($chunks as $chunk) {
            ServiceablePincode::upsert(
                $chunk,
                ['pincode'],
                [
                    'state_id', 'area_code', 'area_name', 'region', 'is_edl', 'ecom_zone',
                    'apex_service', 'apex_tat', 'apex_zone',
                    'surface_service', 'surface_tat', 'surface_zone',
                    'dp_service', 'dp_tat', 'dp_zone',
                    'updated_at',
                ]
            );
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('✓ Import complete. Total pincodes: ' . count($records));

        return self::SUCCESS;
    }
}