<?php

namespace App\Console\Commands;

use App\Console\Helper;
use Illuminate\Console\Command;
use App\Models\TokenPrice as TokenPriceModel;

class TokenPriceCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'token-price:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Token Price';

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
        $response = Helper::getTokenPrice();
        if (
            isset($response) && 
            isset($response['data']) && 
            isset($response['data']['quote']) &&
            isset($response['data']['quote']['USD']) &&
            isset($response['data']['quote']['USD']['price'])
        ) {
            $price = (float) $response['data']['quote']['USD']['price'];
            $price = round($price, 2);

            if ($price > 0) {
                $tokenPrice = new TokenPriceModel;
                $tokenPrice->price = $price;
                $tokenPrice->save();

                $limit = 48 * 7;
                $count = TokenPriceModel::where('id', '>', 0)
                                        ->get()
                                        ->count();
                if ($count > $limit) {
                    TokenPriceModel::orderBy('created_at', 'asc')
                                    ->limit(1)
                                    ->delete();
                }
            }
        }
    }
}
