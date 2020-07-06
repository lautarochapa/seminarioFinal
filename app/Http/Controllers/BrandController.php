<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Brand;
use Symfony\Component\HttpFoundation\Response;

class BrandController extends Controller
{
  function getAll(){
    $brands = Brand::withCount(['products' => function ($query) {
      $query->withFilters();
  }])
  ->get();
  
    return response()->json([
    
      'brands' => $brands
    
    ], Response::HTTP_OK);
    }
}

