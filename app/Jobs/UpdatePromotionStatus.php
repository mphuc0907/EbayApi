<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Promotion;
use Carbon\Carbon;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;


class UpdatePromotionStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
        $promotions = Promotion::where('status', 1)->get();
        $promotions->delete();
        // log
        Log::debug('An informational message.');
//
//        foreach ($promotions as $promotion) {
//            $currentDate = Carbon::now();
//
//            $endDate = Carbon::createFromFormat('d-m-Y', $promotion->end_date);
//            if ($currentDate->gt($endDate)) {
//                $promotion->status = 0;
//                $promotion->save();
//                continue;
//            }
//
//            if ($promotion->total_for_using <= 0) {
//                $promotion->status = 2;
//                $promotion->save();
//            }
//        }
    }
}
