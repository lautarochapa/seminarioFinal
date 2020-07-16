<?php

namespace App\QueryFilters;
use App\QueryFilters\Filter;

use Closure;

class Sort extends Filter
{

    protected function applyFilter($builder){
        return $builder->orderBy('nombre', request($this->filterName())); 
    }

}