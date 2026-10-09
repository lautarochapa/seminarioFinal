<?php

namespace Tests\Unit;

use App\AuditLog;
use App\Ingredient;
use App\Recipe;
use App\RecipeIngredient;
use App\UnitMeasure;
use App\User;
use App\Exceptions\RecipeIngredients\RecipeIngredientException;
use App\Http\Requests\Api\V1\RecipeIngredients\RecipeIngredientRequest;
use App\Http\Requests\Api\V1\RecipeImportCandidates\MapIngredientRequest;
use App\Repositories\RecipeIngredients\RecipeIngredientRepository;
use App\Repositories\RecipeAvailability\RecipeAvailabilityRepository;
use App\Repositories\RecipeCost\RecipeCostRepository;
use App\Repositories\RecipeNutrition\RecipeNutritionRepository;
use App\Services\RecipeIngredients\RecipeIngredientService;
use App\Services\RecipeAvailability\RecipeAvailabilityService;
use App\Services\RecipeCost\RecipeCostService;
use App\Services\RecipeNutrition\RecipeNutritionService;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase;

/** Standalone SQLite memory fixture: no app bootstrap, .env, migrations, seeds or HTTP. */
class RecipeFreeQuantityTest extends TestCase
{
    private $capsule;
    private $previous;
    private $service;
    private $actor;
    private $recipe;
    private $ingredient;
    private $unit;

