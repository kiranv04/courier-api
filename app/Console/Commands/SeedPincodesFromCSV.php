<?php

namespace App\Console\Commands;

use App\Models\ServiceablePincode;
use Illuminate\Console\Command;

class SeedPincodesFromCSV extends Command
{
    protected $signature   = 'pincodes:seed-from-csv';
    protected $description = 'Seed serviceable_pincodes from database/data/pincodes.csv (for production/staging)';

    public function handle(): int
    {
        $csvPath = database_path('data/pincodes.csv');

        if (! file_exists($csvPath)) {
            $this->error("File not found: {$csvPath}");
            $this->line('Run pincodes:export-csv locally first and commit the CSV to your repository.');
            return self::FAILURE;
        }

        $this->info('Seeding pincodes from CSV...');

        $file = fopen($csvPath, 'r');

        if ($file === false) {
            $this->error("Could not open {$csvPath} for reading.");
            return self::FAILURE;
        }

        // Read and validate header row
        $headers = fgetcsv($file);

        $expectedHeaders = [
            'pincode', 'state_id', 'area_code', 'area_name', 'region',
            'is_edl', 'ecom_zone',
            'apex_service', 'apex_tat', 'apex_zone',
            'surface_service', 'surface_tat', 'surface_zone',
            'dp_service', 'dp_tat', 'dp_zone',
        ];

        if ($headers !== $expectedHeaders) {
            $this->error('CSV headers do not match expected format. Re-export using pincodes:export-csv.');
            fclose($file);
            return self::FAILURE;
        }

        $chunk = [];
        $total = 0;
        $now   = now()->toDateTimeString();

        while (($row = fgetcsv($file)) !== false) {
            $record = array_combine($headers, $row);

            // Cast types correctly
            $record['state_id']    = $record['state_id'] !== '' ? (int) $record['state_id'] : null;
            $record['is_edl']      = $record['is_edl'] === '1';
            $record['apex_tat']    = $record['apex_tat'] !== '' ? (int) $record['apex_tat'] : null;
            $record['surface_tat'] = $record['surface_tat'] !== '' ? (int) $record['surface_tat'] : null;
            $record['dp_tat']      = $record['dp_tat'] !== '' ? (int) $record['dp_tat'] : null;

            // Replace empty strings with null for nullable columns
            foreach (['area_code', 'area_name', 'region', 'ecom_zone',
                      'apex_service', 'apex_zone',
                      'surface_service', 'surface_zone',
                      'dp_service', 'dp_zone'] as $col) {
                $record[$col] = $record[$col] !== '' ? $record[$col] : null;
            }

            $record['updated_at'] = $now;
            $record['created_at'] = $now;

            $chunk[] = $record;
            $total++;

            // Upsert in batches of 500
            if (count($chunk) === 500) {
                ServiceablePincode::upsert(
                    $chunk,
                    ['pincode'],
                    array_diff($expectedHeaders, ['pincode'])
                );
                $chunk = [];
                $this->line("  → {$total} rows processed...");
            }
        }

        // Flush remaining rows
        if (! empty($chunk)) {
            ServiceablePincode::upsert(
                $chunk,
                ['pincode'],
                array_diff($expectedHeaders, ['pincode'])
            );
        }

        fclose($file);

        $this->info("✓ Seed complete. {$total} pincodes loaded.");

        return self::SUCCESS;
    }
}