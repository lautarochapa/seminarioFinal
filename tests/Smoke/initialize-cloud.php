<?php

// Runs against a newly created disposable local database, never the app database.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

function checkCloudSetup($condition, $message)
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$db = config('database.connections.pgsql');
checkCloudSetup(in_array($db['host'], ['localhost', '127.0.0.1'], true) && empty($db['url']), 'Solo se permite PostgreSQL local sin DATABASE_URL.');
$admin = new PDO(
    'pgsql:host='.$db['host'].';port='.$db['port'].';dbname=postgres',
    $db['username'],
    $db['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$database = 'cccontrol_cloud_smoke_'.bin2hex(random_bytes(6));
$admin->exec('CREATE DATABASE "'.$database.'"');
$exitCode = 0;

try {
    config([
        'database.default' => 'pgsql',
        'database.connections.pgsql.database' => $database,
        'database.connections.pgsql.url' => null,
        'session.driver' => 'array',
        'cache.default' => 'array',
    ]);
    Illuminate\Support\Facades\DB::purge('pgsql');
    $app['env'] = 'production';

    checkCloudSetup($kernel->call('app:initialize-database', ['--plan' => true]) === 0, 'Fallo plan.');
    checkCloudSetup(! Illuminate\Support\Facades\Schema::hasTable('migrations'), 'Plan no debe crear tablas.');
    checkCloudSetup($kernel->call('app:initialize-database') === 1, 'Produccion exige --force.');
    checkCloudSetup(! Illuminate\Support\Facades\Schema::hasTable('migrations'), 'Rechazo no debe crear tablas.');

    $options = ['--force' => true, '--seed-catalogs' => true];
    checkCloudSetup($kernel->call('app:initialize-database', $options) === 0, 'Fallo inicializacion.');
    checkCloudSetup(Illuminate\Support\Facades\DB::table('users')->count() === 0, 'No crear usuarios demo online.');
    checkCloudSetup(Illuminate\Support\Facades\DB::table('roles')->count() === 8, 'Faltan roles.');
    checkCloudSetup(Illuminate\Support\Facades\DB::table('permissions')->where('code', 'web.user.onboarding')->exists(), 'Falta permiso de onboarding.');
    foreach (['objectives', 'unit_measures', 'ingredient_categories', 'scraping_sources'] as $table) {
        checkCloudSetup(Illuminate\Support\Facades\DB::table($table)->count() > 0, 'Catalogo vacio: '.$table);
    }

    $before = [];
    foreach (['migrations', 'roles', 'permissions', 'role_permissions', 'ingredient_categories'] as $table) {
        $before[$table] = Illuminate\Support\Facades\DB::table($table)->count();
    }
    checkCloudSetup($kernel->call('app:initialize-database', $options) === 0, 'Fallo segunda ejecucion.');
    foreach ($before as $table => $count) {
        checkCloudSetup(Illuminate\Support\Facades\DB::table($table)->count() === $count, 'Duplicados en '.$table);
    }

    // Exercise the modern schema without relying on legacy recipe migrations.
    Illuminate\Support\Facades\DB::beginTransaction();
    try {
        $user = factory(App\User::class)->create();
        $group = App\FamilyGroup::create(['name' => 'Hogar prueba', 'owner_user_id' => $user->id, 'status' => 'active']);
        App\FamilyGroupMember::create([
            'family_group_id' => $group->id, 'user_id' => $user->id,
            'role_in_group' => 'owner', 'status' => 'active',
        ]);
        $onboarding = app(App\Services\Onboarding\OnboardingStatusService::class);
        $newStatus = $onboarding->forUser($user->id);
        checkCloudSetup($newStatus['steps']['basic_profile']['missing'] === ['height_cm'], 'El peso no puede ser obligatorio.');
        App\UserProfile::create(['user_id' => $user->id, 'height_cm' => 170, 'meals_per_day' => 3]);
        App\UserObjective::create([
            'user_id' => $user->id,
            'objective_id' => App\Objective::where('status', 'active')->firstOrFail()->id,
            'is_active' => true,
        ]);
        checkCloudSetup($onboarding->isComplete($user->id), 'El perfil sin peso debe permitir completar la configuracion inicial.');
        $user->assignDefaultRole();
        auth()->login($user);
        request()->setUserResolver(function () use ($user) { return $user; });
        $controller = app(App\Http\Controllers\UserWebScreenController::class);
        foreach (['dashboard', 'onboarding', 'stock', 'planning', 'shopping-list', 'recipe-search', 'recipe-favorites', 'recipe-suggestions', 'shopping-session', 'purchases', 'budget'] as $screen) {
            $view = $controller->index($screen);
            checkCloudSetup($view instanceof Illuminate\View\View, 'La pantalla no debe redirigir: '.$screen);
            view()->share('errors', new Illuminate\Support\ViewErrorBag());
            $html = $view->render();
            checkCloudSetup(strpos($html, 'href="#" class="btn-main" data-screen-primary-action') === false, 'Accion sin destino: '.$screen);
            checkCloudSetup(strpos($html, '/web/budget') !== false, 'Presupuesto ausente del menu: '.$screen);
        }
        auth()->logout();
        request()->setUserResolver(function () { return null; });
        $summary = app(App\Services\UserHome\UserHomeSummaryService::class)->summary($user, $group->id);
        checkCloudSetup($summary['recipes']['available'] === 0, 'El dashboard de un hogar nuevo debe cargar.');
        $recipe = app(App\Services\Recipes\RecipeService::class)->create($user, [
            'name' => 'Receta de prueba', 'servings' => 2,
        ], '127.0.0.1', 'Cloud smoke');
        checkCloudSetup($recipe->fresh()->created_at !== null, 'Falta fecha de creacion de recetas.');
        $recipe->update(['name' => 'Receta actualizada']);
        checkCloudSetup($recipe->fresh()->updated_at !== null, 'Falta fecha de actualizacion de recetas.');
        $timestamps = $recipe->fresh()->only(['created_at', 'updated_at']);
        (new EnsureRecipeTimestamps())->up();
        checkCloudSetup($recipe->fresh()->only(['created_at', 'updated_at']) == $timestamps, 'La correccion no debe alterar fechas existentes.');
        $unitId = Illuminate\Support\Facades\DB::table('unit_measures')->where('code', 'g')->value('id');
        $manual = app(App\Services\ManualProductStock\ManualProductStockService::class)->create($group->id, $user, [
            'product' => ['name' => 'Arroz prueba', 'unit_id' => $unitId],
            'stock' => ['quantity' => 500, 'unit_id' => $unitId],
        ], '127.0.0.1', 'Cloud smoke');
        checkCloudSetup($manual['status'] === 201, 'No se pudo cargar producto manual sin marca.');
        checkCloudSetup($manual['product']->brand_id === null, 'Una marca desconocida debe ser nula, no un ID inexistente.');
        checkCloudSetup((float) $manual['stock_item']->quantity === 500.0, 'Cantidad de stock incorrecta.');

        $pendingList = App\ShoppingList::create([
            'family_group_id' => $group->id, 'created_by' => $user->id,
            'source_type' => 'manual', 'status' => 'active',
        ]);
        $itemService = app(App\Services\ShoppingListItems\ShoppingListItemService::class);
        $pendingItem = $itemService->create($user, $group->id, $pendingList->id, [
            'product_id' => $manual['product']->id, 'quantity' => 10, 'unit_id' => $unitId,
        ], '127.0.0.1', 'Cloud smoke');
        $pendingItem = $itemService->update($user, $group->id, $pendingList->id, $pendingItem->id, [
            'actual_price' => 3, 'status' => 'purchased',
        ], '127.0.0.1', 'Cloud smoke');
        checkCloudSetup((float) $pendingItem->actual_price === 3.0, 'Una lista debe admitir el producto manual del hogar.');
        $otherGroup = App\FamilyGroup::create(['name' => 'Otro hogar', 'owner_user_id' => $user->id, 'status' => 'active']);
        $foreignProduct = $manual['product']->replicate();
        $foreignProduct->family_group_id = $otherGroup->id;
        $foreignProduct->normalized_name .= '-other';
        $foreignProduct->codigo .= '-other';
        $foreignProduct->save();
        try {
            $itemService->update($user, $group->id, $pendingList->id, $pendingItem->id, [
                'product_id' => $foreignProduct->id,
            ], '127.0.0.1', 'Cloud smoke');
            throw new RuntimeException('Se acepto un producto privado de otro hogar.');
        } catch (App\Exceptions\FamilyGroup\FamilyGroupException $expected) {
            checkCloudSetup($pendingItem->fresh()->product_id === $manual['product']->id, 'Se modifico el item con un producto ajeno.');
        }
        $productRepo = app(App\Repositories\ShoppingListItems\ShoppingListItemRepository::class);
        $foreignProduct->update(['status' => 'active']);
        checkCloudSetup(!$productRepo->activeProductExists($foreignProduct->id, $group->id), 'Un producto ajeno sigue siendo privado aunque este activo.');
        $foreignProduct->update(['family_group_id' => $group->id, 'is_active' => false]);
        checkCloudSetup(!$productRepo->activeProductExists($foreignProduct->id, $group->id), 'No aceptar productos desactivados.');

        $list = App\ShoppingList::create([
            'family_group_id' => $group->id, 'created_by' => $user->id,
            'source_type' => 'manual', 'status' => 'in_progress',
        ]);
        $item = App\ShoppingListItem::create([
            'shopping_list_id' => $list->id, 'free_text_name' => 'Tomate prueba',
            'unit_id' => $unitId, 'quantity' => 2, 'estimated_price' => 1800,
            'actual_price' => 1600, 'status' => 'purchased',
        ]);
        $completion = app(App\Services\ShoppingListCompletion\ShoppingListCompletionService::class);
        $payload = ['items' => [[
            'shopping_list_item_id' => $item->id, 'add_to_stock' => true,
            'create_pending_product' => true,
        ]]];
        $result = $completion->complete($user, $group->id, $list->id, $payload, '127.0.0.1', 'Cloud smoke');
        checkCloudSetup($result['summary']['items_added_to_stock_count'] === 1, 'La compra debe agregar stock.');
        checkCloudSetup((float) $result['purchase']->actual_total === 3200.0, 'Total de compra incorrecto.');
        $purchasedStock = App\StockItem::findOrFail($result['purchase']->items->first()->created_stock_item_id);
        checkCloudSetup((float) $purchasedStock->estimated_purchase_price === 1600.0, 'Se perdio el precio al crear stock.');
        $resource = new App\Http\Resources\Api\V1\ShoppingLists\ShoppingListResource($result['list']);
        $data = json_decode($resource->toJson(), true);
        checkCloudSetup((float) $data['items'][0]['actual_price'] === 1600.0, 'El detalle de lista perdio el precio.');
        checkCloudSetup($data['items'][0]['stock_processing_state'] === 'processed', 'El detalle muestra pendientes ya procesados.');
        try {
            $completion->complete($user, $group->id, $list->id, $payload, '127.0.0.1', 'Cloud smoke');
            throw new RuntimeException('Se permitio confirmar dos veces la misma compra.');
        } catch (App\Exceptions\Purchases\PurchaseException $expected) {
            checkCloudSetup((float) $purchasedStock->fresh()->quantity === 2.0, 'La compra duplico stock.');
            checkCloudSetup(App\Purchase::where('shopping_list_id', $list->id)->count() === 1, 'La compra se duplico.');
        }
        $budget = app(App\Services\Budgets\BudgetService::class)->create($group->id, $user->id, [
            'year' => (int) date('Y'), 'month' => (int) date('n'), 'total_amount' => 10000, 'currency' => 'ARS',
        ], '127.0.0.1', 'Cloud smoke');
        $budgetSummary = app(App\Services\Budgets\BudgetSummaryService::class)->summary($group->id, $budget->id, $user->id);
        checkCloudSetup($budgetSummary['spent_amount'] === 3200.0, 'La compra no impacta en el presupuesto.');
        checkCloudSetup($budgetSummary['available_amount'] === 6800.0, 'Saldo de presupuesto incorrecto.');

        $otherUser = factory(App\User::class)->create();
        $recipesService = app(App\Services\Recipes\RecipeService::class);
        $privateRecipe = $recipesService->create($otherUser, ['name' => 'Receta privada ajena', 'servings' => 1], '127.0.0.1', 'Cloud smoke');
        $visibleRecipes = $recipesService->list($user, [])->pluck('id')->all();
        checkCloudSetup(in_array($recipe->id, $visibleRecipes, true), 'Se oculto la receta propia.');
        checkCloudSetup(!in_array($privateRecipe->id, $visibleRecipes, true), 'El listado expone recetas privadas de otro usuario.');
        checkCloudSetup($recipesService->list($user, ['owner_user_id' => $otherUser->id])->total() === 0, 'No permitir evadir visibilidad mediante filtros.');
        try {
            $recipesService->show($user, $privateRecipe->id);
            throw new RuntimeException('El detalle expone una receta privada ajena.');
        } catch (App\Exceptions\Recipes\RecipeException $expected) {
            checkCloudSetup($recipesService->show($user, $recipe->id)->id === $recipe->id, 'Se bloqueo el detalle propio.');
        }
        $privateRecipe->update(['is_public' => true]);
        checkCloudSetup($recipesService->show($user, $privateRecipe->id)->id === $privateRecipe->id, 'Se bloquearon recetas compartidas.');
        App\Budget::create(['family_group_id' => $otherGroup->id, 'year' => 2026, 'month' => 9, 'total_amount' => 12345, 'currency' => 'ARS', 'status' => 'active']);
        auth()->login($user);
        request()->setUserResolver(function () use ($user) { return $user; });
        $stats = $controller->index('budget')->getData()['stats'];
        checkCloudSetup($stats['budgets'] === 1 && $stats['family_groups'] === 1, 'Los contadores incluyen hogares ajenos.');
        $budgetResponse = app(App\Http\Controllers\Api\V1\Budgets\BudgetController::class)
            ->index(request(), $group->id)->getData(true);
        checkCloudSetup(count($budgetResponse['data']) === 1, 'El listado incluye presupuestos ajenos.');
        checkCloudSetup((float) $budgetResponse['data'][0]['used_amount'] === 3200.0, 'El listado omite el gasto real.');
        checkCloudSetup((float) $budgetResponse['data'][0]['available_amount'] === 6800.0, 'El listado omite el saldo disponible.');
        $movement = App\StockMovement::with('unit')->where('stock_item_id', $purchasedStock->id)->firstOrFail();
        $movementData = json_decode((new App\Http\Resources\Api\V1\StockMovements\StockMovementResource($movement))->toJson(), true);
        checkCloudSetup($movementData['unit']['id'] === $movement->unit_id, 'El movimiento omite su unidad.');
        checkCloudSetup($movementData['unit']['symbol'] === $movement->unit->symbol, 'Simbolo de unidad incorrecto.');
        require_once __DIR__.'/shopping-session-regressions.php';
        checkShoppingSessionRegressions($user, $group, $manual['product'], $unitId, $budget);
        require_once __DIR__.'/product-images.php';
        checkProductImages($manual['product'], $group, $user->id, $unitId);
        auth()->logout();
        request()->setUserResolver(function () { return null; });
    } finally {
        Illuminate\Support\Facades\DB::rollBack();
    }

    view()->share('errors', new Illuminate\Support\ViewErrorBag());
    $login = view('auth.login')->render();
    checkCloudSetup(strpos($login, 'data-demo-password') === false, 'Login publico expone credenciales demo.');
    checkCloudSetup(strpos($login, 'superadmin@cccontrol.test') === false, 'Login publico expone usuario admin demo.');

    $app['env'] = 'local';
    require_once database_path('migrations/2026_06_15_000018_seed_demo_role_users.php');
    (new SeedDemoRoleUsers())->up();
    checkCloudSetup(Illuminate\Support\Facades\DB::table('users')->count() === 8, 'Se perdieron usuarios demo locales.');
    checkCloudSetup(strpos(view('auth.login')->render(), 'data-demo-password') !== false, 'Login local perdio atajos demo.');

    $passwords = Illuminate\Support\Facades\DB::table('users')->orderBy('id')->pluck('password')->all();
    $app['env'] = 'production';
    (new SeedDemoRoleUsers())->up();
    checkCloudSetup(Illuminate\Support\Facades\DB::table('users')->orderBy('id')->pluck('password')->all() === $passwords, 'No resetear cuentas existentes en produccion.');
    echo 'OK: inicializacion, catalogos, permisos, perfil sin peso, navegacion, dashboard, recetas, stock, compra sin duplicados, presupuesto y aislamiento demo.'.PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    $exitCode = 1;
} finally {
    Illuminate\Support\Facades\DB::purge('pgsql');
    $admin->exec('DROP DATABASE "'.$database.'"');
}
exit($exitCode);
