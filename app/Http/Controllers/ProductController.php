<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Product;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{

      function getAll(){


        $products = Product::with('brand', 'supply')->paginate(5);

        return response()->json([

          'products' => $products
  
      ], Response::HTTP_OK);
      }

      function getAll2(){


        return Product::with('brand', 'supply')->paginate(15);

      }


}