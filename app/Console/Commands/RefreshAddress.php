<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Node;
use App\Models\NodeInfo;
use App\Models\User;

use App\Services\ChecksumValidator;

class RefreshAddress extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refresh:address';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh Address';

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
        // Runs Every 5 Mins = 300 Seconds
        $nodes = Node::whereNotNull('node_address')
                        ->where('refreshed', 0)
                        ->orderBy('created_at', 'asc')
                        ->offset(0)
                        ->limit(50)
                        ->get();
        if ($nodes) {
            foreach ($nodes as $node) {
                $address = strtolower($node->node_address);
                // $newAddress = (new ChecksumValidator())->do($address);
                $node->node_address = $address;
                $node->refreshed = 1;
                $node->save();
            }
        }

        $nodeInfos = NodeInfo::whereNotNull('node_address')
                                ->where('refreshed', 0)
                                ->orderBy('created_at', 'asc')
                                ->offset(0)
                                ->limit(50)
                                ->get();

        if ($nodeInfos) {
            foreach ($nodeInfos as $nodeInfo) {
                $address = strtolower($nodeInfo->node_address);
                // $newAddress = (new ChecksumValidator())->do($address);
                $nodeInfo->node_address = $address;
                $nodeInfo->refreshed = 1;
                $nodeInfo->save();
            }
        }

        $users = User::whereNotNull('public_address_node')
                                ->where('refreshed', 0)
                                ->orderBy('created_at', 'asc')
                                ->offset(0)
                                ->limit(50)
                                ->get();
        if ($users) {
            foreach ($users as $user) {
                $address = strtolower($user->public_address_node);
                // $newAddress = (new ChecksumValidator())->do($address);
                $user->public_address_node = $address;
                $user->refreshed = 1;
                $user->save();
            }
        }

        return 0;
    }
}
