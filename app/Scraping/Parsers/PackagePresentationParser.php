<?php

namespace App\Scraping\Parsers;

class PackagePresentationParser
{
    public function parse(string $name): ?array
    {
        if (! preg_match('/(?:^|\s)(\d+(?:[\.,]\d+)?)\s*(kg|kgs?|kilogramos?|g|gr|grs|gramos?|ml|mililitros?|l|lt|lts|litros?)\.?\s*$/iu', trim($name), $match)) {
            return null;
        }

        $unit = mb_strtolower($match[2], 'UTF-8');
        if (preg_match('/^(kg|kgs|kilogramos?)$/u', $unit)) {
            $unit = 'kg';
        } elseif (preg_match('/^(g|gr|grs|gramos?)$/u', $unit)) {
            $unit = 'g';
        } elseif (preg_match('/^(ml|mililitros?)$/u', $unit)) {
            $unit = 'ml';
        } else {
            $unit = 'l';
        }

        return [
            'net_quantity' => (float) str_replace(',', '.', $match[1]),
            'package_unit_code' => $unit,
        ];
    }
}
