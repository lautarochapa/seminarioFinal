<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class RecipeCookWebContractTest extends TestCase
{
    public function test_web_sends_cook_payload_as_object_not_double_encoded_json()
    {
        $script = file_get_contents(__DIR__.'/../../public/js/recipe-favorites-actions.js');
        $this->assertStringContainsString("endpoint('/recipes/' + s.recipeId + '/cook')", $script);
        $this->assertStringContainsString('body:   body', $script);
        $this->assertStringNotContainsString('body:   JSON.stringify(body)', $script);
        $this->assertStringContainsString('body.deduct_stock = true', $script);
        $this->assertStringContainsString('body.family_group_id', $script);
    }
}
