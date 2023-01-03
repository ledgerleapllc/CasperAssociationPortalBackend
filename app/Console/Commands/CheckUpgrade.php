<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Upgrade;
use App\Jobs\UpgradeReminder24;

use Carbon\Carbon;

class CheckUpgrade extends Command
{
    protected $signature = 'upgrade:check';
    protected $description = 'Check Upgrade';

    public function __construct() {
        parent::__construct();
    }

    public function handle() {
        $lastUpgrade = Upgrade::orderBy('id', 'desc')->limit(1)->first();
        if (
            isset($lastUpgrade->reminder_24_sent) &&
            !$lastUpgrade->reminder_24_sent &&
            $lastUpgrade->activation_datetime > Carbon::now('UTC') &&
            $lastUpgrade->activation_datetime <= Carbon::now('UTC')->addHours(24)
        ) {
            UpgradeReminder24::dispatch($lastUpgrade)->onQueue('default_long');
        }
        return 0;
    }
}
