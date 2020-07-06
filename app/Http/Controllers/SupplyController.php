<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Supply;
use Symfony\Component\HttpFoundation\Response;

class SupplyController extends Controller
{
  function getAll(){
    $supplies = Supply::all();
    return response()->json([
    
      'supplies' => $supplies
    
    ], Response::HTTP_OK);
    }
}
