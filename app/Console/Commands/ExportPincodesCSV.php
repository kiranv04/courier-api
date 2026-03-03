<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportPincodesCSV extends Command
{
    protected $signature   = 'pincodes:export-csv';
    protected $description = 'Export serviceable_pincodes table to database/data/pincodes.csv';

    public function handle(): int
    {
        $outputPath = database_path('data/pincodes.csv');

        if (! is_dir(database_path('data'))) {
            mkdir(database_path('data'), 0755, true);
        }

        $this->info('Exporting pincodes to CSV...');

        $columns = [
            'pincode', 'state_id', 'area_code', 'area_name', 'region',
            'is_edl', 'ecom_zone',
            'apex_service', 'apex_tat', 'apex_zone',
            'surface_service', 'surface_tat', 'surface_zone',
            'dp_service', 'dp_tat', 'dp_zone',
        ];

        $file = fopen($outputPath, 'w');

        if ($file === false) {
            $this->error("Could not open {$outputPath} for writing.");
            return self::FAILURE;
        }

        // Write header row
        fputcsv($file, $columns);

        $total = 0;

        // Stream in chunks of 1000 — no memory issues
        DB::table('serviceable_pincodes')
            ->select($columns)
            ->orderBy('id')
            ->chunk(1000, function ($rows) use ($file, &$total) {
                foreach ($rows as $row) {
                    fputcsv($file, (array) $row);
                    $total++;
                }
                $this->line("  → {$total} rows written...");
            });

        fclose($file);

        $size = round(filesize($outputPath) / 1024 / 1024, 2);
        $this->info("✓ Export complete. {$total} rows written to database/data/pincodes.csv ({$size} MB).");
        $this->info('Commit this file to your repository for use on the server.');

        return self::SUCCESS;
    }
}