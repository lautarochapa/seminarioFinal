<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
class CookingTodayWebContractTest extends TestCase
{
    public function test_dashboard_and_recipes_apply_available_filter()
    {
        $view = file_get_contents(__DIR__.'/../../resources/views/web/user-screen.blade.php');
        $script = file_get_contents(__DIR__.'/../../public/js/user-recipes.js');
        $this->assertStringContainsString('Qué puedo cocinar hoy', $view);
        $this->assertStringContainsString('/web/recipes?availability=available', $view);
        $this->assertStringContainsString("get('availability') === 'available'", $script);
        $this->assertStringContainsString('/recipes/available?per_page=100', $script);
        $this->assertStringContainsString('/recipes/almost-available?per_page=100', $script);
    }
}
