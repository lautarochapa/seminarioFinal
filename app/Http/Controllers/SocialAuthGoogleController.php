<?php
namespace App\Http\Controllers;


use App\Providers\SocialGoogleAccountService;
use Illuminate\Http\Request;
use Socialite;



class SocialAuthGoogleController extends Controller
{
  /**
   * Create a redirect method to google api.
   *
   * @return void
   */
  public function redirect()
  {
      return Socialite::driver('google')->redirect();
  }
/**
     * Return a callback method from google api.
     *
     * @return callback URL from google
     */
    public function callback(SocialGoogleAccountService $service)
    {
        $user = $service->createOrGetUser(Socialite::driver('google')->user());
        auth()->login($user);
        $destination = \App\Services\Auth\AuthRedirect::afterLogin();
        if (parse_url($destination, PHP_URL_PATH) === '/web/family-group') {
            return redirect()->to($destination);
        }
        return redirect()->to('/home');
    }
}
