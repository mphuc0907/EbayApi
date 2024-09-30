<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CategorySlug;
use App\Http\Controllers\Api\ApiController as ApiController;
use Illuminate\Support\Facades\DB;

class CategorySlugController extends ApiController
{
    public function GetCategorySlug() {
        $category = DB::table('category_sub')->get();
        print $category;
    }

    public function getCategorySlugByParent($id) {
        $category = DB::table('category_sub')->where('id_category', $id)->get();
        return $category;
    }
    public function addCategorySlug(Request $request) {
        try {
            $data = $request->all();
//            dd($data);
            $category = CategorySlug::create($data);
            $success['expires_at'] = now()->addDays(3);
            $success['name'] =  $category->name;
            $success['id_category'] =  $category->id_category;
            $success['floor_dis'] =  $category->floor_dis;
            return $this->sendResponse($success, 'User register successfully.');
        }catch (\Exception $e) {
            return  $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }
    public function getCategorySlugByID($id) {
        $category = CategorySlug::find($id);
        return $category;
    }

  public function updateCategorySlug(Request $request, $id)
  {
    try {
      $category = CategorySlug::find($id);
      $name = $request['name'];
      $floor_dis = $request['floor_dis'];
      $id_parent = $request['id_category'];
      $category->name = $name;
      $category->id_category = $id_parent;
      $category->floor_dis = $floor_dis;
      $category->save();
      return $this->sendResponse($category, 'Category updated successfully.');
    } catch (\Exception $e) {
      return $this->sendError('An error has occurred. Please try again later', [], 400);
    }
  }

  public function deleteCategorySlug($id)
  {
    try {
      $category = CategorySlug::find($id);
      $category->delete();
      return $this->sendResponse($category, 'Category deleted successfully.');
    } catch (\Exception $e) {
      return $this->sendError('An error has occurred. Please try again later', [], 400);
    }
  }
}
