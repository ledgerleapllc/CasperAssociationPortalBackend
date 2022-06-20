<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\User;
use App\Models\UserAddress;

class MoveAddresses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'move:addresses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move Addresses';

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
        $users = User::with('profile')->whereNotNull('public_address_node')->get();
        if ($users && count($users) > 0) {
            foreach ($users as $user) {
                $address = $user->public_address_node;
                $userAddress = UserAddress::where('public_address_node', $address)->first();
                if (!$userAddress) {
                    $userAddress = new UserAddress;
                    $userAddress->user_id = $user->id;
                    $userAddress->public_address_node = $address;
                    $userAddress->node_verified_at = $user->node_verified_at;
                    $userAddress->signed_file = $user->signed_file;
                    $userAddress->is_fail_node = $user->is_fail_node;
                    $userAddress->rank = $user->rank;
                    $userAddress->pending_node = $user->pending_node;
                    $userAddress->validator_fee = $user->validator_fee;
                    $userAddress->node_status = $user->node_status;
                    if ($user->profile && $user->profile->extra_status) {
                        $userAddress->extra_status = $user->profile->extra_status;
                    }
                    $userAddress->save();
                }
                $user->has_address = 1;
                if ($user->node_verified_at) {
                    $user->has_verified_address = 1;
                }
                $user->save();
            }
        }
        return 0;
    }
}
