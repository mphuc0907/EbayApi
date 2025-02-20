<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\ApiController as ApiController;

class DashboardContetroller extends ApiController
{
    public function Statistic(){
        try {
            // Tạo mảng chứa kết quả cho 12 tháng
            $statistics = [];

            // Lấy tháng và năm hiện tại
            $currentMonth = date('m');
            $currentYear = date('Y');

            // Lặp qua 12 tháng gần nhất
            for ($i = 0; $i < 12; $i++) {
                // Tính toán tháng và năm của tháng trong quá khứ
                $month = $currentMonth - $i;
                $year = $currentYear;

                // Nếu tháng < 1, chuyển về tháng của năm trước
                if ($month < 1) {
                    $month += 12;
                    $year--;
                }

                // Lấy tổng giá trị đơn hàng cho tháng đó, nếu không có thì gán = 0
                $orderTotal = Order::whereMonth('created_at', $month)
                        ->whereYear('created_at', $year)
                        ->sum('total_price') ?? 0;

                // Lấy tổng giá trị đơn hàng cho tháng đó, nếu không có thì gán = 0
                $floTotal = Order::whereMonth('created_at', $month)
                        ->whereYear('created_at', $year)
                        ->sum('admin_amount') ?? 0;

                // Lấy số lượng người dùng tạo ra trong tháng đó, nếu không có thì gán = 0
                $userCount = User::whereMonth('created_at', $month)
                        ->whereYear('created_at', $year)
                        ->count() ?? 0;

                // Thêm kết quả vào mảng thống kê
                $statistics[] = [
                    'month' => $month,
                    'year' => $year,
                    'total_admin' => $floTotal,
                    'total_order' => $orderTotal,
                    'total_user' => $userCount
                ];
            }

            // Đảo ngược mảng để sắp xếp từ tháng cũ đến tháng hiện tại
            $statistics = array_reverse($statistics);

            // Trả về kết quả thống kê
            return response()->json($statistics, 200);

        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function StatisticBySellerId($id)
    {
        try {
            // Tạo mảng chứa kết quả cho 12 tháng
            $statistics = [];

            $currentMonth = date('m');
            $currentYear = date('Y');

            // Lặp qua 12 tháng gần nhất
            for ($i = 0; $i < 12; $i++) {
                // Tính toán tháng và năm của tháng trong quá khứ
                $month = $currentMonth - $i;
                $year = $currentYear;

                // Nếu tháng < 1, chuyển về tháng của năm trước
                if ($month < 1) {
                    $month += 12;
                    $year--;
                }

                // Lấy tổng giá trị đơn hàng cho tháng đó, nếu không có thì gán = 0
                $orderTotal = Order::whereMonth('created_at', $month)
                    ->whereYear('created_at', $year)
                    ->where('id_seller', $id)
                    ->sum('total_price') ?? 0;

                // Lấy tổng giá trị đơn hàng cho tháng đó, nếu không có thì gán = 0
                $floTotal = Order::whereMonth('created_at', $month)
                    ->whereYear('created_at', $year)
                    ->where('id_seller', $id)
                    ->sum('admin_amount') ?? 0;

                // Lấy số lượng người dùng tạo ra trong tháng đó, nếu không có thì gán = 0
                $userCount = User::whereMonth('created_at', $month)
                    ->whereYear('created_at', $year)
                    ->where('id_seller', $id)
                    ->count() ?? 0;

                // Thêm kết quả vào mảng thống kê
                $statistics[] = [
                    'month' => $month,
                    'year' => $year,
                    'total_admin' => $floTotal,
                    'total_order' => $orderTotal,
                    'total_user' => $userCount
                ];
            }
            $statistics = array_reverse($statistics);
            return response()->json($statistics, 200);
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function StaticDayBySellerId($id)
    {
        try {
            // Tạo mảng chứa kết quả cho 30 ngày
            $statistics = [];

            // Lấy ngày hiện tại
            $currentDate = \Carbon\Carbon::now();

            // Lặp qua 30 ngày gần nhất
            for ($i = 0; $i < 30; $i++) {
                // Tính toán ngày trong quá khứ và định dạng thành "d/m"
                $date = $currentDate->copy()->subDays($i);
                $formattedDate = $date->format('d/m'); // Định dạng ngày thành d/m

                // Lấy tổng giá trị đơn hàng cho ngày đó, nếu không có thì gán = 0
                $orderTotal = Order::whereDate('created_at', $date)->where('id_seller', $id)->sum('total_price') ?? 0;

                // Lấy tổng giá trị admin (floTotal) cho ngày đó, nếu không có thì gán = 0
                $floTotal = Order::whereDate('created_at', $date)->where('id_seller', $id)->sum('admin_amount') ?? 0;

                // Lấy số lượng người dùng tạo ra trong ngày đó, nếu không có thì gán = 0
                $userCount = User::whereDate('created_at', $date)->where('id_seller', $id)->count() ?? 0;

                // Thêm kết quả vào mảng thống kê
                $statistics[] = [
                    'date' => $formattedDate, // Lưu trữ ngày định dạng d/m
                    'total_admin' => $floTotal,
                    'total_order' => $orderTotal,
                    'total_user' => $userCount
                ];
            }

            // Đảo ngược mảng để sắp xếp từ ngày cũ đến ngày hiện tại
            $statistics = array_reverse($statistics);

            // Trả về kết quả thống kê
            return response()->json($statistics, 200);

        }
        catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }
    public function StatisticByDay() {
        try {
            // Tạo mảng chứa kết quả cho 30 ngày
            $statistics = [];

            // Lấy ngày hiện tại
            $currentDate = \Carbon\Carbon::now();

            // Lặp qua 30 ngày gần nhất
            for ($i = 0; $i < 30; $i++) {
                // Tính toán ngày trong quá khứ và định dạng thành "d/m"
                $date = $currentDate->copy()->subDays($i);
                $formattedDate = $date->format('d/m'); // Định dạng ngày thành d/m

                // Lấy tổng giá trị đơn hàng cho ngày đó, nếu không có thì gán = 0
                $orderTotal = Order::whereDate('created_at', $date)->sum('total_price') ?? 0;

                // Lấy tổng giá trị admin (floTotal) cho ngày đó, nếu không có thì gán = 0
                $floTotal = Order::whereDate('created_at', $date)->sum('admin_amount') ?? 0;

                // Lấy số lượng người dùng tạo ra trong ngày đó, nếu không có thì gán = 0
                $userCount = User::whereDate('created_at', $date)->count() ?? 0;

                // Thêm kết quả vào mảng thống kê
                $statistics[] = [
                    'date' => $formattedDate, // Lưu trữ ngày định dạng d/m
                    'total_admin' => $floTotal,
                    'total_order' => $orderTotal,
                    'total_user' => $userCount
                ];
            }

            // Đảo ngược mảng để sắp xếp từ ngày cũ đến ngày hiện tại
            $statistics = array_reverse($statistics);

            // Trả về kết quả thống kê
            return response()->json($statistics, 200);

        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }



}
