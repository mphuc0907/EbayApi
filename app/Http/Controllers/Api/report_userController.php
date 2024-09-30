<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\report_user;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\ApiController as ApiController;
use Illuminate\Support\Facades\DB;

class report_userController extends ApiController
{
    public function report_user() {
        $report_user = DB::table('report_user')->get();
        print $report_user;
    }

    public function AddReport(Request $request) {
        try {
            if (!empty(auth()->user())) {
                $user = auth()->user();
                $data['user_create'] = $user['_id'];
            }

            $data['contact'] = $request['contact'];
            $data['content'] = $request['content'];
            $data['user_report'] = $request['user_report'];


            $report = report_user::create($data);

            return $this->sendResponse(null, 'User report successfully.');

        }catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }
}
