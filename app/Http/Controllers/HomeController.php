<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        // User role
        $role = Auth::user()->nivel_acceso; 
        
        // Check user role
        switch ($role) {
            case '1':
                    return view('profiles/comensal');
                break;
                case '2':
                        return view('profiles/admin');
                    break;
                    case '3':
                            return view('profiles/superadmin');
                        break;
                        case '4':
                                return view('profiles/somelier');
                            break;
                            case '5':
                                    return view('profiles/chef');
                                break;
                                case '6':
                                        return view('profiles/nutritionist');
                                    break; 
            default:
                    return view('/login'); 
                break;
        }
    }
}
