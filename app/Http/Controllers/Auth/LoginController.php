<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    //protected $redirectTo = RouteServiceProvider::HOME;

    public function redirectTo(){
        
        // User role
        $role = Auth::user()->nivel_acceso; 
        
        // Check user role
        switch ($role) {
            case '1':
                    return '/comensal';
                break;
                case '2':
                        return '/admin';
                    break;
                    case '3':
                            return '/superadmin';
                        break;
                        case '4':
                                return '/somelier';
                            break;
                            case '5':
                                    return '/chef';
                                break;
                                case '6':
                                        return '/nutritionist';
                                    break; 
            default:
                    return '/login'; 
                break;
        }
    }


    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }
}
