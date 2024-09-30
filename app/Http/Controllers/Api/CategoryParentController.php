<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController as ApiController;
use App\Http\Controllers\Controller;
use App\Models\CategoryParent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryParentController extends ApiController
{
  public function GetCategoryParent()
  {
    $category = DB::table('category_parent')->get();
    print $category;
  }

  public function addCategoryParent(Request $request)
  {
    try {
      $data = $request->all();
      $category = CategoryParent::create($data);

      $success['expires_at'] = now()->addDays(3);
      $success['name'] = $category->name;
      return $this->sendResponse($success, 'User register successfully.');
    } catch (\Exception $e) {
      return $this->sendError('An error has occurred. Please try again later', [], 400);
    }
  }

  public function getCategoryParentByID($id)
  {
    $category = CategoryParent::find($id);
    print $category;
  }

  public function updateCategoryParent(Request $request, $id)
  {
    try {
      $category = CategoryParent::find($id);
      $name = $request['name'];
      $category->name = $name;
      $category->save();
      return $this->sendResponse($category, 'Category updated successfully.');
    } catch (\Exception $e) {
      return $this->sendError('An error has occurred. Please try again later', [], 400);
    }
  }

  public function deleteCategoryParent($id)
  {
    try {
      $category = CategoryParent::find($id);
      $category->delete();
      return $this->sendResponse($category, 'Category deleted successfully.');
    } catch (\Exception $e) {
      return $this->sendError('An error has occurred. Please try again later', [], 400);
    }
  }

//    public function getCategoryParentByID(Request $request)
//    {
//        try {
//
//            $currentID = $request['currentID'];
//            dd($currentID);
//            $categoryID = DB::table('category_parent')->find($currentID);
//            print $categoryID;
//        } catch (\Exception $e) {
//            return  $this->sendError('An error has occurred. Please try again later', [], 400);
//        }
//    }
}
