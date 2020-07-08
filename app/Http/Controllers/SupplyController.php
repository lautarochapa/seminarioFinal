<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Supply;
use Symfony\Component\HttpFoundation\Response;

class SupplyController extends Controller
{
  function getAll(){
    $supplies = Supply::withCount(['products' => function ($query) {
      $query->withFilters();
  }])
  ->get();

  
    return response()->json([
    
      'supplies' => $supplies
    
    ], Response::HTTP_OK);
    }



 
    function getSelected(){

      if(request()->input('sups')){
        

      $supplies = Supply::with(['category'=> function ($query) {
        $query->with('grandparent');
    }])->whereIn('id', request()->input('sups'))->get();
  
    
      return response()->json([
      
        'supplies' => $supplies
      
      ], Response::HTTP_OK);

    }else{
      return response()->json([
      
        'supplies' => []
      
      ], Response::HTTP_OK);
    }

      }

      
      





}
