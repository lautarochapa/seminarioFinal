<?php

namespace Tests\Unit;

use App\Services\Scraping\ScrapingExecutionGuard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ScrapingExecutionGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_acquire_normal_retorna_true_y_ocupa_el_lock()
    {
        $guard = new ScrapingExecutionGuard();

        $this->assertTrue($guard->acquire('cookpad'));
    }

    public function test_lock_vigente_bloquea_un_segundo_intento_para_el_mismo_source()
    {
        $first = new ScrapingExecutionGuard();
        $second = new ScrapingExecutionGuard();

        $this->assertTrue($first->acquire('cookpad'));
        $this->assertFalse($second->acquire('cookpad'));
    }

    public function test_lock_de_un_source_no_afecta_a_otro_source()
    {
        $cookpad = new ScrapingExecutionGuard();
        $carrefour = new ScrapingExecutionGuard();

        $this->assertTrue($cookpad->acquire('cookpad'));
        $this->assertTrue($carrefour->acquire('carrefour'));
    }

    public function test_release_libera_el_lock_para_un_proximo_acquire()
    {
        $first = new ScrapingExecutionGuard();
        $this->assertTrue($first->acquire('cookpad'));

        $first->release();

        $second = new ScrapingExecutionGuard();
        $this->assertTrue($second->acquire('cookpad'));
    }

    public function test_release_solo_libera_el_lock_propio_no_el_de_otro_owner()
    {
        $first = new ScrapingExecutionGuard();
        $this->assertTrue($first->acquire('cookpad'));

        // $second nunca pudo adquirir el lock (owner distinto): release() no
        // debe liberar el lock que pertenece a $first.
        $second = new ScrapingExecutionGuard();
        $this->assertFalse($second->acquire('cookpad'));
        $second->release();

        $third = new ScrapingExecutionGuard();
        $this->assertFalse($third->acquire('cookpad'));
    }

    public function test_ttl_explicito_mas_corto_permite_recuperar_el_lock_antes_que_el_default()
    {
        Carbon::setTestNow(Carbon::now());
        $first = new ScrapingExecutionGuard();
        // acquire() aplica un piso de 60s (max(60, $ttlSeconds)) como
        // resguardo, incluso pasando un valor menor.
        $this->assertTrue($first->acquire('cookpad', 1));

        // No se libera (simula un proceso interrumpido antes de su finally).
        Carbon::setTestNow(Carbon::now()->addSeconds(65));

        $second = new ScrapingExecutionGuard();
        $this->assertTrue($second->acquire('cookpad', 1), 'El lock debe quedar libre una vez vencido su propio TTL.');
    }

    public function test_ttl_de_recipe_no_afecta_el_default_usado_por_product_scraping()
    {
        Carbon::setTestNow(Carbon::now());
        config(['scraping.recipe_lock_ttl_seconds' => 1]);

        // RunScrapingJob (product) llama acquire($code) sin segundo argumento:
        // debe seguir usando scraping.lock_ttl_seconds, sin verse afectado
        // por el TTL corto configurado para recetas.
        $product = new ScrapingExecutionGuard();
        $this->assertTrue($product->acquire('carrefour'));

        Carbon::setTestNow(Carbon::now()->addSeconds(65));

        $productRetry = new ScrapingExecutionGuard();
        $this->assertFalse(
            $productRetry->acquire('carrefour'),
            'Con el TTL default (3600s) el lock de product scraping no debe expirar en 65 segundos.'
        );
    }
}
