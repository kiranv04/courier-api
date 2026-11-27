<?php

namespace App\Console\Commands;

use App\Models\ServiceablePincode;
use App\Models\State;
use Illuminate\Console\Command;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Reader\XLSX\Options;

class ImportPincodes extends Command
{
    protected $signature   = 'pincodes:import';
    protected $description = 'Import serviceable pincodes from Excel files in database/data/';

    // Maps a spreadsheet spelling variant to the canonical name in the `states` table.
    // IDs are resolved at runtime against the DB (see resolveStateIds()) rather than
    // hardcoded here, so this stays correct regardless of seed/insert order.
    private const STATE_NAME_MAP = [
        'ANDAMAN & NICOBAR ISLANDS'                => 'Andaman and Nicobar Islands',
        'ANDAMAN AND NICOBAR ISLANDS'              => 'Andaman and Nicobar Islands',
        'ANDHRA PRADESH'                           => 'Andhra Pradesh',
        'ARUNACHAL PRADESH'                        => 'Arunachal Pradesh',
        'ASSAM'                                    => 'Assam',
        'BIHAR'                                    => 'Bihar',
        'CHANDIGARH'                               => 'Chandigarh',
        'CHHATTISGARH'                             => 'Chhattisgarh',
        'CHHATISGARH'                              => 'Chhattisgarh',
        'DADRA AND NAGAR HAVELI'                   => 'Dadra and Nagar Haveli and Daman and Diu',
        'DADRA AND NAGAR HAVELI AND DAMAN & DIU'   => 'Dadra and Nagar Haveli and Daman and Diu',
        'DADRA AND NAGAR HAVELI AND DAMAN AND DIU' => 'Dadra and Nagar Haveli and Daman and Diu',
        'DELHI'                                    => 'Delhi',
        'GOA'                                      => 'Goa',
        'GUJARAT'                                  => 'Gujarat',
        'HARYANA'                                  => 'Haryana',
        'HIMACHAL PRADESH'                         => 'Himachal Pradesh',
        'JAMMU AND KASHMIR'                        => 'Jammu and Kashmir',
        'JHARKHAND'                                => 'Jharkhand',
        'KARNATAKA'                                => 'Karnataka',
        'KERALA'                                   => 'Kerala',
        'LADAKH'                                   => 'Ladakh',
        'LEH LADHAK'                               => 'Ladakh',
        'LEH LADAKH'                               => 'Ladakh',
        'LAKSHADWEEP'                              => 'Lakshadweep',
        'MADHYA PRADESH'                           => 'Madhya Pradesh',
        'MAHARASHTRA'                              => 'Maharashtra',
        'MANIPUR'                                  => 'Manipur',
        'MEGHALAYA'                                => 'Meghalaya',
        'MIZORAM'                                  => 'Mizoram',
        'NAGALAND'                                 => 'Nagaland',
        'ODISHA'                                   => 'Odisha',
        'ORISSA'                                   => 'Odisha',
        'PONDICHERRY'                              => 'Puducherry',
        'PUDUCHERRY'                               => 'Puducherry',
        'PUNJAB'                                   => 'Punjab',
        'RAJASTHAN'                                => 'Rajasthan',
        'SIKKIM'                                   => 'Sikkim',
        'TAMIL NADU'                               => 'Tamil Nadu',
        'TAMILNADU'                                => 'Tamil Nadu',
        'TELANGANA'                                => 'Telangana',
        'TRIPURA'                                  => 'Tripura',
        'UTTAR PRADESH'                            => 'Uttar Pradesh',
        'UTTARAKHAND'                              => 'Uttarakhand',
        'UTTARANCHAL'                              => 'Uttarakhand',
        'WEST BENGAL'                              => 'West Bengal',
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

    /**
     * Resolve canonical state name => id from the `states` table, keyed case-insensitively
     * so lookups are resilient to minor casing differences.
     *
     * @return array<string, int>
     */
    private function resolveStateIds(): array
    {
        $ids = [];
        foreach (State::pluck('id', 'name') as $name => $id) {
            $ids[strtoupper($name)] = $id;
        }
        return $ids;
    }

    public function handle(): int
    {
        $dataPath = database_path('data');

        foreach (self::FILES as $file) {
            if (! file_exists($dataPath . '/' . $file['filename'])) {
                $this->error("Missing file: {$dataPath}/{$file['filename']}");
                return self::FAILURE;
            }
        }

        $stateIds = $this->resolveStateIds();

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

                    $stateRaw       = strtoupper((string) $get('CSTATE'));
                    $stateName      = self::STATE_NAME_MAP[$stateRaw] ?? null;
                    $stateId        = $stateName !== null ? ($stateIds[strtoupper($stateName)] ?? null) : null;

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
