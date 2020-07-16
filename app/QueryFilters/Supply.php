<?php

namespace App\QueryFilters;

use Closure;

class Supply{

    public function handle($request, Closure $next){

        if(! request()->has('supply')){
            return $next($request);
        }
        $builder = $next($request);

        return $builder->where('supply_id', request('supply')); 
        
        
    }

}