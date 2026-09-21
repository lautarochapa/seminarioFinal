<?php

namespace App\Services\Auth;

class AuthRedirect
{
    public static function afterLogin(): string
    {
        $intended = session('url.intended', '');
        $query = [];
        parse_str(parse_url($intended, PHP_URL_QUERY) ?: '', $query);
        $id = filter_var($query['invitation'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id && parse_url($intended, PHP_URL_PATH) === '/web/family-group') {
            return url('/web/family-group').'?invitation='.$id;
        }
        return url('/web');
    }
}
