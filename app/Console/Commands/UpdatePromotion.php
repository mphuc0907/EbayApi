<?php

namespace App\Console\Commands;

use App\Models\Promotion;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdatePromotion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-promotion';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $promotions = Promotion::all();

        foreach ($promotions as $promotion) {
            $currentDate = Carbon::now();

            $endDate = Carbon::createFromFormat('d-m-Y', $promotion->end_date);
            if ($currentDate->gt($endDate)) {
                $promotion->status = 0;
                $promotion->save();
                continue;
            }

            if ($promotion->total_for_using <= 0) {
                $promotion->status = 2;
                $promotion->save();
            }
        }
    }
}
