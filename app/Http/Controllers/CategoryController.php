<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Category;


/**
* @OA\Info(title="API Usuarios", version="1.0")
*
* @OA\Server(url="http://swagger.local")
*/

class CategoryController extends Controller
{

    /**
    * @OA\Get(
    *     path="/api/users",
    *     summary="Mostrar usuarios",
    *     @OA\Response(
    *         response=200,
    *         description="Mostrar todos los usuarios."
    *     ),
    *     @OA\Response(
    *         response="default",
    *         description="Ha ocurrido un error."
    *     )
    * )
    */


    function getAll(){
        return Category::all();
      }


      


      function getAllOrdenadas(){


      $array = Category::all();

function ffather($arr,$el=0){
  $final=array();
  foreach ($arr as $key => $value) {
      if ($el == $value["padre"]){
          $value["hijos"]=ffather($arr,$value["id"]);
          $final[]= $value;
      }
  }
  return $final;
}
        return ffather($array);
      }


}

