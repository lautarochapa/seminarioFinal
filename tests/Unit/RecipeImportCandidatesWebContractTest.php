<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class RecipeImportCandidatesWebContractTest extends TestCase
{
    public function test_pantalla_expone_acciones_de_mapeo_automatico()
    {
        $view = file_get_contents(__DIR__.'/../../resources/views/web/admin-screen.blade.php');
        $script = file_get_contents(__DIR__.'/../../public/js/admin-recipe-import-candidates.js');

        $this->assertStringContainsString('data-import-candidates-apply-suggestions', $view);
        $this->assertStringContainsString('Aplicar mapeos sugeridos', $view);
        $this->assertStringContainsString('data-import-candidates-recalculate', $view);
        $this->assertStringContainsString('Recalcular sugerencias', $view);
        $this->assertStringContainsString('data-import-candidates-recalculate-visible', $view);
        $this->assertStringContainsString('Recalcular pendientes visibles', $view);

        $this->assertStringContainsString('function applySuggestions(root)', $script);
        $this->assertStringContainsString('/apply-suggestions', $script);
        $this->assertStringContainsString('function recalculateVisible(root)', $script);
        $this->assertStringContainsString('/recalculate-suggestions-bulk', $script);
        $this->assertStringContainsString("postAction(root, 'recalculate-suggestions')", $script);
    }

    public function test_lineas_de_ingrediente_muestran_badges_de_confianza()
    {
        $script = file_get_contents(__DIR__.'/../../public/js/admin-recipe-import-candidates.js');

        $this->assertStringContainsString('function lineBadge(kind)', $script);
        $this->assertStringContainsString('Detectado automaticamente', $script);
        $this->assertStringContainsString("'Sugerido'", $script);
        $this->assertStringContainsString('Sin determinar', $script);
    }

    public function test_listado_y_detalle_muestran_estado_de_mapeo()
    {
        $script = file_get_contents(__DIR__.'/../../public/js/admin-recipe-import-candidates.js');

        $this->assertStringContainsString('function readinessBadge(summary)', $script);
        $this->assertStringContainsString('Lista para aprobar', $script);
        $this->assertStringContainsString('Requiere revision', $script);
        $this->assertStringContainsString('function mappingSummary(candidate)', $script);
    }

    public function test_manual_override_no_se_pisa_al_aplicar_sugerencias()
    {
        // Contrato de backend (verificado tambien en
        // RecipeImportCandidatesTest::test_aplicar_sugerencias_no_pisa_mapeo_manual_ya_existente):
        // applySuggestedMappings() nunca escribe sobre un indice que ya
        // figura en ingredient_mappings.
        $service = file_get_contents(__DIR__.'/../../app/Services/RecipeImportCandidates/RecipeImportCandidatesService.php');

        $this->assertStringContainsString('unmappedIngredientIndices', $service);
        $this->assertStringContainsString('skippedAlreadyMapped', $service);
    }

    public function test_pantalla_expone_seleccion_y_acciones_batch()
    {
        $view = file_get_contents(__DIR__.'/../../resources/views/web/admin-screen.blade.php');
        $script = file_get_contents(__DIR__.'/../../public/js/admin-recipe-import-candidates.js');

        $this->assertStringContainsString('data-import-candidates-select-all', $view);
        $this->assertStringContainsString('data-import-candidates-select', $view);
        $this->assertStringContainsString('data-import-candidates-apply-bulk', $view);
        $this->assertStringContainsString('Aplicar sugerencias seleccionadas', $view);
        $this->assertStringContainsString('data-import-candidates-approve-bulk', $view);
        $this->assertStringContainsString('Aprobar seleccionadas listas', $view);

        $this->assertStringContainsString('function applySuggestionsBulk(root)', $script);
        $this->assertStringContainsString('/apply-suggestions-bulk', $script);
        $this->assertStringContainsString('function approveBulkAction(root)', $script);
        $this->assertStringContainsString('/approve-bulk', $script);
        $this->assertStringContainsString('function selectedIds()', $script);
        $this->assertStringContainsString('candidate_ids: ids', $script);
    }
}
