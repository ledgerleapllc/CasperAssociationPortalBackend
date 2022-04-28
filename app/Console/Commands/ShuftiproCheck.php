<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ShuftiproTemp;
use App\Services\ShuftiproCheck as ServicesShuftiproCheck;
use Illuminate\Support\Facades\Log;

class ShuftiproCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shuftipro:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Shuftipro Response';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Runs Every 5 Mins ( 300 Seconds )
        // Process 20 per Run
        $shuftiproCheck = new ServicesShuftiproCheck();
        $records = ShuftiproTemp::where('status', 'booked')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($records as $record) {
            try {
                $shuftiproCheck->handle($record);
            } catch (\Exception $th) {
                Log::error($th->getMessage());
            }
        }
    }
}
