<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class RecipeScrapingWebContractTest extends TestCase
{
    public function test_boton_ver_jobs_hace_scroll_y_resalta_la_seccion_de_jobs()
    {
        $view = file_get_contents(__DIR__.'/../../resources/views/web/admin-screen.blade.php');
        $script = file_get_contents(__DIR__.'/../../public/js/admin-recipe-scraping.js');
        $layout = file_get_contents(__DIR__.'/../../resources/views/layouts/admin-web.blade.php');

        $this->assertStringContainsString('data-recipe-scraping-jobs-section', $view);
        $this->assertStringContainsString('data-screen-secondary-action', $script);
        $this->assertStringContainsString('highlightJobsSection', $script);
        $this->assertStringContainsString('scrollIntoView', $script);
        $this->assertStringContainsString('highlight-focus', $script);
        $this->assertStringContainsString('.highlight-focus', $layout);
    }

    public function test_boton_ver_individual_sigue_disponible()
    {
        $script = file_get_contents(__DIR__.'/../../public/js/admin-recipe-scraping.js');

        $this->assertStringContainsString('data-recipe-scraping-show', $script);
        $this->assertStringContainsString("loadJob(root, showButton.getAttribute('data-recipe-scraping-show'))", $script);
    }

    public function test_formulario_expone_busqueda_opcional_de_recetas()
    {
        $view = file_get_contents(__DIR__.'/../../resources/views/web/admin-screen.blade.php');
        $script = file_get_contents(__DIR__.'/../../public/js/admin-recipe-scraping.js');

        $this->assertStringContainsString('Buscar recetas (opcional)', $view);
        $this->assertStringContainsString('name="search_term"', $view);
        $this->assertStringContainsString('Ej: arroz, pollo, milanesa', $view);
        $this->assertStringContainsString('Ejecutar Cookpad', $view);
        $this->assertStringContainsString('body.search_term = term', $script);
    }

    public function test_tabla_y_detalle_muestran_el_termino_buscado()
    {
        $script = file_get_contents(__DIR__.'/../../public/js/admin-recipe-scraping.js');

        $this->assertStringContainsString('function searchTerm(job)', $script);
        $this->assertStringContainsString('Buscar: ', $script);
        $this->assertStringContainsString('<span>Buscar</span>', $script);
    }
}
