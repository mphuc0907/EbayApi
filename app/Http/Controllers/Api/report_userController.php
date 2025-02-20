<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController as ApiController;
use App\Models\report_user;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class report_userController extends ApiController
{
    public function report_user()
    {
        $report_user = DB::table('report_user')->get();
        print $report_user;
    }

    public function AddReport(Request $request)
    {
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

        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function GetReports(Request $request)
    {
        try {
            $query = report_user::query();
            // tìm theo tên user
            if ($request->has('user_report')) {
                $user_report = User::where('name', 'like', '%' . $request->user_report . '%')->first();
                if ($user_report) {
                    $query->where('user_report', $user_report['_id']);
                }
            }
            // tìm theo khoảng thời gian
            if ($request->has('from') && $request->has('to')) {
                $query->whereBetween('created_at', [$request->from, $request->to]);
            }
            $perPage = $request->input('per_page', 10);
            $report = $query->paginate($perPage);
            foreach ($report as $item) {
                $item['name_user_report'] = User::find($item->user_report)->name;
                if (!empty($item->user_create)){
                    $item['name_user_create'] = User::find($item->user_create)->name;
                }else{
                    $item['name_user_create'] = 'Anonymous';
                }
            }
            return $this->sendResponse([
                'data' => $report->items(),
                'meta' => [
                    'current_page' => $report->currentPage(),
                    'last_page' => $report->lastPage(),
                    'per_page' => $report->perPage(),
                    'total' => $report->total()
                ]
            ], 'Reports retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }
}
