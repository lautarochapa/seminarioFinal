<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Category;
use App\Supply;


/**
* @OA\Info(title="API Usuarios", version="1.0")
*
* @OA\Server(url="https://comidacocinacontrol.herokuapp.com")
*/

class CategoryController extends Controller
{

    /**
    * @OA\Get(
    *     path="/api/categoriasOrdenadas",
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


      


      function getAllWithSupplies(){


      $array = Category::all();

      foreach ($array as $valor) {
        $valor['insumos'] = Category::find($valor['id'])->supplies();
    }


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


      function getAll2(){


        $categories = Category::with('children', 'insumos')->where('padre',0)->get();
      // $array = Category::all();

//        foreach ($array as $valor) {
   //       $valor['hijos'] = Category::find($valor['id'])->childrens();
    //  }



        return $categories;
      }



}



