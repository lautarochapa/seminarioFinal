<?php

namespace App\QueryFilters;
use App\QueryFilters\Filter;

use Closure;

class Supply extends Filter
{

    protected function applyFilter($builder){
        return $builder->where('supply_id', $this->filterName()); 
    }

}