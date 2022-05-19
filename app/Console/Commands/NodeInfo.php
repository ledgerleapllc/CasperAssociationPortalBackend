<?php

namespace App\Console\Commands;

use App\Models\Metric;
use App\Models\Node;
use App\Models\NodeInfo as ModelsNodeInfo;
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
            $key = strtolower($key);

            $versionsNodes = $values->pluck('protocol_version');
            $versionsNodes = $versionsNodes->toArray();

            usort($versionsNodes, 'version_compare');
            
            $highestVersionNode = (end($versionsNodes));
            if (version_compare($highestVersion, $highestVersionNode, '<')) {
                $userAddress = UserAddress::where('public_address_node', $key)->first();
                if ($userAddress) {
                    $userAddress->is_fail_node = 1;
                    $userAddress->node_status = 'Offline';
                    $userAddress->save();
                }
            }

            $totalResponsiveness = $totalBlockHeight = 0;
            
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

        $users = User::with(['addresses'])
                        ->where('role', 'member')
                        ->where('banned', 0)
                        ->get();
        if ($users && count($users) > 0) {
            foreach ($users as $user) {
                $addresses = $user->addresses ?? null;
                if (!$addresses) {
                    $user->is_fail_node = 1;
                    $user->node_status = 'Offline';
                    $user->save();
                } else if (count($addresses) > 0) {
                    $hasNotFailNode = $hasNotOfflineStatus = false;
                    foreach ($addresses as $address) {
                        if ($address->is_fail_node != 1) {
                            $hasNotFailNode = true;
                        }
                        if ($address->node_status != 'Offline') {
                            $hasNotOfflineStatus = true;
                        }
                    }
                    if (!$hasNotFailNode) {
                        $user->is_fail_node = 1;
                        $user->node_status = 'Offline';
                        $user->save();
                    } else if (!$hasNotOfflineStatus) {
                        $user->node_status = 'Offline';
                        $user->save();
                    }
                }
            }
        }
    }

    public function updateUptime()
    {
        $nodes = ModelsNodeInfo::get();
        $now = Carbon::now('UTC');
        $time = $now->subDays(14);
        foreach ($nodes as $node) {
            $avg_uptime = Node::where('node_address', strtolower($node->node_address))
                                ->whereNotNull('uptime')
                                ->where('created_at', '>=', $time)
                                ->avg('uptime');
            $node->uptime = $avg_uptime * 100;
            $node->save();
        }
    }

    public function updateRank()
    {
        $slide_value_uptime = $slide_value_update_responsiveness = $slide_value_delegotors = $slide_value_stake_amount = $slide_delegation_rate = 20;

        $max_uptime = Node::max('uptime');
        $max_uptime = $max_uptime * 100;
        $max_delegators = ModelsNodeInfo::max('delegators_count');
        $max_stake_amount = ModelsNodeInfo::max('total_staked_amount');

        DB::table('user_addresses')->update(['rank' => null]);
        $userAddresses = UserAddress::with(['user', 'user.metric', 'user.nodeInfo'])
                            ->leftJoin('node_info', 'user_addresses.public_address_node', '=', 'node_info.node_address')
                            ->select([
                                'user_addresses.*',
                                'node_info.delegation_rate',
                                'node_info.delegators_count',
                                'node_info.total_staked_amount',
                            ])
                            ->whereHas('user')
                            ->get();
        
        foreach ($userAddresses as $userAddress) {
            $latest = Node::where('node_address', strtolower($userAddress->public_address_node))->whereNotnull('protocol_version')->orderBy('created_at', 'desc')->first();
            if (!$latest) $latest = new Node();
            $delegation_rate = $userAddress->delegation_rate ? $userAddress->delegation_rate / 100 : 1;
            if (!$userAddress->user->metric && !$userAddress->user->nodeInfo) {
                $userAddress->totalScore = null;
                continue;
            }
            $latest_uptime_node = isset($latest->uptime) ? $latest->uptime * 100 : null;
            $latest_update_responsiveness_node = $latest->update_responsiveness ?? null;
            $metric = $userAddress->user->metric;
            if (!$metric) $metric = new Metric();
            
            $latest_uptime_metric = $metric->uptime ? $metric->uptime : null;
            $latest_update_responsiveness_metric = $metric->update_responsiveness ? $metric->update_responsiveness : null;

            $latest_uptime = $latest_uptime_node ??  $latest_uptime_metric ?? 1;
            $latest_update_responsiveness = $latest_update_responsiveness_node ??  $latest_update_responsiveness_metric ?? 1;

            $delegators_count = $userAddress->delegators_count ? $userAddress->user->nodeInfo->delegators_count : 0;
            $total_staked_amount = $userAddress->total_staked_amount ? $userAddress->user->nodeInfo->total_staked_amount : 0;

            $uptime_score = ($slide_value_uptime * $latest_uptime) / 100;
            $update_responsiveness_score = ($slide_value_update_responsiveness * $latest_update_responsiveness) / 100;
            $dellegator_score = ($delegators_count / $max_delegators) * $slide_value_delegotors;
            $stake_amount_score = ($total_staked_amount / $max_stake_amount) * $slide_value_stake_amount;
            $delegation_rate_score = ($slide_delegation_rate * (1 - $delegation_rate)) / 100;
            $totalScore =  $uptime_score + $update_responsiveness_score + $dellegator_score + $stake_amount_score + $delegation_rate_score;

            $userAddress->totalScore = $totalScore;
        }
        $userAddresses = $userAddresses->sortByDesc('totalScore')->values();
        foreach ($userAddresses as $key => $userAddress) {
            UserAddress::where('id', $userAddress->id)->update(['rank' => $key + 1]);
            $user = User::where('public_address_node', $userAddress->public_address_node)->first();
            if ($user) {
                $user->rank = $key + 1;
                $user->save();
            }
        }
    }
}