<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Category;
use App\Supply;
use Symfony\Component\HttpFoundation\Response;


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


        $categories = Category::with('allchildren', 'supplies')->where('padre',0)->get();

        return response()->json([

          'categories' => $categories
  
      ], Response::HTTP_OK);
      }


      function getAll2(){
        $categories = Category::with('allchildren')->sum('products_count','AS','products_count')
        ->get();
      
      
        return response()->json([
        
          'categories' => $categories
        
        ], Response::HTTP_OK);
        }


}






