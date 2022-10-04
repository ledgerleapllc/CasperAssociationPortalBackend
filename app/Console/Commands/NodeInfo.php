<?php

namespace App\Console\Commands;

use App\Models\Metric;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\NodeHelper;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

class NodeInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'node-info';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'get node info';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $nodeHelper = new NodeHelper();
        $nodeHelper->getValidatorStanding();
    }
}