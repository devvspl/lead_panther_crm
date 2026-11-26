<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneAccountEmailAttachments extends Command
{
    protected $signature = 'account-emails:prune-attachments';
    protected $description = 'Remove private account-email attachments older than seven days';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subDays(7)->format('Y-m-d');
        foreach ($disk->directories('account-email-attachments') as $directory) {
            if (preg_match('~^account-email-attachments/(\d{4}-\d{2}-\d{2})$~', $directory, $matches) && $matches[1] < $cutoff) {
                $disk->deleteDirectory($directory);
            }
        }
        return self::SUCCESS;
    }
}
