<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController as ApiController;
use App\Models\kiosk_sub_product;
use App\Models\kiosk_sub_upload_history;
use App\Models\Kiot;
use App\Models\KiotSub;
use App\Models\OrderDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class kiosk_sub_productController extends ApiController
{
//    public function productAddFile(Request $request) {
//        try {
//            $data = $request->all();
//            $dataProduct = kiosk_sub_product::create($data);
//
//            $productKiot['kiosk_sub_id'] = $dataProduct['kiosk_sub_id'];
//            $productKiot['file_name'] = $dataProduct['namefile'];
//            $productKiot['result'] = $dataProduct['value'];
//            $productKiot['status'] = $dataProduct['status'];
//
//            $product = kiosk_sub_upload_history::create($productKiot);
//
//            return $this->sendResponse($dataProduct, 'Upload file successfully.');
//
//
//        }catch (\Exception $e) {
//            return  $this->sendError('An error has occurred. Please try again later', [], 400);
//        }
//    }

    public function checkFileName(Request $request)
    {
        try {
            $data = kiosk_sub_product::where('namefile', $request['namefile'])->where('kiosk_sub_id', $request['id'])->get();
            if (count($data) > 0) {
                return $this->sendResponse(true, 'File name is exist.');
            } else {
                return $this->sendResponse(false, 'File name is not exist.');
            }
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function UploadFileTxt(Request $request)
    {
        try {
            date_default_timezone_set('Asia/Ho_Chi_Minh');

            if (!$request->has(['value', 'count_data', 'invalid_count'])) {
                return $this->sendError('Invalid request data', [], 400);
            }

            $data = $request['value'];
            $countData = $request['count_data'];
            $invalidCount = $request['invalid_count'];

            if (empty($data)) {
                return $this->sendError('No data provided', [], 400);
            }

            $kioskSubId = $data[0]['kiosk_sub_id'];
            $kioskSub = KiotSub::find($kioskSubId);

            if (!$kioskSub) {
                return $this->sendError('Kiosk sub not found', [], 404);
            }

            $kiosk = Kiot::find($kioskSub->kiosk_id);

            if (!$kiosk) {
                return $this->sendError('Kiosk not found', [], 404);
            }

            // Lấy category sub của kiosk hiện tại
            $currentCategorySub = $kiosk->category_sub_id;

            // Khởi tạo data_encrypt
            $data_encrypt = $data;

            // Kiểm tra xem có cần check trùng không
            if ($kiosk->is_duplicate === "1") {
                // Lấy tất cả kiosk có cùng category sub
                $relatedKiosks = Kiot::where('category_sub_id', $currentCategorySub)->pluck('_id');

                // Lấy tất cả kiosk_sub của các kiosk có cùng category sub
                $relatedKioskSubs = KiotSub::whereIn('kiosk_id', $relatedKiosks)->pluck('_id');

                // Lấy tất cả các giá trị đã tồn tại trong cùng category sub
                $existingProducts = kiosk_sub_product::whereIn('kiosk_sub_id', $relatedKioskSubs)
                    ->get()
                    ->map(function ($item) {
                        return trim($item->value);
                    })
                    ->toArray();

                $data_encrypt = $this->checkDuplicateAndEncrypt($data, $existingProducts);
            }

            foreach ($data_encrypt as $key => $value) {
                $data_encrypt[$key]['created_at'] = date('d-m-Y H:i:s');
            }

            // Insert products
            kiosk_sub_product::insert($data_encrypt);

            // Create upload history
            $uploadHistory = [
                'kiosk_sub_id' => $kioskSubId,
                'file_name' => $data_encrypt[0]['namefile'],
                'result' => json_encode($data_encrypt),
                'status' => $data_encrypt[0]['status'],
                'created_at' => date('d-m-Y H:i:s'),
                'invalid_count' => $invalidCount
            ];

            kiosk_sub_upload_history::insert($uploadHistory);

            // Update quantity
            $this->updateKioskQuantity($kioskSub, $countData);

            return $this->sendResponse($data, 'Upload file successfully.');

        } catch (\Exception $e) {
            Log::error('Upload file error: ' . $e->getMessage());
            return $this->sendError('An error has occurred. Please try again later', [], 500);
        }
    }
    private function checkDuplicateAndEncrypt($data, $existingValues = [])
    {
        $valueMap = []; // Mảng để theo dõi các giá trị đã xuất hiện

        // Xử lý từng record
        foreach ($data as $key => $value) {
            $currentValue = trim($value['value']);

            // Kiểm tra nếu giá trị đã tồn tại trong DB hoặc trong data hiện tại
            if (in_array($currentValue, $existingValues) || isset($valueMap[$currentValue])) {
                // Phân tích giá trị thành email và password
                $parts = explode('|', $currentValue);

                if (count($parts) === 2) {
                    $email = $parts[0];
                    $password = $parts[1];

                    // Mã hoá cả email và password
                    $encodedEmail = base64_encode(md5($email, true));
                    $encodedPassword = base64_encode(md5($password, true));

                    $shortEmail = substr($encodedEmail, 0, 12);
                    $shortPassword = substr($encodedPassword, 0, 12);

                    // Cập nhật giá trị đã mã hoá
                    $data[$key]['value'] = $shortEmail . '|' . $shortPassword;

                    // Log để debug
                    Log::info('Found exact duplicate and encrypted: ' . $email);
                }
            } else {
                // Thêm giá trị vào map để kiểm tra cho các record tiếp theo
                $valueMap[$currentValue] = true;
            }
        }

        return $data;
    }

    private function updateKioskQuantity($kioskSub, $countData)
    {
        $currentQuantity = $kioskSub->quantity ?? 0;

        if ($currentQuantity < 0) {
            $kioskSub->quantity = $countData;
        } else {
            $kioskSub->quantity = $currentQuantity + $countData;
        }

        $kioskSub->save();
    }
    public function getKiotSubProductByParentId($id)
    {
        try {
            $data = kiosk_sub_product::where('kiosk_sub_id', $id)->get();
            return $this->sendResponse($data, 'Get data successfully.');
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function getKiotSubHistoryByParentId($id)
    {
        try {
            $data = kiosk_sub_upload_history::where('kiosk_sub_id', $id)->get();
            $data = $data->groupBy('file_name');
            return $this->sendResponse($data, 'Get data successfully.');
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function getKiotSubHistoryById($id)
    {
        try {
            $data = kiosk_sub_upload_history::where('_id', $id)->get();
            return $this->sendResponse($data, 'Get data successfully.');
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function getProductByFileName(Request $request)
    {
        try {
            $id = $request['id'];
            $data = kiosk_sub_product::where('namefile', $request['namefile'])->where('kiosk_sub_id', $id)->get();
            return $this->sendResponse($data, 'Get data successfully.');
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function getProductTxtNoSell(Request $request)
    {
        try {
            $id = $request['id'];
            $data = kiosk_sub_product::where('namefile', $request['namefile'])
                ->where('kiosk_sub_id', $id)
                ->where('status', 1)
                ->get();
            return $this->sendResponse($data, 'Get data successfully.');
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function deleteProductTxt(Request $request)
    {
        try {
            $id = $request['id'];

            $countData = kiosk_sub_product::where('namefile', $request['namefile'])
                ->where('kiosk_sub_id', $id)
                ->delete();

            $kiot_sub_upload_history = kiosk_sub_upload_history::where('file_name', $request['namefile'])
                ->where('kiosk_sub_id', $id)
                ->delete();

            $kiotCuronData = KiotSub::find($id);

            if ($kiotCuronData) {
                $kiotCuronData['quantity'] = max(0, $kiotCuronData['quantity'] - $countData);
                $kiotCuronData->save();
            }

            return $this->sendResponse($countData, 'Delete data successfully.');
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later.', [], 400);
        }
    }

    public function reportProduct(Request $request)
    {
            $data = $request->all();
            $id_product = $data['id_product'] ?? null;
            $is_report = $data['is_report'] ?? false;
            $order_code = $data['order_code'] ?? null;
            if ($id_product == null) {
                $order_detail = OrderDetail::where('order_code', $order_code)->get();
                $order_detail = $order_detail->pluck('value')->toArray();
                $order_detail = json_decode($order_detail[0]);
                $data_product = $data['data_product'];
                $result_ids = [];
                foreach ($data_product as $product_email) {
                    foreach ($order_detail as $detail) {
                        $value_email = explode('|', $detail->value)[0];
                        if ($product_email === $value_email) {
                            $result_ids[] = $detail->id;
                        }
                    }
                }
                $total_request = count($data_product);
                $product = kiosk_sub_product::whereIn('_id', $result_ids)->update(['is_report' => "true"]);
                $total_success = kiosk_sub_product::whereIn('_id', $result_ids)->where('is_report', 'true')->count();
                $total_fail = $total_request - $total_success;
                $result = [
                    'total_request' => $total_request,
                    'total_success' => $total_success,
                    'total_fail' => $total_fail
                ];
                return $this->sendResponse($result, 'Report product successfully.');
            } else {
                $product = kiosk_sub_product::where('_id', $id_product)->update(['is_report' => $is_report]);
            }
            return $this->sendResponse($product, 'Report product successfully.');
    }

    public function checkReport($id){
        $product = kiosk_sub_product::where('_id', $id)->first();
        return $this->sendResponse($product->is_report, 'Check report successfully.');
    }
}
