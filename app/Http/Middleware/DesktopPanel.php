<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DesktopPanel
{
    public function handle(Request $request, Closure $next)
    {
        $mobile = preg_match('/Android|iPhone|iPad|iPod|Mobile/i', $request->userAgent() ?? '')
            || $request->header('Sec-CH-UA-Mobile') === '?1';
        $panel = $request->is('login', 'register', 'home', 'web', 'web/*', 'admin-web', 'admin-web/*', 'app', 'app/*');
        if (!$mobile || !$panel) {
            return $next($request);
        }

        // Email invitations need their existing web login and acceptance flow.
        $invitation = filter_var($request->query('invitation'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($request->is('web/family-group') && $invitation) {
            $request->session()->put('mobile_invitation_until', time() + 1800);
            return $next($request);
        }
        if ($request->is('login', 'register')
            && $request->session()->get('mobile_invitation_until', 0) > time()) {
            return $next($request);
        }

        return redirect()->route('mobile.entry')->header('Cache-Control', 'no-store, private');
    }
}
