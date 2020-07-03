<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Category;

class CategoryController extends Controller
{
    function getAll(){
        return Category::all();
      }


      
      //codigo tomado de: https://stackoverflow.com/questions/57680077/how-can-i-order-this-list-using-paired-father-son-with-php
//funcion auxiliar para ordenar las categorias
      function recursiveGroup($level, $grouped) {
  $result = [];
  foreach ($grouped[$level] as $item) {
      $result[$item] = recursiveGroup($item, $grouped);
  }

  return $result;
}

      function getAllOrdenadas(){
        $array = Auditorias::all();


        $grouped = [];
        foreach ($array as $valor) {
          $child = $valor['id'];
          $parent= $valor['padre'];



            if (!array_key_exists($parent, $grouped)) {
                $grouped[$parent] = [];
            }
        
            $grouped[$parent][] = $child;
        }
        
        // Here we find minimal id for the beginning of our grouped array
        $minId = min(array_keys($grouped));
        
        $result[$minId] = recursiveGroup($minId, $grouped);
        var_dump($result);
        return $array;
      }


}

