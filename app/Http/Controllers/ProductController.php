<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Product;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{

      function getAll(){


        $products = Product::with('brand', 'supply')->paginate(40);

        return response()->json([

          'products' => $products
  
      ], Response::HTTP_OK);
      }



}