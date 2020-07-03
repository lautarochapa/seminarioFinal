<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Category;

class CategoryController extends Controller
{
    function getAll(){
        return Category::all();
      }


      


      function getAllOrdenadas(){


      $array = Category::all();

function ffather($arr,$el=0){
  $final=array();
  foreach ($arr as $key => $value) {
      if ($el == $value["padre"]){
          $value['nombre']["hijos"]=ffather($arr,$value["id"]);
          $final[]= $value;
      }
  }
  return $final;
}
        return ffather($array);
      }


}

