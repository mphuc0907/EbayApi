<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends ApiController
{
    public function adminGetAllActivityLog(Request $request)
    {
        try {
            // Kiểm tra quyền truy cập
            $user = auth()->user();
            if ($user['role'] != 4) {
                return $this->sendError('Không có quyền truy cập', [], 403);
            }

            $perPage = $request->get('per_page', 20);
            $currentPage = $request->get('page', 1);

            $logs = ActivityLog::orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $currentPage);

            $response = [
                'data' => $logs->items(),
                'meta' => [
                    'total' => $logs->total(),
                    'per_page' => $logs->perPage(),
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'next_page_url' => $logs->nextPageUrl(),
                    'prev_page_url' => $logs->previousPageUrl(),
                ]
            ];

            return $this->sendResponse($response, 'Activity logs retrieved successfully.');

        } catch (\Exception $e) {
            return $this->sendError('Lỗi lấy dữ liệu: ' . $e->getMessage(), [], 400);
        }
    }

    public function adminSearchActivityLog(Request $request)
    {
        try {
            // Kiểm tra quyền truy cập
            $user = auth()->user();
            if ($user['role'] != 4) {
                return $this->sendError('Không có quyền truy cập', [], 403);
            }

            $perPage = $request->get('per_page', 20);
            $currentPage = $request->get('page', 1);
            $supporter_id = $request->get('supporter_id');
            $target_id = $request->get('target_id');
            $is_success = $request->get('is_success');
            $from_date = $request->get('from_date');
            $to_date = $request->get('to_date');

            $logs = ActivityLog::query()
                ->when($supporter_id, function ($query, $supporter_id) {
                    return $query->where('supporter_id', $supporter_id);
                })
                ->when($target_id, function ($query, $target_id) {
                    return $query->where('target_id', $target_id);
                })
                ->when($is_success !== null, function ($query) use ($is_success) {
                    return $query->where('is_success', (bool)$is_success);
                })
                ->when($from_date, function ($query, $from_date) {
                    return $query->where('created_at', '>=', new \DateTime($from_date));
                })
                ->when($to_date, function ($query, $to_date) {
                    return $query->where('created_at', '<=', new \DateTime($to_date));
                })
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $currentPage);

            $response = [
                'data' => $logs->items(),
                'meta' => [
                    'total' => $logs->total(),
                    'per_page' => $logs->perPage(),
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'next_page_url' => $logs->nextPageUrl(),
                    'prev_page_url' => $logs->previousPageUrl(),
                ]
            ];

            return $this->sendResponse($response, 'Activity logs retrieved successfully.');

        } catch (\Exception $e) {
            return $this->sendError('Lỗi lấy dữ liệu: ' . $e->getMessage(), [], 400);
        }
    }
}
