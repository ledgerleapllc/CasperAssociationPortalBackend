<?php

namespace App\Console\Commands;

use App\Models\Metric;
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
        // $this->updateUptime();
        $this->updateRank();
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
                    $user->node_status = 'Offline';
                    $user->save();
                }
            }
            $totalResponsiveness = 0;
            $totalBlockHeight = 0;
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
                }
                $block_height = round($totalBlockHeight / $countVersion);
                $block_height_average =  ($base_block - ($max_hight_block - $block_height)) * 10;
                if ($block_height_average <= 0) {
                    $block_height_average = 0;
                }
                $nodeInfo->block_height_average = $block_height_average;
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

    public function updateRank()
    {
        $slide_value_uptime = 20;
        $slide_value_update_responsiveness = 20;
        $slide_value_delegotors = 20;
        $slide_value_stake_amount = 20;
        $slide_delegation_rate = 20;

        $max_uptime = Node::max('uptime');
        $max_uptime = $max_uptime * 100;
        $max_delegators = ModelsNodeInfo::max('delegators_count');
        $max_stake_amount = ModelsNodeInfo::max('total_staked_amount');

        $users = User::with(['metric'])->where('role', 'member')
            ->leftJoin('node_info', 'users.public_address_node', '=', 'node_info.node_address')
            ->where('banned', 0)
            ->select([
                'users.*',
                'node_info.delegation_rate',
                'node_info.delegators_count',
                'node_info.total_staked_amount',
            ])
            ->get();
        foreach ($users as $user) {
            $latest = Node::where('node_address', $user->public_address_node)->whereNotnull('protocol_version')->orderBy('created_at', 'desc')->first();
            if (!$latest) {
                $latest = new Node();
            }
            $delegation_rate = $user->delegation_rate ?  $user->delegation_rate / 100 : 1;
            if (!$user->metric && !$user->nodeInfo) {
                $user->totalScore = null;
                continue;
            }
            $latest_uptime_node = isset($latest->uptime) ? $latest->uptime * 100 : null;
            $latest_update_responsiveness_node = $latest->update_responsiveness ?? null;
            $metric = $user->metric;
            if (!$metric) {
                $metric = new Metric();
            }
            $latest_uptime_metric = $metric->uptime ? $metric->uptime : null;
            $latest_update_responsiveness_metric = $metric->update_responsiveness ? $metric->update_responsiveness : null;

            $latest_uptime = $latest_uptime_node ??  $latest_uptime_metric ?? 1;
            $latest_update_responsiveness = $latest_update_responsiveness_node ??  $latest_update_responsiveness_metric ?? 1;

            $delegators_count = $user->delegators_count ? $user->nodeInfo->delegators_count : 0;
            $total_staked_amount = $user->total_staked_amount ? $user->nodeInfo->total_staked_amount : 0;

            $uptime_score = ($slide_value_uptime * $latest_uptime) / 100;
            $update_responsiveness_score = ($slide_value_update_responsiveness * $latest_update_responsiveness) / 100;
            $dellegator_score = ($delegators_count / $max_delegators) * $slide_value_delegotors;
            $satke_amount_score = ($total_staked_amount / $max_stake_amount) * $slide_value_stake_amount;
            $delegation_rate_score = ($slide_delegation_rate * (1 - $delegation_rate)) / 100;
            $totalScore =  $uptime_score + $update_responsiveness_score + $dellegator_score + $satke_amount_score + $delegation_rate_score;

            $user->totalScore = $totalScore;
        }
        $users = $users->sortByDesc('totalScore')->values();
        foreach ($users as $key => $user) {
            User::where('id', $user->id)->update(['rank' => $key + 1]);
        }
    }

}
