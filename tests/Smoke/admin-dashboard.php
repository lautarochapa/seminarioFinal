<?php

// This smoke deliberately refuses the normal application bootstrap or production database.
$bootstrap = dirname(__DIR__, 2).'/.runtime/qa-admin/bootstrap.php';
if (!is_file($bootstrap)) { fwrite(STDERR, "Isolated qa-admin fixture missing.\n"); exit(2); }
$app = require $bootstrap;
$db = Illuminate\Support\Facades\DB::connection();
if (config('app.env') !== 'testing' || $db->getDriverName() !== 'sqlite'
    || realpath($db->getDatabaseName()) !== realpath(dirname($bootstrap).'/qa.sqlite')
    || array_keys(config('database.connections')) !== ['sqlite']) {
    throw new RuntimeException('Only the isolated qa-admin SQLite fixture is allowed.');
}
$checks = [];
$check = function ($ok, $name) use (&$checks) { $checks[] = ['name' => $name, 'pass' => (bool) $ok]; };
$controller = new App\Http\Controllers\AdminWebScreenController;
$reflection = new ReflectionMethod($controller, 'screens'); $reflection->setAccessible(true);
$screens = $reflection->invoke($controller);
$service = new App\Services\Admin\AdminDashboardService;
$context = function ($user) use ($app) {
    $request = Illuminate\Http\Request::create('/admin-web', 'GET');
    $request->setUserResolver(function () use ($user) { return $user; });
    $app->instance('request', $request);
    Illuminate\Support\Facades\Auth::guard()->setUser($user);
};
$capture = function ($user) use ($service, $screens, $db, $context, $check) {
    $context($user); $db->flushQueryLog(); $db->enableQueryLog();
    $dashboard = $service->build($user, $screens);
    $queries = $db->getQueryLog(); $db->disableQueryLog();
    $cards = [];
    foreach ($dashboard['sections'] as $section) {
        $check(count($section['cards']) > 0, $user->username.' has no empty sections');
        foreach ($section['cards'] as $card) {
            $cards[$card['key']] = $card;
            $screen = substr($card['url'], strlen('/admin-web/'));
            $permission = $screens[$screen]['permission'] ?? 'web.admin.'.$screen;
            $check(isset($screens[$screen]) && $user->hasPermission($permission), $user->username.' can open '.$card['key']);
            $check(is_int($card['value']) && $card['value'] >= 0, $card['key'].' is an explicit non-negative integer');
        }
    }
    foreach ($queries as $query) $check(stripos(ltrim($query['query']), 'select') === 0, 'dashboard query is read-only');
    return ['dashboard' => $dashboard, 'cards' => $cards, 'queries' => $queries];
};
$fingerprint = function () use ($db) {
    $hashes = [];
    foreach ($db->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name") as $table) {
        $rows = array_map(function ($row) { return json_encode($row); }, $db->table($table->name)->get()->all());
        sort($rows); $hashes[$table->name] = hash('sha256', implode("\n", $rows));
    }
    return $hashes;
};
$before = $fingerprint(); $db->beginTransaction();
$summary = [];
try {
    $users = []; $baseline = [];
    foreach (['catalog_admin', 'recipe_admin', 'super_admin'] as $role) {
        $users[$role] = App\User::where('email', 'local.'.$role.'@example.invalid')->firstOrFail();
        $baseline[$role] = $capture($users[$role]);
    }
    $actor = $users['super_admin'];
    $baseProduct = App\Product::firstOrFail();
    $product = null;
    foreach ([['active', true, false], ['inactive', true, false], ['pending_review', true, false], ['active', true, true]] as $i => [$status, $active, $deleted]) {
        $copy = $baseProduct->replicate(); $copy->name = $copy->nombre = 'Dashboard QA '.$i;
        $copy->normalized_name = 'dashboard qa '.$i; $copy->codigo = 'DASH-QA-'.$i;
        $copy->status = $status; $copy->is_active = $active; $copy->save();
        if ($deleted) $copy->delete();
        if ($i === 0) $product = $copy;
    }
    $baseIngredient = App\Ingredient::firstOrFail();
    foreach (['active', 'inactive', 'deleted'] as $i => $status) {
        $copy = $baseIngredient->replicate(); $copy->name = 'Dashboard ingrediente '.$i; $copy->normalized_name = 'dashboard ingrediente '.$i;
        $copy->status = $status === 'deleted' ? 'active' : $status; $copy->save(); if ($status === 'deleted') $copy->delete();
    }
    $city = App\City::create(['name' => 'Dashboard QA', 'province' => 'QA', 'country' => 'Argentina', 'status' => 'active']);
    $chain = App\SupermarketChain::create(['name' => 'Dashboard QA', 'code' => 'dashboard-qa', 'status' => 'active']);
    foreach ([1, 2] as $i) {
        $branch = App\SupermarketBranch::create(['name' => 'Dashboard QA '.$i, 'supermarket_chain_id' => $chain->id,
            'city_id' => $city->id, 'address' => 'Fixture', 'status' => 'active']);
        $mapping = App\SupermarketProduct::create(['product_id' => $product->id, 'supermarket_chain_id' => $chain->id,
            'supermarket_branch_id' => $branch->id, 'status' => 'active']);
        foreach (range(1, $i) as $j) App\SupermarketProductPrice::create(['supermarket_product_id' => $mapping->id,
            'price' => 100 + $j, 'currency' => 'ARS', 'status' => 'active', 'scraped_at' => now()]);
    }
    foreach ([[true, 'active', false], [true, 'inactive', false], [false, 'active', false], [true, 'active', true]] as $i => [$official, $status, $deleted]) {
        $recipe = App\Recipe::create(['name' => 'Dashboard receta '.$i, 'nombre' => 'Dashboard receta '.$i, 'normalized_name' => 'dashboard receta '.$i,
            'descripcion' => '', 'tiempo' => '', 'img' => '', 'video' => '', 'porcion' => '', 'calorias' => 0,
            'source_type' => $official ? 'official' : 'user', 'owner_user_id' => $actor->id,
            'is_official' => $official, 'is_public' => $official, 'status' => $status]);
        if ($deleted) $recipe->delete();
    }
    foreach (['pending', 'parsed', 'approved', 'recipe_created', 'rejected', 'failed'] as $status) {
        App\ImportedRecipeCandidate::create(['source_url' => 'https://example.invalid/'.$status, 'source_site' => 'fixture', 'raw_title' => 'QA', 'status' => $status]);
    }
    $source = App\ScrapingSource::create(['code' => 'dashboard-fixture', 'name' => 'Dashboard QA', 'type' => 'web_scraper', 'is_active' => false, 'status' => 'inactive']);
    $jobs = [];
    foreach (['product_prices' => ['pending', 'running', 'cancel_requested', 'completed', 'failed', 'cancelled'],
        'recipe_scraping' => ['pending', 'completed', 'failed']] as $type => $statuses) {
        foreach ($statuses as $status) {
            $job = App\ScrapingJob::create(['source_id' => $source->id, 'job_type' => $type, 'requested_by' => $actor->id,
                'status' => $status]);
            $job->created_at = $job->updated_at = now()->subDays(3); $job->save();
            $jobs[$type][$status] = $job;
        }
        foreach (['open', 'resolved'] as $status) App\ScrapingAlert::create(['source_id' => $source->id,
            'scraping_job_id' => $jobs[$type]['failed']->id, 'alert_type' => 'fixture', 'message' => 'QA', 'severity' => 'warning', 'status' => $status]);
        foreach ([1, 2] as $i) App\ScrapingError::create(['source_id' => $source->id, 'scraping_job_id' => $jobs[$type]['failed']->id, 'error_type' => 'fixture', 'message' => 'QA '.$i]);
    }
    App\ScrapingAlert::create(['source_id' => $source->id, 'scraping_job_id' => null, 'alert_type' => 'fixture', 'message' => 'Unassigned QA', 'severity' => 'warning', 'status' => 'open']);
    foreach (['pending', 'matched', 'created', 'approved', 'rejected'] as $status) App\ScrapedProductCandidate::create([
        'scraping_job_id' => $jobs['product_prices']['completed']->id, 'source_id' => $source->id, 'raw_name' => 'QA '.$status, 'review_status' => $status]);
    foreach (['pending', 'approved', 'rejected', 'deleted'] as $status) {
        $item = App\ProductRequest::create(['requested_by_user_id' => $actor->id, 'name' => 'QA '.$status,
            'normalized_name' => 'qa '.$status, 'status' => $status === 'deleted' ? 'pending' : $status]);
        if ($status === 'deleted') $item->delete();
    }
    foreach (['open', 'resolved', 'rejected'] as $status) App\ProductReport::create(['user_id' => $actor->id, 'product_id' => $product->id,
        'report_type' => 'other', 'description' => 'QA', 'status' => $status]);
    foreach (['pending', 'queued', 'failed'] as $status) App\PriceRefreshRequest::create(['user_id' => $actor->id, 'product_id' => $product->id, 'status' => $status]);

    $expected = ['products' => 3, 'published_products' => 1, 'ingredients' => 2, 'cities' => 1, 'supermarkets' => 1,
        'branches' => 2, 'supermarket_products' => 2, 'prices' => 3, 'official_recipes' => 2, 'imported_recipes' => 6,
        'products_pending_review' => 3, 'product_requests_pending' => 1, 'product_reports_open' => 1,
        'price_refresh_pending' => 1, 'recipes_pending_completion' => 3, 'recipe_imports_failed' => 1,
        'product_jobs_active' => 3, 'product_jobs_total' => 6, 'product_alerts_open' => 1, 'product_errors_total' => 2,
        'recipe_jobs_active' => 1, 'recipe_jobs_total' => 3, 'recipe_alerts_open' => 1, 'recipe_errors_total' => 2];
    foreach ($users as $role => $user) {
        $result = $capture($user);
        foreach ($expected as $key => $increment) {
            if (!isset($result['cards'][$key])) continue;
            $check($result['cards'][$key]['value'] === $baseline[$role]['cards'][$key]['value'] + $increment, "$role $key has correct state/soft-delete/type count");
        }
        $sql = implode("\n", array_column($result['queries'], 'query'));
        $check(!preg_match('/from "(?:thesis_documents|demo_scenarios|promotions|payment_methods)"/i', $sql), "$role never queries retired modules");
        if ($role === 'recipe_admin') {
            $check(!preg_match('/from "(?:products|ingredients|brands|supermarket_chains|supermarket_branches|scraped_product_candidates|product_requests|product_reports|price_refresh_requests|users)"/i', $sql), 'recipe administrator does not count catalog or accounts');
            $check(!isset($result['cards']['product_jobs_active']) && !isset($result['cards']['product_alerts_open']), 'recipe administrator cannot see product scraping activity');
        }
        if ($role === 'catalog_admin') {
            $check(!preg_match('/from "(?:recipes|recipe_categories|recipe_tags|imported_recipe_candidates|users)"/i', $sql), 'catalog administrator does not count recipes or accounts');
            $check(!isset($result['cards']['recipe_jobs_active']), 'catalog administrator cannot see recipe scraping activity');
            $check(array_slice(array_keys($result['cards']), 0, 4) === ['products', 'ingredients', 'supermarkets', 'branches'], 'requested quantities come first');
        }
        $countQueries = array_filter($result['queries'], function ($q) { return stripos($q['query'], 'COUNT(') !== false; });
        $summary[$role] = ['sections' => array_column($result['dashboard']['sections'], 'key'), 'cards' => count($result['cards']), 'count_queries' => count($countQueries)];
        $context($user); $db->flushQueryLog(); $db->enableQueryLog();
        $data = $controller->index('dashboard')->getData();
        $queries = $db->getQueryLog(); $db->disableQueryLog();
        $check($data['stats'] === [] && count($data['dashboard']['sections']) === count($result['dashboard']['sections']), "$role controller bypasses global stats");
        foreach ($queries as $q) $check(stripos(ltrim($q['query']), 'select') === 0, 'controller dashboard has no writes or stale-job reconciliation');
    }
    $context($actor); $db->flushQueryLog(); $db->enableQueryLog();
    $data = $controller->index('brands')->getData(); $queries = $db->getQueryLog(); $db->disableQueryLog();
    $counts = array_values(array_filter($queries, function ($q) { return stripos($q['query'], 'count(') !== false; }));
    $check(array_keys($data['stats']) === ['brands'] && count($counts) === 1, 'other screens only query requested statistics');
    $check($data['dashboard'] === ['sections' => []], 'non-dashboard screen does not compute dashboard');
    $context($actor); $db->flushQueryLog(); $db->enableQueryLog();
    $empty = $service->build($actor, []); $queries = $db->getQueryLog(); $db->disableQueryLog();
    $check($empty === ['sections' => []] && count($queries) === 0, 'no destinations means no sections and no queries');
    $check($jobs['recipe_scraping']['pending']->fresh()->status === 'pending', 'old pending recipe job stays pending: read does not reconcile it');
} catch (Throwable $e) {
    $check(false, get_class($e).': '.$e->getMessage());
} finally {
    $db->disableQueryLog(); while ($db->transactionLevel() > 0) $db->rollBack();
}
$check($before === $fingerprint(), 'all SQLite tables unchanged after fixture rollback');
$failed = array_values(array_filter($checks, function ($c) { return !$c['pass']; }));
echo json_encode(['result' => $failed ? 'FAIL' : 'PASS', 'database' => 'isolated qa-admin/qa.sqlite',
    'checks' => count($checks), 'failures' => $failed, 'roles' => $summary, 'results' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($failed ? 1 : 0);
