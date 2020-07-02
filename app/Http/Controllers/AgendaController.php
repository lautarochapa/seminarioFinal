<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Agenda;

class AgendaController extends Controller
{
    function getAll(){
        return Agenda::all();
      }
}
