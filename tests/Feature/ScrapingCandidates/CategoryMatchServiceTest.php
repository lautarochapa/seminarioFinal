<?php

namespace Tests\Feature\ScrapingCandidates;

use App\ProductCategory;
use App\Services\ScrapingCandidates\CategoryMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryMatchServiceTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $name): ProductCategory
    {
        return ProductCategory::create([
            'name'   => $name,
            'status' => 'active',
        ]);
    }

    private function service(): CategoryMatchService
    {
        return app(CategoryMatchService::class);
    }

    public function test_matchea_hoja_exacta_arroz()
    {
        $arroz = $this->category('Arroz');

        $result = $this->service()->match('/Almacen/Arroz y legumbres/Arroz/');

        $this->assertEquals($arroz->id, $result['category_id']);
        $this->assertEquals('category_leaf', $result['confidence']);
    }

    public function test_matchea_nivel_padre_cuando_la_hoja_no_tiene_equivalente()
    {
        $harinas = $this->category('Harinas');

        $result = $this->service()->match('/Almacen/Harinas/Otras harinas/');

        $this->assertEquals($harinas->id, $result['category_id']);
        $this->assertEquals('category_ancestor', $result['confidence']);
    }

    public function test_matchea_aceites_por_hoja()
    {
        $aceites = $this->category('Aceites');

        $result = $this->service()->match('/Almacen/Aceites, Vinagres y Aderezos/Aceites/');

        $this->assertEquals($aceites->id, $result['category_id']);
    }

    public function test_ignora_acentos_al_comparar()
    {
        // El catalogo local puede tener el nombre sin tilde ("Almacen") pero
        // VTEX scrapea la ruta con tilde ("Almacén"): antes esto no
        // matcheaba nunca por la diferencia de un caracter.
        $almacen = $this->category('Almacen');

        $result = $this->service()->match('/Almacén/Pastas secas/Fideos guiseros y para sopas/');

        $this->assertEquals($almacen->id, $result['category_id']);
        $this->assertEquals('category_ancestor', $result['confidence']);
    }

    public function test_ignora_acentos_en_sentido_inverso()
    {
        // Catalogo local CON tilde, ruta scrapeada SIN tilde.
        $categoria = $this->category('Almacén');

        $result = $this->service()->match('/Almacen/Fideos/');

        $this->assertEquals($categoria->id, $result['category_id']);
    }

    public function test_sin_equivalente_local_queda_unresolved()
    {
        $this->category('Arroz');

        // Ninguno de los segmentos ("Lacteos y productos frescos", "Tapas y
        // pastas frescas") tiene equivalente exacto en el catalogo local:
        // debe quedar sin sugerir, nunca "adivinar" una categoria parecida.
        $result = $this->service()->match('/Lácteos y productos frescos/Tapas y pastas frescas/');

        $this->assertNull($result['category_id']);
    }

    public function test_nombre_ambiguo_en_catalogo_local_no_elige_ninguna()
    {
        $this->category('Arroz');
        $this->category('Arroz');

        $result = $this->service()->match('/Almacen/Arroz/');

        $this->assertNull($result['category_id']);
    }

    public function test_no_hace_fuzzy_por_plural_o_variacion_de_palabra()
    {
        // "Pastas secas" no es "Pastas": no se acepta como match parcial.
        $this->category('Pastas');

        $result = $this->service()->match('/Almacen/Pastas secas/Fideos largos/');

        $this->assertNull($result['category_id']);
    }
}
