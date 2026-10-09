<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

/** Keep null payloads from older clients compatible without accepting new links. */
class RetiredCommerceAssociation implements Rule
{
    public function passes($attribute, $value)
    {
        return $value === null;
    }

    public function message()
    {
        return 'Las promociones y los métodos de pago fueron retirados. Omite este campo.';
    }
}
