<?php

namespace App\Console\Commands;

use App\Support\License;
use Illuminate\Console\Command;

/** Daily background license verification against the Convoro store. */
class LicenseCheck extends Command
{
    protected $signature = 'license:check';

    protected $description = 'Verify the ConvoroCP license against the store';

    public function handle(): int
    {
        $r = License::check();
        $this->line(($r['ok'] ? '<fg=green>✓</>' : '<fg=yellow>•</>').' '.$r['message']);

        return self::SUCCESS;
    }
}
