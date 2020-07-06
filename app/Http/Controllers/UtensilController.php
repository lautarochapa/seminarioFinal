<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Utensil;
use Symfony\Component\HttpFoundation\Response;

class UtensilController extends Controller
{
    function getAll(){
      $utensils = Utensil::all();
      return response()->json([
      
        'utensils' => $utensils
      
      ], Response::HTTP_OK);
      }
}
