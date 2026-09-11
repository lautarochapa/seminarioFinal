<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SupermarketScrapingWebContractTest extends TestCase
{
    public function test_admin_form_sends_and_displays_optional_search_term()
    {
        $view = file_get_contents(__DIR__.'/../../resources/views/web/admin-screen.blade.php');
        $script = file_get_contents(__DIR__.'/../../public/js/admin-supermarket-scraping.js');

        $this->assertStringContainsString('Buscar producto (opcional)', $view);
        $this->assertStringContainsString('name="search_term"', $view);
        $this->assertStringContainsString('Ej: arroz, tomate, 7790580146115', $view);
        $this->assertStringContainsString('body.search_term = form.elements.search_term.value.trim()', $script);
        $this->assertStringContainsString('job.parameters.search_term', $script);
    }
}
