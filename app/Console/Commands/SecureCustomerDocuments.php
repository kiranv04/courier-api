<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SecureCustomerDocuments extends Command
{
    protected $signature = 'customers:secure-documents';

    protected $description = 'Move customer KYC documents (GST/PAN/Aadhaar) from the public disk to the private disk';

    public function handle(): int
    {
        $public  = Storage::disk('public');
        $private = Storage::disk('local');
        $moved   = 0;

        foreach (['customers/gst', 'customers/pan', 'customers/aadhar'] as $dir) {
            foreach ($public->files($dir) as $file) {
                $private->put($file, $public->get($file));
                $public->delete($file);
                $moved++;
            }
        }

        $this->info("Moved {$moved} document(s) to private storage.");

        return self::SUCCESS;
    }
}
