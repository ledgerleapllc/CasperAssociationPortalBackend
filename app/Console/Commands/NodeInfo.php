<?php

namespace App\Console\Commands;

use App\Models\Node;
use App\Models\NodeInfo as ModelsNodeInfo;
use App\Models\User;
use App\Services\NodeHelper;
use Carbon\Carbon;
use Illuminate\Console\Command;

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
        $nodeHelper = new NodeHelper();
        $nodeHelper->updateStats();
        $this->updateNode();
        $this->updateUptime();
    }

    public function updateNode()
    {
        $nodes = Node::whereNotNull('protocol_version')->get();
        $max_hight_block = $nodes->max('block_height');
        $base_block = 10;
        $versions = $nodes->pluck('protocol_version');
        $versions = $versions->toArray();
        usort($versions, 'version_compare');
        $highestVersion = (end($versions));
        $grouped = $nodes->groupBy('node_address');
        foreach ($grouped as $key => $values) {
            $versionsNodes = $values->pluck('protocol_version');
            $versionsNodes = $versionsNodes->toArray();
            usort($versionsNodes, 'version_compare');
            $highestVersionNode = (end($versionsNodes));
            if (version_compare($highestVersion, $highestVersionNode, '<')) {
                $user = User::where('public_address_node', $key)->first();
                if ($user) {
                    $user->is_fail_node = 1;
                    $user->save();
                }
            }
            $totalResponsiveness = 0;
            $totalBlockHeight = 0;
            $totalPeer = 0;
            $nodeInfo = ModelsNodeInfo::where('node_address', $key)->first();
            if ($nodeInfo) {
                $groupedVersion = $values->groupBy('protocol_version');
                $countVersion = count($groupedVersion);
                $totalArray = [];
                foreach ($groupedVersion as $ver => $items) {
                    $countItem = count($items);
                    $totalVerResponsiveness = 0;
                    $totalVerBlockHeight = 0;
                    $totalVerPeer = 0;
                    foreach ($items as $item) {
                        $totalVerResponsiveness += $item->update_responsiveness;
                        $totalVerBlockHeight += $item->block_height;
                        $totalVerPeer += $item->peers;
                    }
                    $totalArray[$ver] = [
                        'totalVerResponsiveness' => round($totalVerResponsiveness / $countItem),
                        'totalVerBlockHeight' => round($totalVerBlockHeight / $countItem),
                        'totalVerPeer' => round($totalVerPeer / $countItem),
                    ];
                }
                foreach ($totalArray as $total) {
                    $totalResponsiveness += $total['totalVerResponsiveness'];
                    $totalBlockHeight += $total['totalVerBlockHeight'];
                    $totalPeer += $total['totalVerPeer'];
                }
                $block_height = round($totalBlockHeight / $countVersion);
                $block_height_average =  ($base_block - ($max_hight_block - $block_height)) * 10;
                if ($block_height_average <= 0) {
                    $block_height_average = 0;
                }
                $nodeInfo->block_height = $block_height;
                $nodeInfo->block_height_average = $block_height_average;
                $nodeInfo->update_responsiveness = round($totalResponsiveness / $countVersion);
                $nodeInfo->peers = round($totalPeer / $countVersion);
                $nodeInfo->save();
            }
        }
    }

    public function updateUptime()
    {
        $nodes = ModelsNodeInfo::get();
        $now = Carbon::now('UTC');
        $time = $now->subDays(14);
        foreach ($nodes as $node) {
            $avg_uptime = Node::where('node_address', $node->node_address)->whereNotNull('uptime')->where('created_at', '>=', $time)->avg('uptime');
            $node->uptime = $avg_uptime * 100;
            $node->save();
        }
    }
}
