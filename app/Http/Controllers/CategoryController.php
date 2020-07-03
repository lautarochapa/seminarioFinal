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
   




// funcion find father recibe el arreglo y el id por defecto 0
function ffather($arr,$el=0){
  // creamos una varible final que contendra nuestros hijos
  $final=array();
  // recorremos el arreglo
  foreach ($arr as $key => $value) {
      // validamos que el id actual coincida con el id_padre "encontramos un hijo"
      if ($el == $value["padre"]){
          // volvemos a llamar a la funcion find father para buscar ahora los hijos de los hijos
          $value["hijos"]=ffather($arr,$value["id"]);
          // cargamos el nuevo valor en $final
          $final[]= $value;
      }
  }
  // retornamos $final
  return $final;
}






        return ffather($array);
      }


}

