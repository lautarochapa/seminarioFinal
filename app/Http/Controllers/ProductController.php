<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Product;
use Symfony\Component\HttpFoundation\Response;


use Illuminate\Pipeline\Pipeline;

class ProductController extends Controller
{

    /*  function getAll(){


        $products = Product::with('brand', 'supply')->paginate(40);

        return response()->json([

          'products' => $products
  
      ], Response::HTTP_OK);
      }*/




      public function getAll()
    {
      
        $products = Product::withFilters()->paginate(21);

        return response()->json([

          'products' => $products
  
      ], Response::HTTP_OK);
    }


   
      public function viewProduct($id)
      {
        $product = Product::find($id);
        return view('profiles/admin/product', compact('product'));
      }


      function getProductsNames(){


        $products = Product::select('id','nombre')->get();
        return response()->json([

          'products' => $products
  
      ], Response::HTTP_OK);
      }



      

      function getBySupply($id){


        $products = Product::with('brand', 'supply')->where('supply_id', '=', $id)->paginate(40);
        return response()->json([

          'products' => $products
  
      ], Response::HTTP_OK);
      }




/*  16/07 implementacion de pipelines */

Public function index(){

  $products = app(Pipeline::class)
    ->send(Product::query())
    ->through([
      \App\QueryFilters\Supply::class,
      \App\QueryFilters\Sort::class,
    ])
    ->thenReturn()
    ->get();



  return view('products.index', compact('products'));
}






}