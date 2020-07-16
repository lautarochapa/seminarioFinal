<?php

namespace App\QueryFilters;

use Closure;

class Supply extends Filter
{

    protected function applyFilter($builder){
        return $builder->where('supply_id', $this->filterName()); 
    }

}