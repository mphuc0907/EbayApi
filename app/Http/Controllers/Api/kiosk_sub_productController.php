<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\kiosk_sub_upload_history;
use App\Models\KiotSub;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\ApiController as ApiController;
use App\Models\kiosk_sub_product;

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

    public function UploadFileTxt(Request $request)
    {
//        try {
                $data = $request['value'];

                $countData = $request['count_data'];

                $product = kiosk_sub_product::insert($data);
                $productKiot = array();
                foreach ($data as $key => $value) {
                  $productKiot['kiosk_sub_id'] = $value['kiosk_sub_id'];
                  $productKiot['file_name'] = $value['namefile'];
                  $productKiot['result'] = json_encode($data);
                  $productKiot['status'] = $value['status'];
                }

                $hightStory = kiosk_sub_upload_history::insert($productKiot);
                $kiotCuronData = KiotSub::find($productKiot['kiosk_sub_id']);

                if ($kiotCuronData['quantity'] < 0 || $kiotCuronData['quantity'] == null) {
                    $kiotCuronData['quantity'] = $countData;
                }else {

                    $kiotCuronData['quantity'] = $kiotCuronData['quantity'] + $countData;
                }
                $kiotCuronData->save();
                return $this->sendResponse($data, 'Upload file successfully.');

//        } catch (\Exception $e) {
//            return $this->sendError('An error has occurred. Please try again later', [], 400);
//        }
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
}
