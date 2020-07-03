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



      //codigo tomado de: https://stackoverflow.com/questions/57680077/how-can-i-order-this-list-using-paired-father-son-with-php
//funcion auxiliar para ordenar las categorias
function recursiveGroup($level, $grouped) {
  $result = [];
  foreach ($grouped[$level] as $item) {
      $result[$item] = recursiveGroup($item, $grouped);
  }
        return $result;
      }


      $array = Category::all();
     // $arrayBkp = Category::all();
        $grouped = [];


        foreach ($array as $valor) {
          $child = $valor['id'];
          $parent= $valor['padre'];
            if ($parent = 0) {
                $grouped[$valor['nombre']] = [];
            }
        }



      //  while(count($array) > 1) {
          foreach ($array as $valor) {
            $child = $valor['id'];
            $parent= $valor['padre'];
                  $grouped[$parent][] = $child;
          }
        //}

        
        
        // Here we find minimal id for the beginning of our grouped array
      //  $minId = min(array_keys($grouped));
        
       // $result[$minId] = recursiveGroup($minId, $grouped);
       // var_dump($result);
        return $grouped;
      }


}

