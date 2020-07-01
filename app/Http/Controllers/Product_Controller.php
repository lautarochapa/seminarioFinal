<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Product;

class Product_Controller extends Controller
{
    function getAll(){
        return Product::all();
      }
}