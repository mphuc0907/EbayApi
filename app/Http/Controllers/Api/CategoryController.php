<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController as ApiController;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends ApiController
{
  public function GetCategory()
  {
    $category = DB::table('category')->get();
    print $category;
  }

  public function addCategory(Request $request)
  {
    try {
      $data = $request->all();
      $category = Category::create($data);

      $success['expires_at'] = now()->addDays(3);
      $success['name'] = $category->name;
      $success['floor_dis'] = $category->floor_dis;
      $success['id_cateory'] = $category->id_cateory;

      return $this->sendResponse($success, 'User register successfully.');
    } catch (\Exception $e) {
      return $this->sendError('An error has occurred. Please try again later', [], 400);
    }
  }

  public function getCategoryByID($id)
  {
    $category = Category::find($id);
    print $category;
  }

  public function updateCategory(Request $request, $id)
  {
    try {
      $category = Category::find($id);
      $name = $request['name'];
      $icon = $request['icon'];
      $id_parent = $request['id_parent'];
      $detaill = $request['detaill'];
      $category->name = $name;
      $category->icon = $icon;
      $category->id_parent = $id_parent;
      $category->detaill = $detaill;
      $category->save();
      return $this->sendResponse($category, 'Category updated successfully.');
    } catch (\Exception $e) {
      return $this->sendError('An error has occurred. Please try again later', [], 400);
    }
  }

  public function deleteCategory($id)
  {
    try {
      $category = Category::find($id);
      $category->delete();
      return $this->sendResponse($category, 'Category deleted successfully.');
    } catch (\Exception $e) {
      return $this->sendError('An error has occurred. Please try again later', [], 400);
    }
  }

}