    protected function setUp(): void
    {
        $this->previous = [Facade::getFacadeApplication(), Model::getConnectionResolver(), Model::getEventDispatcher()];
        $container = new Container;
        $this->capsule = new Capsule($container);
        $this->capsule->addConnection(['driver'=>'sqlite','database'=>':memory:','prefix'=>'']);
        $this->capsule->setEventDispatcher(new Dispatcher($container));
        $this->capsule->bootEloquent();
        $container->instance('db', $this->capsule->getDatabaseManager());
        Facade::clearResolvedInstances(); Facade::setFacadeApplication($container); Model::clearBootedModels();
        $db = $this->capsule->getConnection();
        $this->assertSame('sqlite', $db->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME));
        $this->assertSame(':memory:', $db->getDatabaseName());
        $schema = $db->getSchemaBuilder();
        $schema->create('products', function(Blueprint $t) { $t->increments('id'); $t->integer('ingredient_id')->nullable(); $t->string('status')->default('active'); $t->boolean('is_active')->default(true); $t->softDeletes(); });
        $schema->create('ingredient_equivalences', function(Blueprint $t) { $t->increments('id'); $t->integer('source_ingredient_id'); $t->integer('target_ingredient_id'); $t->string('status'); });
        $schema->create('shopping_list_items', function(Blueprint $t) { $t->increments('id'); $t->integer('shopping_list_id'); $t->integer('ingredient_id')->nullable(); $t->integer('product_id')->nullable(); $t->decimal('quantity',12,4); $t->integer('unit_id'); $t->decimal('estimated_price')->nullable(); $t->string('status'); $t->text('notes')->nullable(); $t->timestamps(); });
        $schema->create('recipes', function(Blueprint $t) { $t->increments('id'); $t->string('name'); $t->integer('owner_user_id'); $t->boolean('is_official')->default(false); $t->integer('servings')->default(1); $t->timestamps(); $t->softDeletes(); });
        $schema->create('ingredients', function(Blueprint $t) { $t->increments('id'); $t->string('name'); $t->string('status'); $t->timestamps(); $t->softDeletes(); });
        $schema->create('unit_measures', function(Blueprint $t) { $t->increments('id'); $t->string('name'); $t->string('code'); $t->string('status'); $t->timestamps(); });
        $schema->create('recipe_ingredients', function(Blueprint $t) { $t->increments('id'); $t->integer('recipe_id'); $t->integer('ingredient_id'); $t->integer('unit_id'); $t->decimal('quantity',12,4); $t->integer('specific_product_id')->nullable(); $t->boolean('is_optional'); $t->text('notes')->nullable(); $t->integer('sort_order'); $t->timestamps(); });
        $schema->create('audit_logs', function(Blueprint $t) { $t->increments('id'); $t->integer('user_id'); $t->string('action'); $t->string('entity_name'); $t->string('entity_id'); $t->text('old_values')->nullable(); $t->text('new_values'); $t->string('ip_address'); $t->string('user_agent'); $t->timestamp('created_at')->nullable(); });
        $this->actor = new User; $this->actor->id=19;
        $this->recipe = Recipe::create(['name'=>'Receta local','owner_user_id'=>19,'is_official'=>false,'servings'=>1]);
        $this->ingredient = Ingredient::create(['name'=>'sal','status'=>'active']);
        $this->unit = UnitMeasure::create(['name'=>'Pizca','code'=>'pinch','status'=>'active']);
        $this->service = new RecipeIngredientService(new RecipeIngredientRepository);
    }

    protected function tearDown(): void
    {
        $this->capsule->getDatabaseManager()->purge(); Model::clearBootedModels();
        $this->previous[1] ? Model::setConnectionResolver($this->previous[1]) : Model::unsetConnectionResolver();
        $this->previous[2] ? Model::setEventDispatcher($this->previous[2]) : Model::unsetEventDispatcher();
        Facade::clearResolvedInstances(); Facade::setFacadeApplication($this->previous[0]);
        parent::tearDown();
    }

    private function payload(array $extra=[]): array { return array_merge(['ingredient_id'=>$this->ingredient->id,'unit_id'=>$this->unit->id,'quantity'=>0,'is_optional'=>true,'notes'=>'sal a gusto'], $extra); }
    private function add(array $extra=[]): RecipeIngredient { return $this->service->add($this->actor,$this->recipe->id,$this->payload($extra),'127.0.0.1','local-test'); }
    private function invoke($object,string $method,array $args=[]) { $m=new \ReflectionMethod($object,$method); $m->setAccessible(true); return $m->invokeArgs($object,$args); }
    private function validation(string $class,array $data) { $request=$class::create('/local','POST',$data); $factory=new Factory(new Translator(new ArrayLoader,'es')); $validator=$factory->make($request->all(),$request->rules()); if(method_exists($request,'withValidator'))$request->withValidator($validator); return $validator; }
    private function loaded(array $rows): Recipe { $this->recipe->setRelation('ingredients',collect($rows)); return $this->recipe; }
    private function freeRow(array $data=[]): RecipeIngredient { $row=new RecipeIngredient($this->payload($data)); $row->setRelation('ingredient',$this->ingredient); $row->setRelation('unit',$this->unit); return $row; }
    private function bare(string $class,array $properties=[]) { $reflection=new \ReflectionClass($class); $object=$reflection->newInstanceWithoutConstructor(); foreach($properties as $name=>$value){$property=$reflection->getProperty($name);$property->setAccessible(true);$property->setValue($object,$value);} return $object; }

    public function test_request_accepts_zero_only_when_optional_and_rejects_negative_or_tiny_quantities(): void
    {
        foreach([RecipeIngredientRequest::class,MapIngredientRequest::class] as $class) {
            $base=$this->payload(['ingredient_index'=>0]); if($class===RecipeIngredientRequest::class)unset($base['ingredient_index']);
            $this->assertFalse($this->validation($class,$base)->fails());
            foreach([['quantity'=>0,'is_optional'=>false],['quantity'=>-1],['quantity'=>0.00001,'is_optional'=>false]] as $bad) $this->assertTrue($this->validation($class,array_merge($base,$bad))->fails());
            $this->assertFalse($this->validation($class,array_merge($base,['quantity'=>0.5,'is_optional'=>false]))->fails());
        }
    }

    public function test_add_and_both_resources_preserve_text_and_expose_free_quantity(): void
    {
        $row=$this->add(); $this->assertSame('0.0000',$row->quantity); $this->assertTrue($row->is_optional); $this->assertSame('sal a gusto',$row->notes);
        foreach([\App\Http\Resources\Api\V1\RecipeIngredients\RecipeIngredientResource::class,\App\Http\Resources\Api\V1\Recipes\RecipeIngredientResource::class] as $class) {
            $data=(new $class($row))->toArray(null); $this->assertSame('sal a gusto',$data['quantity_label']); $this->assertFalse($data['tracks_stock']);
        }
        $this->assertSame(1,AuditLog::count());
    }

    public function test_partial_update_cannot_disable_optional_while_quantity_is_zero(): void
    {
        $row=$this->add();
        try { $this->service->update($this->actor,$this->recipe->id,$row->id,['is_optional'=>false],'127.0.0.1','test'); $this->fail('Expected combined-state rejection'); }
        catch(RecipeIngredientException $e) { $this->assertSame(422,$e->getHttpStatus()); }
        $this->assertTrue($row->fresh()->is_optional); $this->assertSame(1,AuditLog::count());
        $updated=$this->service->update($this->actor,$this->recipe->id,$row->id,['quantity'=>0.5,'is_optional'=>false],'127.0.0.1','test');
        $this->assertSame('0.5000',$updated->quantity); $this->assertFalse($updated->is_optional);
        try { $this->service->update($this->actor,$this->recipe->id,$row->id,['quantity'=>0],'127.0.0.1','test'); $this->fail('Expected combined-state rejection'); }
        catch(RecipeIngredientException $e) { $this->assertSame(422,$e->getHttpStatus()); }
        $this->assertSame('0.5000',$row->fresh()->quantity); $this->assertSame(2,AuditLog::count());
    }

    public function test_direct_service_rejects_invalid_quantity_without_writing(): void
    {
        foreach([['quantity'=>-1],['quantity'=>0,'is_optional'=>false],['quantity'=>0.00001,'is_optional'=>false]] as $data) {
            try { $this->add($data); $this->fail('Expected invalid quantity'); } catch(RecipeIngredientException $e) { $this->assertSame(422,$e->getHttpStatus()); }
            $this->assertSame(0,RecipeIngredient::count()); $this->assertSame(0,AuditLog::count());
        }
    }

    public function test_free_quantity_is_cookable_and_never_requires_unit_conversion_even_when_including_optional(): void
    {
        $service=new RecipeAvailabilityService($this->createMock(RecipeAvailabilityRepository::class));
        foreach([false,true] as $include) {
            $result=$this->invoke($service,'compute',[$this->loaded([$this->freeRow()]),[$this->ingredient->id=>[999=>3]],[],collect(),$include,1.5]);
            $this->assertTrue($result['can_cook']); $this->assertSame(1.5,$result['max_possible_servings']); $this->assertSame([],$result['ingredients']); $this->assertSame([],$result['warnings']); $this->assertSame(0,$result['missing_ingredients_count']);
        }
        $result=$this->invoke($service,'compute',[$this->loaded([$this->freeRow(['quantity'=>2])]),[],[],collect(),true,1.0]);
        $this->assertFalse($result['can_cook']); $this->assertCount(1,$result['ingredients']);
    }

    public function test_free_quantity_skips_cost_lookup_but_does_not_claim_complete_zero_cost(): void
    {
        $repo=$this->createMock(RecipeCostRepository::class); $repo->method('loadIngredientsForCost')->willReturn($this->loaded([$this->freeRow()]));
        $repo->expects($this->never())->method('cheapestPriceForIngredient'); $repo->expects($this->never())->method('findConversion');
        $result=$this->invoke(new RecipeCostService($repo),'calculate',[$this->recipe,null]);
        $this->assertSame('partial',$result['calculation_status']); $this->assertSame([],$result['ingredients']); $this->assertSame(1,$result['unquantified_ingredients_count']);
    }

    public function test_free_quantity_skips_nutrition_conversion_but_keeps_estimate_partial(): void
    {
        $repo=$this->createMock(RecipeNutritionRepository::class); $g=new UnitMeasure(['code'=>'g']); $g->id=99; $repo->method('gramsUnit')->willReturn($g);
        $repo->expects($this->never())->method('findConversionToGrams');
        $result=$this->invoke(new RecipeNutritionService($repo),'calculate',[$this->loaded([$this->freeRow()])]);
        $this->assertSame('partial',$result['calculation_status']);
    }

    public function test_positive_optional_quantity_remains_numeric_and_participates_in_cost_and_nutrition(): void
    {
        $row=$this->freeRow(['quantity'=>2,'specific_product_id'=>4]);
        $this->assertFalse($row->isUnquantified()); $this->assertNull($row->quantityLabel()); $this->assertFalse($row->tracksStock());
        $cost=$this->createMock(RecipeCostRepository::class); $cost->method('loadIngredientsForCost')->willReturn($this->loaded([$row]));
        $cost->expects($this->once())->method('stockPriceForProduct')->with(8,4)->willReturn(['unit_id'=>$this->unit->id,'price_per_unit'=>3]);
        $cost->method('findConversion')->willReturn(1.0);
        $result=$this->invoke(new RecipeCostService($cost),'calculate',[$this->recipe,8]);
        $this->assertSame(6.0,$result['total_cost']); $this->assertSame('complete',$result['calculation_status']); $this->assertCount(1,$result['ingredients']);
        $this->ingredient->setRelation('nutrients',collect([(object)['code'=>'calories','pivot'=>(object)['status'=>'active','amount_per_100g'=>100]]]));
        $nutrition=$this->createMock(RecipeNutritionRepository::class); $nutrition->method('gramsUnit')->willReturn($this->unit); $nutrition->expects($this->never())->method('findConversionToGrams');
        $result=$this->invoke(new RecipeNutritionService($nutrition),'calculate',[$this->loaded([$row,$this->freeRow(['ingredient_id'=>99])])]);
        $this->assertSame(2.0,$result['calories_total']); $this->assertSame('partial',$result['calculation_status']);
    }

    public function test_plan_ignores_free_quantity_rounds_only_purchase_pieces_and_persists_rounded_fallback(): void
    {
        $this->unit->code='unit';
        $recipe=$this->loaded([$this->freeRow(),$this->freeRow(['ingredient_id'=>2,'quantity'=>0.5,'is_optional'=>false])]);
        $plan=(object)['items'=>collect([(object)['id'=>1,'recipe'=>$recipe,'servings_total'=>1]])];
        $repo=$this->createMock(\App\Repositories\ShoppingListPreview\ShoppingListPreviewRepository::class);
        $repo->expects($this->once())->method('stockItemsForIngredient')->willReturn([['id'=>1,'unit_id'=>$this->unit->id,'quantity'=>0.25]]);
        $repo->method('conversionFactor')->willReturn(1.0); $repo->method('resolveProductForIngredient')->willReturn(null);
        $service=$this->bare(\App\Services\ShoppingListPreview\ShoppingListPreviewService::class,['repo'=>$repo]);
        $requirements=$this->invoke($service,'requirements',[$plan]); $this->assertCount(1,$requirements); $this->assertSame(0.5,$requirements[0]['required_quantity']);
        $missing=$this->invoke($service,'subtractStock',[8,$requirements]); $this->assertSame(0.25,$missing[0]['missing_quantity']);
        $purchases=$this->invoke($service,'resolvePurchasableProducts',[8,$missing]); $this->assertSame(1.0,$purchases[0]['purchase_quantity']); $this->assertSame(0.25,$purchases[0]['missing_quantity']);
        $list=new \App\ShoppingList; $list->id=1;
        (new \App\Repositories\ShoppingListPreview\ShoppingListPreviewRepository)->replaceItems($list,$purchases);
        $this->assertSame('1.0000',\App\ShoppingListItem::first()->quantity);
        foreach(['g','ml','pinch','kg'] as $code) $this->assertSame(0.25,\App\Services\ShoppingLists\PurchaseQuantity::withoutPackaging(0.25,$code));
    }

    public function test_recipe_generation_omits_free_quantity_and_buys_one_piece_for_fractional_missing_stock(): void
    {
        $this->unit->code='unit'; $recipe=$this->loaded([$this->freeRow(),$this->freeRow(['ingredient_id'=>2,'quantity'=>0.5,'is_optional'=>false])]);
        $groups=$this->createMock(\App\Repositories\FamilyGroup\FamilyGroupRepository::class);
        $groups->method('findOrFailForUser')->willReturn(new \App\FamilyGroup);
        $availability=$this->createMock(RecipeAvailabilityRepository::class); $availability->method('loadRecipeIngredients')->willReturn($recipe); $availability->method('stockByIngredient')->willReturn([2=>[$this->unit->id=>0.25]]); $availability->method('findConversionFactor')->willReturn(1.0);
        $list=new \App\ShoppingList; $list->id=1; $list->wasRecentlyCreated=true;
        $lists=$this->createMock(\App\Repositories\ShoppingLists\ShoppingListRepository::class); $lists->method('create')->willReturn($list); $lists->method('findInGroup')->willReturn($list);
        $items=$this->createMock(\App\Repositories\ShoppingListItems\ShoppingListItemRepository::class); $items->method('duplicateExists')->willReturn(false);
        $items->expects($this->once())->method('create')->willReturnCallback(function($data){$this->assertSame(1.0,$data['quantity']);$this->assertSame(2,$data['ingredient_id']);$item=new \App\ShoppingListItem($data);$item->id=1;return $item;});
        $prices=$this->createMock(\App\Services\RecipeShoppingList\RecipePriceEstimator::class); $prices->expects($this->never())->method('resolve');
        $totals=$this->createMock(\App\Services\ShoppingLists\ShoppingListTotalService::class); $totals->expects($this->once())->method('recalculate');
        $service=new \App\Services\RecipeShoppingList\RecipeShoppingListService($groups,$availability,$lists,$items,$prices,$totals,$this->createMock(\App\Services\ShoppingLists\ShoppingListGenerationGuard::class));
        $result=$service->generate($this->actor,8,$recipe->id,[],'127.0.0.1','test');
        $this->assertSame(1,$result['items_added']); $this->assertSame([],$result['warnings']); $this->assertSame(0.25,$result['priced_items'][0]['requested_quantity']); $this->assertSame(1.0,$result['priced_items'][0]['purchase_quantity']);
    }

    public function test_recipe_and_plan_cooking_skip_free_rows_and_preserve_half_piece_consumption(): void
    {
        $recipe=$this->loaded([$this->freeRow(),$this->freeRow(['quantity'=>3]),$this->freeRow(['ingredient_id'=>2,'quantity'=>0.5,'is_optional'=>false])]);
        $repo=$this->createMock(\App\Repositories\RecipeFavoritesCooked\RecipeFavoritesCookedRepository::class);
        $repo->method('loadRecipeWithIngredients')->willReturn($recipe);
        $repo->expects($this->exactly(2))->method('stockItemsForIngredient')->with(8,2)->willReturn([(object)['id'=>1,'product_id'=>5,'unit_id'=>$this->unit->id,'quantity'=>1]]);
        $repo->method('findConversionFactor')->willReturn(1.0); $repo->expects($this->once())->method('deductStockItem')->with(1,0.5)->willReturn(true);
        $repo->expects($this->once())->method('createStockMovement')->willReturnCallback(function($data){$this->assertSame(0.5,$data['quantity']);return new \App\StockMovement($data);});
        $repo->expects($this->once())->method('markCookLogDiscounted')->with(1);
        $direct=new \App\Services\RecipeFavoritesCooked\RecipeFavoritesCookedService($repo,$this->createMock(RecipeAvailabilityService::class));
        $this->assertSame(1,$this->invoke($direct,'deductStock',[1,$recipe,1.0,8,19,$recipe->id]));
        $plan=$this->bare(\App\Services\MealPlanItemStatus\MealPlanItemStatusService::class,['cookedRepo'=>$repo]);
        $deductions=$this->invoke($plan,'planDeductions',[8,$recipe,1.0]); $this->assertCount(1,$deductions); $this->assertSame(0.5,$deductions[0]['deduct']);
    }

    public function test_candidate_repository_cannot_bypass_zero_optional_invariant_and_rolls_back_recipe(): void
    {
        $schema=$this->capsule->getConnection()->getSchemaBuilder();
        $schema->table('recipes',function(Blueprint $t){foreach(['nombre','description','descripcion','tiempo','img','video','porcion','source_url','source_site','source_type','status'] as $name)$t->text($name)->nullable();foreach(['calorias','is_public','is_verified','prep_time_minutes','cook_time_minutes'] as $name)$t->integer($name)->nullable();});
        $schema->create('imported_recipe_candidates',function(Blueprint $t){$t->increments('id');foreach(['raw_title','raw_description','raw_image_url','source_url','source_site','raw_steps_json','raw_ingredients_json','parsed_recipe_json','status','reviewed_at'] as $name)$t->text($name)->nullable();$t->integer('created_recipe_id')->nullable();$t->integer('reviewed_by')->nullable();$t->timestamps();});
        $schema->create('recipe_review_logs',function(Blueprint $t){$t->increments('id');$t->integer('recipe_id');$t->integer('imported_recipe_candidate_id');$t->integer('reviewed_by');$t->string('action');$t->text('comments')->nullable();$t->timestamp('created_at');});
        $repo=new \App\Repositories\RecipeImportCandidates\RecipeImportCandidatesRepository;
        foreach([['quantity'=>0,'is_optional'=>false],['quantity'=>-1,'is_optional'=>true],['quantity'=>null,'is_optional'=>true]] as $bad) {
            $candidate=\App\ImportedRecipeCandidate::create(['raw_title'=>'Candidata','raw_steps_json'=>[],'status'=>'approved','parsed_recipe_json'=>['servings'=>1,'ingredient_mappings'=>[$this->payload($bad)]]]);
            try{$repo->createRecipe($candidate,[],19);$this->fail('Expected import quantity rejection');}
            catch(\App\Exceptions\RecipeImportCandidates\RecipeImportCandidatesException $e){$this->assertSame(422,$e->getHttpStatus());}
            $this->assertSame(1,Recipe::count());$this->assertSame(0,RecipeIngredient::count());$this->assertSame('approved',$candidate->fresh()->status);
        }
        $candidate=\App\ImportedRecipeCandidate::create(['raw_title'=>'Candidata libre','raw_steps_json'=>[],'status'=>'approved','parsed_recipe_json'=>['servings'=>1,'ingredient_mappings'=>[$this->payload()]]]);
        $recipe=$repo->createRecipe($candidate,[],19);$row=RecipeIngredient::where('recipe_id',$recipe->id)->first();
        $this->assertSame('0.0000',$row->quantity);$this->assertTrue($row->is_optional);$this->assertSame('sal a gusto',$row->notes);$this->assertSame('recipe_created',$candidate->fresh()->status);
    }

    public function test_free_ingredient_does_not_recommend_consuming_expiring_stock(): void
    {
        $actor=$this->getMockBuilder(User::class)->onlyMethods(['hasPermission'])->getMock();$actor->id=19;$actor->method('hasPermission')->willReturn(false);
        $repo=$this->createMock(\App\Repositories\RecipeSuggestions\RecipeSuggestionsRepository::class);
        $repo->method('findFamilyGroup')->willReturn(new \App\FamilyGroup);$repo->method('isMember')->willReturn(true);$repo->method('expiringIngredientIds')->willReturn([$this->ingredient->id]);
        $repo->method('candidateRecipes')->willReturn(collect([$this->loaded([$this->freeRow()])]));
        $availability=$this->createMock(RecipeAvailabilityService::class);$availability->method('availabilityBatch')->willReturn([]);
        $service=new \App\Services\RecipeSuggestions\RecipeSuggestionsService($repo,$availability);
        $result=$service->byExpiringStock($actor,8,7,1,20);$this->assertSame(0,$result['meta']['total']);$this->assertCount(0,$result['data']);
    }

    public function test_substitution_rows_exclude_unknown_quantity_but_keep_positive_optional(): void
    {
        $schema=$this->capsule->getConnection()->getSchemaBuilder();$schema->table('unit_measures',function(Blueprint $t){$t->string('symbol')->nullable();});
        $first=$this->add();$second=Ingredient::create(['name'=>'pimienta','status'=>'active']);
        $row=RecipeIngredient::create(['recipe_id'=>$this->recipe->id,'ingredient_id'=>$second->id,'unit_id'=>$this->unit->id,'quantity'=>0.5,'is_optional'=>true,'sort_order'=>1]);
        $rows=(new \App\Repositories\RecipeSubstitutions\RecipeSubstitutionsRepository)->recipeIngredients($this->recipe->id);
        $this->assertCount(1,$rows);$this->assertSame($row->id,$rows[0]->recipe_ingredient_id);$this->assertEquals(0.5,$rows[0]->quantity);
    }
}
