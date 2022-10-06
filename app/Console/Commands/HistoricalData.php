<?php

namespace App\Console\Commands;

use App\Services\NodeHelper;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

class HistoricalData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'historical-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'get historical node data';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $nodeHelper = new NodeHelper();
    }
}