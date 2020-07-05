<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    function getAll(){

      $users = User::all();
        return response()->json([

          'users' => $users
      
      ], Response::HTTP_OK);
      }
  
}
