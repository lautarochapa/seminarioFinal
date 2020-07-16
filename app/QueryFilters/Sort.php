<?php

namespace App\QueryFilters;

use Closure;

class Sort{

    public function handle($request, Closure $next){

        if(! $request()->has('sort')){
            return $next($request);
        }
        $builder = $next($request);

        return $builder->orderBy('nombre', request('sort')); 
        
        
    }

}