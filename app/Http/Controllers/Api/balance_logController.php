<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\ApiController as ApiController;
use App\Models\balance_log;

class balance_logController extends ApiController
{
   public function getBalanceLogByUserId()
   {
      try {
        $user = auth()->user();
         $balance_log = balance_log::where('user_id', $user['_id'])->orderBy('created_at', 'desc')->get();
         return $this->sendResponse($balance_log, 'Balance Log retrieved successfully.');
      } catch (\Exception $e) {
         return $this->sendError($e->getMessage());
      }
   }
}
