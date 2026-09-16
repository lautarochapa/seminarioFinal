<?php

namespace Tests\Feature\Exceptions;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiExceptionHandlerTest extends TestCase
{
    public function test_ruta_api_inexistente_retorna_404()
    {
        $response = $this->getJson('/api/v1/totally-nonexistent-route-xyz');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'ROUTE_NOT_FOUND');
        $this->assertNotEmpty($response->json('error.message'));
        $this->assertNotEmpty($response->json('trace_id'));
    }

    public function test_metodo_http_incorrecto_en_ruta_existente_retorna_405()
    {
        // .../import-candidates/{id}/approve solo acepta POST; GET en ese
        // path no colisiona con ninguna otra ruta (a diferencia de probar
        // con la base "import-candidates" sola, que matchea el wildcard
        // DELETE /admin/recipes/{id} de otro recurso y da 404, no 405).
        $response = $this->getJson('/api/v1/admin/recipes/import-candidates/9/approve');

        $response->assertStatus(405)
            ->assertJsonPath('error.code', 'METHOD_NOT_ALLOWED');
    }

    public function test_excepcion_inesperada_real_sigue_siendo_500()
    {
        Route::middleware('api')->get('/api/v1/__qa_force_unexpected_exception', function () {
            throw new \RuntimeException('boom inesperado');
        });

        $response = $this->getJson('/api/v1/__qa_force_unexpected_exception');

        $response->assertStatus(500)
            ->assertJsonPath('error.code', 'INTERNAL_ERROR');
    }
}
