<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController as ApiController;
use App\Http\Controllers\Controller;
use App\Models\KiotSub;
use Illuminate\Http\Request;

class KiotSubController extends ApiController
{
  public function GetSubKiotByKioskId($id)
  {
    try {
      $subkiot = KiotSub::where('kiosk_id', $id)->get();
      return $this->sendResponse($subkiot, 'Get Subkiot successfully.');

    } catch (\Exception $e) {
      return $this->sendError('An error has occurred. Please try again later', [], 400);
    }
  }

  public function getSubKiotById($id)
  {
    try {
      $subkiot = KiotSub::where('_id', $id)->first();
      return $this->sendResponse($subkiot, 'User register successfully.');

    } catch (\Exception $e) {
      return $this->sendError('An error has occurred. Please try again later', [], 400);
    }
  }

  public function AddSubKiot(Request $request)
  {
    try {
//            $data = $request->all();
      $count = KiotSub::where('kiosk_id', $request['kiosk_id'])->count();
      if ($count >= 5) {
        return $this->sendError('Bạn chỉ có thể thêm tối đa 5 SubKiot cho một kiosk.', ['status' => 'error'], 400);
      }
      $data['kiosk_id'] = $request['kiosk_id'];
      $data['name'] = $request['name'];
      $data['price'] = $request['price'];
      $data['status'] = $request['status'];

      $subkiot = KiotSub::create($data);

      $success['expires_at'] = now()->addDays(3);
      $success['name'] = $subkiot->name;
      return $this->sendResponse($success, 'User register successfully.');

    } catch (\Exception $e) {
      return $this->sendError('An error has occurred. Please try again later', [], 400);
    }
  }

  public function updateSubKiot(Request $request)
  {
    try {
      $subkiot = KiotSub::where('_id', $request['_id'])->first();
      if (!$subkiot) {
        return $this->sendError('Subkiot not found', [], 404);
      }
      if (!is_null($request->input('price'))) {
        $subkiot->price = $request->input('price');
      }
      if (!is_null($request->input('name'))) {
        $subkiot->name = $request->input('name');
      }
      $subkiot->status = $request->input('status');
      $subkiot->_id = $request->input('_id');
      $subkiot->save();
      return $this->sendResponse($subkiot, 'Update successfully.');
    } catch (\Exception $e) {
      return $this->sendError('An error has occurred. Please try again later', [], 400);
    }
  }
  function deleteSubKiot(Request $request)
  {
    try {
      $subkiot = KiotSub::where('_id', $request['_id'])->first();
      if (!$subkiot) {
        return $this->sendError('Subkiot not found', [], 404);
      }
      $subkiot->delete();
      return $this->sendResponse([], 'Delete successfully.');
    } catch (\Exception $e) {
      return $this->sendError('An error has occurred. Please try again later', [], 400);
    }
  }
}
