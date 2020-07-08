<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Product;
use Symfony\Component\HttpFoundation\Response;

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


      function getOne($id){


        $products = Product::find($id);
        return response()->json([

          'products' => $products
  
      ], Response::HTTP_OK);
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



}