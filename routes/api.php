<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(base_path('routes/api_contract.php'));

Route::prefix('v1')->middleware(['web', 'trace_id', 'api_token', 'auth'])->group(function () {
    Route::get('auth/consents', [\App\Http\Controllers\Api\V1\Consents\UserConsentController::class, 'show']);
    Route::patch('auth/consents', [\App\Http\Controllers\Api\V1\Consents\UserConsentController::class, 'update']);
    Route::get('users/me/consents', [\App\Http\Controllers\Api\V1\Consents\UserConsentController::class, 'show']);
    Route::patch('users/me/consents', [\App\Http\Controllers\Api\V1\Consents\UserConsentController::class, 'update']);
});

Route::prefix('v1')->middleware(['web', 'trace_id', 'api_token', 'auth'])->group(function () {
    Route::get('admin/units', [\App\Http\Controllers\Api\V1\Units\AdminUnitController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/units', [\App\Http\Controllers\Api\V1\Units\AdminUnitController::class, 'store'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/units/{id}/audit', [\App\Http\Controllers\Api\V1\Units\AdminUnitController::class, 'audit'])
        ->middleware('permission:audit.read');
    Route::patch('admin/units/{id}/restore', [\App\Http\Controllers\Api\V1\Units\AdminUnitController::class, 'restore'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/units/{id}', [\App\Http\Controllers\Api\V1\Units\AdminUnitController::class, 'show'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/units/{id}', [\App\Http\Controllers\Api\V1\Units\AdminUnitController::class, 'update'])
        ->middleware('permission:catalog.manage');
    Route::delete('admin/units/{id}', [\App\Http\Controllers\Api\V1\Units\AdminUnitController::class, 'destroy'])
        ->middleware('permission:catalog.manage');

    Route::get('admin/unit-conversions', [\App\Http\Controllers\Api\V1\Units\AdminUnitConversionController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/unit-conversions', [\App\Http\Controllers\Api\V1\Units\AdminUnitConversionController::class, 'store'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/unit-conversions/{id}/audit', [\App\Http\Controllers\Api\V1\Units\AdminUnitConversionController::class, 'audit'])
        ->middleware('permission:audit.read');
    Route::patch('admin/unit-conversions/{id}/restore', [\App\Http\Controllers\Api\V1\Units\AdminUnitConversionController::class, 'restore'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/unit-conversions/{id}', [\App\Http\Controllers\Api\V1\Units\AdminUnitConversionController::class, 'show'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/unit-conversions/{id}', [\App\Http\Controllers\Api\V1\Units\AdminUnitConversionController::class, 'update'])
        ->middleware('permission:catalog.manage');
    Route::delete('admin/unit-conversions/{id}', [\App\Http\Controllers\Api\V1\Units\AdminUnitConversionController::class, 'destroy'])
        ->middleware('permission:catalog.manage');

    Route::get('units', [\App\Http\Controllers\Api\V1\Units\UnitCatalogController::class, 'index']);

    Route::get('admin/brands', [\App\Http\Controllers\Api\V1\Brands\AdminBrandController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/brands', [\App\Http\Controllers\Api\V1\Brands\AdminBrandController::class, 'store'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/brands/{id}/restore', [\App\Http\Controllers\Api\V1\Brands\AdminBrandController::class, 'restore'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/brands/{id}', [\App\Http\Controllers\Api\V1\Brands\AdminBrandController::class, 'show'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/brands/{id}', [\App\Http\Controllers\Api\V1\Brands\AdminBrandController::class, 'update'])
        ->middleware('permission:catalog.manage');
    Route::delete('admin/brands/{id}', [\App\Http\Controllers\Api\V1\Brands\AdminBrandController::class, 'destroy'])
        ->middleware('permission:catalog.manage');

    Route::get('brands', [\App\Http\Controllers\Api\V1\Brands\BrandCatalogController::class, 'index']);

    Route::get('admin/product-categories', [\App\Http\Controllers\Api\V1\ProductCategories\ProductCategoryAdminController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/product-categories', [\App\Http\Controllers\Api\V1\ProductCategories\ProductCategoryAdminController::class, 'store'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/product-categories/{id}/restore', [\App\Http\Controllers\Api\V1\ProductCategories\ProductCategoryAdminController::class, 'restore'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/product-categories/{id}', [\App\Http\Controllers\Api\V1\ProductCategories\ProductCategoryAdminController::class, 'show'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/product-categories/{id}', [\App\Http\Controllers\Api\V1\ProductCategories\ProductCategoryAdminController::class, 'update'])
        ->middleware('permission:catalog.manage');
    Route::delete('admin/product-categories/{id}', [\App\Http\Controllers\Api\V1\ProductCategories\ProductCategoryAdminController::class, 'destroy'])
        ->middleware('permission:catalog.manage');

    Route::get('product-categories', [\App\Http\Controllers\Api\V1\ProductCategories\ProductCategoryCatalogController::class, 'index']);

    Route::get('admin/products', [\App\Http\Controllers\Api\V1\Products\AdminProductController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/products', [\App\Http\Controllers\Api\V1\Products\AdminProductController::class, 'store'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/products/{id}/restore', [\App\Http\Controllers\Api\V1\Products\AdminProductController::class, 'restore'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/products/{id}', [\App\Http\Controllers\Api\V1\Products\AdminProductController::class, 'show'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/products/{id}', [\App\Http\Controllers\Api\V1\Products\AdminProductController::class, 'update'])
        ->middleware('permission:catalog.manage');
    Route::delete('admin/products/{id}', [\App\Http\Controllers\Api\V1\Products\AdminProductController::class, 'destroy'])
        ->middleware('permission:catalog.manage');

    Route::get('products/barcode/{barcode}', [\App\Http\Controllers\Api\V1\Products\ProductCatalogController::class, 'findByBarcode']);
    Route::get('products/{id}/nutrition', [\App\Http\Controllers\Api\V1\Products\ProductCatalogController::class, 'nutrition']);
    Route::get('products/{id}/prices', [\App\Http\Controllers\Api\V1\Products\ProductCatalogController::class, 'prices']);
    Route::get('products/{id}/alternatives', [\App\Http\Controllers\Api\V1\Products\ProductCatalogController::class, 'alternatives']);
    Route::post('products/{id}/request-price-refresh', [\App\Http\Controllers\Api\V1\PriceRefreshRequests\PriceRefreshRequestController::class, 'store']);
    Route::post('products/{id}/reports', [\App\Http\Controllers\Api\V1\ProductReports\ProductReportController::class, 'store']);
    Route::get('products/{id}', [\App\Http\Controllers\Api\V1\Products\ProductCatalogController::class, 'show']);
    Route::get('products', [\App\Http\Controllers\Api\V1\Products\ProductCatalogController::class, 'index']);
    Route::get('users/me/price-refresh-requests', [\App\Http\Controllers\Api\V1\PriceRefreshRequests\PriceRefreshRequestController::class, 'mine']);

    // Reportes de productos - administración
    Route::get('admin/product-reports', [\App\Http\Controllers\Api\V1\ProductReports\AdminProductReportController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/product-reports/{id}/resolve', [\App\Http\Controllers\Api\V1\ProductReports\AdminProductReportController::class, 'resolve'])
        ->middleware('permission:catalog.manage');

    // Metodos de pago - administracion, catalogo y metodos del usuario
    Route::get('admin/payment-methods', [\App\Http\Controllers\Api\V1\PaymentMethods\AdminPaymentMethodController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/payment-methods', [\App\Http\Controllers\Api\V1\PaymentMethods\AdminPaymentMethodController::class, 'store'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/payment-methods/{id}/restore', [\App\Http\Controllers\Api\V1\PaymentMethods\AdminPaymentMethodController::class, 'restore'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/payment-methods/{id}', [\App\Http\Controllers\Api\V1\PaymentMethods\AdminPaymentMethodController::class, 'show'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/payment-methods/{id}', [\App\Http\Controllers\Api\V1\PaymentMethods\AdminPaymentMethodController::class, 'update'])
        ->middleware('permission:catalog.manage');
    Route::delete('admin/payment-methods/{id}', [\App\Http\Controllers\Api\V1\PaymentMethods\AdminPaymentMethodController::class, 'destroy'])
        ->middleware('permission:catalog.manage');

    Route::get('payment-methods', [\App\Http\Controllers\Api\V1\PaymentMethods\PaymentMethodCatalogController::class, 'index']);

    Route::get('users/me/payment-methods', [\App\Http\Controllers\Api\V1\PaymentMethods\UserPaymentMethodController::class, 'index']);
    Route::post('users/me/payment-methods', [\App\Http\Controllers\Api\V1\PaymentMethods\UserPaymentMethodController::class, 'store']);
    Route::delete('users/me/payment-methods/{id}', [\App\Http\Controllers\Api\V1\PaymentMethods\UserPaymentMethodController::class, 'destroy']);

    // Promociones de supermercado - administracion y catalogo por sucursal
    Route::get('admin/promotions', [\App\Http\Controllers\Api\V1\Promotions\AdminPromotionController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/promotions', [\App\Http\Controllers\Api\V1\Promotions\AdminPromotionController::class, 'store'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/promotions/{id}/restore', [\App\Http\Controllers\Api\V1\Promotions\AdminPromotionController::class, 'restore'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/promotions/{id}', [\App\Http\Controllers\Api\V1\Promotions\AdminPromotionController::class, 'show'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/promotions/{id}', [\App\Http\Controllers\Api\V1\Promotions\AdminPromotionController::class, 'update'])
        ->middleware('permission:catalog.manage');
    Route::delete('admin/promotions/{id}', [\App\Http\Controllers\Api\V1\Promotions\AdminPromotionController::class, 'destroy'])
        ->middleware('permission:catalog.manage');

    Route::get('supermarket-branches/{id}/promotions', [\App\Http\Controllers\Api\V1\Promotions\BranchPromotionController::class, 'index']);

    // Productos de supermercado - administracion y catalogo de precios
    Route::get('admin/supermarket-products', [\App\Http\Controllers\Api\V1\SupermarketProducts\AdminSupermarketProductController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/supermarket-products', [\App\Http\Controllers\Api\V1\SupermarketProducts\AdminSupermarketProductController::class, 'store'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/supermarket-products/{id}/restore', [\App\Http\Controllers\Api\V1\SupermarketProducts\AdminSupermarketProductController::class, 'restore'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/supermarket-products/{id}/prices', [\App\Http\Controllers\Api\V1\SupermarketPrices\AdminSupermarketPriceController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/supermarket-products/{id}/prices', [\App\Http\Controllers\Api\V1\SupermarketPrices\AdminSupermarketPriceController::class, 'store'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/supermarket-products/{id}', [\App\Http\Controllers\Api\V1\SupermarketProducts\AdminSupermarketProductController::class, 'show'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/supermarket-products/{id}', [\App\Http\Controllers\Api\V1\SupermarketProducts\AdminSupermarketProductController::class, 'update'])
        ->middleware('permission:catalog.manage');
    Route::delete('admin/supermarket-products/{id}', [\App\Http\Controllers\Api\V1\SupermarketProducts\AdminSupermarketProductController::class, 'destroy'])
        ->middleware('permission:catalog.manage');

    Route::get('supermarket-branches/{id}/products', [\App\Http\Controllers\Api\V1\SupermarketProducts\BranchProductController::class, 'index']);

    Route::get('products/{productId}/best-price', [\App\Http\Controllers\Api\V1\SupermarketProducts\ProductPriceController::class, 'bestPrice']);
    Route::get('products/{productId}/supermarket-prices', [\App\Http\Controllers\Api\V1\SupermarketProducts\ProductPriceController::class, 'supermarketPrices']);

    // Sucursales de supermercados - administracion y catalogo
    Route::get('admin/supermarket-branches', [\App\Http\Controllers\Api\V1\SupermarketBranches\AdminSupermarketBranchController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/supermarket-branches', [\App\Http\Controllers\Api\V1\SupermarketBranches\AdminSupermarketBranchController::class, 'store'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/supermarket-branches/{id}/restore', [\App\Http\Controllers\Api\V1\SupermarketBranches\AdminSupermarketBranchController::class, 'restore'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/supermarket-branches/{id}', [\App\Http\Controllers\Api\V1\SupermarketBranches\AdminSupermarketBranchController::class, 'show'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/supermarket-branches/{id}', [\App\Http\Controllers\Api\V1\SupermarketBranches\AdminSupermarketBranchController::class, 'update'])
        ->middleware('permission:catalog.manage');
    Route::delete('admin/supermarket-branches/{id}', [\App\Http\Controllers\Api\V1\SupermarketBranches\AdminSupermarketBranchController::class, 'destroy'])
        ->middleware('permission:catalog.manage');

    Route::get('supermarket-branches/nearby', [\App\Http\Controllers\Api\V1\SupermarketBranches\SupermarketBranchCatalogController::class, 'nearby']);
    Route::get('supermarket-branches/{id}', [\App\Http\Controllers\Api\V1\SupermarketBranches\SupermarketBranchCatalogController::class, 'show']);
    Route::get('supermarket-branches', [\App\Http\Controllers\Api\V1\SupermarketBranches\SupermarketBranchCatalogController::class, 'index']);

    // Cadenas de supermercados - administración y catálogo
    Route::get('admin/supermarket-chains', [\App\Http\Controllers\Api\V1\Supermarkets\AdminSupermarketChainController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/supermarket-chains', [\App\Http\Controllers\Api\V1\Supermarkets\AdminSupermarketChainController::class, 'store'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/supermarket-chains/{id}/restore', [\App\Http\Controllers\Api\V1\Supermarkets\AdminSupermarketChainController::class, 'restore'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/supermarket-chains/{id}', [\App\Http\Controllers\Api\V1\Supermarkets\AdminSupermarketChainController::class, 'show'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/supermarket-chains/{id}', [\App\Http\Controllers\Api\V1\Supermarkets\AdminSupermarketChainController::class, 'update'])
        ->middleware('permission:catalog.manage');
    Route::delete('admin/supermarket-chains/{id}', [\App\Http\Controllers\Api\V1\Supermarkets\AdminSupermarketChainController::class, 'destroy'])
        ->middleware('permission:catalog.manage');

    Route::get('supermarkets/{id}', [\App\Http\Controllers\Api\V1\Supermarkets\SupermarketCatalogController::class, 'show']);
    Route::get('supermarkets', [\App\Http\Controllers\Api\V1\Supermarkets\SupermarketCatalogController::class, 'index']);

    // Ciudades - administración y catálogo
    Route::get('admin/cities', [\App\Http\Controllers\Api\V1\Cities\AdminCityController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/cities', [\App\Http\Controllers\Api\V1\Cities\AdminCityController::class, 'store'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/cities/{id}/restore', [\App\Http\Controllers\Api\V1\Cities\AdminCityController::class, 'restore'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/cities/{id}', [\App\Http\Controllers\Api\V1\Cities\AdminCityController::class, 'update'])
        ->middleware('permission:catalog.manage');
    Route::delete('admin/cities/{id}', [\App\Http\Controllers\Api\V1\Cities\AdminCityController::class, 'destroy'])
        ->middleware('permission:catalog.manage');

    Route::get('cities', [\App\Http\Controllers\Api\V1\Cities\CityCatalogController::class, 'index']);

    Route::get('admin/food-tags', [\App\Http\Controllers\Api\V1\FoodTags\AdminFoodTagController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/food-tags', [\App\Http\Controllers\Api\V1\FoodTags\AdminFoodTagController::class, 'store'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/food-tags/{id}/restore', [\App\Http\Controllers\Api\V1\FoodTags\AdminFoodTagController::class, 'restore'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/food-tags/{id}', [\App\Http\Controllers\Api\V1\FoodTags\AdminFoodTagController::class, 'show'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/food-tags/{id}', [\App\Http\Controllers\Api\V1\FoodTags\AdminFoodTagController::class, 'update'])
        ->middleware('permission:catalog.manage');
    Route::delete('admin/food-tags/{id}', [\App\Http\Controllers\Api\V1\FoodTags\AdminFoodTagController::class, 'destroy'])
        ->middleware('permission:catalog.manage');

    Route::get('food-tags', [\App\Http\Controllers\Api\V1\FoodTags\FoodTagCatalogController::class, 'index']);

    Route::get('admin/ingredient-equivalences', [\App\Http\Controllers\Api\V1\IngredientEquivalences\AdminIngredientEquivalenceController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/ingredient-equivalences', [\App\Http\Controllers\Api\V1\IngredientEquivalences\AdminIngredientEquivalenceController::class, 'store'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/ingredient-equivalences/{id}/audit', [\App\Http\Controllers\Api\V1\IngredientEquivalences\AdminIngredientEquivalenceController::class, 'audit'])
        ->middleware('permission:audit.read');
    Route::patch('admin/ingredient-equivalences/{id}/restore', [\App\Http\Controllers\Api\V1\IngredientEquivalences\AdminIngredientEquivalenceController::class, 'restore'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/ingredient-equivalences/{id}', [\App\Http\Controllers\Api\V1\IngredientEquivalences\AdminIngredientEquivalenceController::class, 'show'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/ingredient-equivalences/{id}', [\App\Http\Controllers\Api\V1\IngredientEquivalences\AdminIngredientEquivalenceController::class, 'update'])
        ->middleware('permission:catalog.manage');
    Route::delete('admin/ingredient-equivalences/{id}', [\App\Http\Controllers\Api\V1\IngredientEquivalences\AdminIngredientEquivalenceController::class, 'destroy'])
        ->middleware('permission:catalog.manage');

    Route::get('admin/ingredients', [\App\Http\Controllers\Api\V1\Ingredients\AdminIngredientController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/ingredients', [\App\Http\Controllers\Api\V1\Ingredients\AdminIngredientController::class, 'store'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/ingredients/{id}/audit', [\App\Http\Controllers\Api\V1\Ingredients\AdminIngredientController::class, 'audit'])
        ->middleware('permission:audit.read');
    Route::patch('admin/ingredients/{id}/restore', [\App\Http\Controllers\Api\V1\Ingredients\AdminIngredientController::class, 'restore'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/ingredients/{id}', [\App\Http\Controllers\Api\V1\Ingredients\AdminIngredientController::class, 'show'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/ingredients/{id}', [\App\Http\Controllers\Api\V1\Ingredients\AdminIngredientController::class, 'update'])
        ->middleware('permission:catalog.manage');
    Route::delete('admin/ingredients/{id}', [\App\Http\Controllers\Api\V1\Ingredients\AdminIngredientController::class, 'destroy'])
        ->middleware('permission:catalog.manage');

    Route::get('ingredients/{id}/nutrition', [\App\Http\Controllers\Api\V1\Ingredients\IngredientCatalogController::class, 'nutrition']);
    Route::get('ingredients/{id}/equivalences', [\App\Http\Controllers\Api\V1\Ingredients\IngredientCatalogController::class, 'equivalences']);
    Route::get('ingredients/{id}', [\App\Http\Controllers\Api\V1\Ingredients\IngredientCatalogController::class, 'show']);
    Route::get('ingredients', [\App\Http\Controllers\Api\V1\Ingredients\IngredientCatalogController::class, 'index']);

    Route::get('admin/ingredient-categories', [\App\Http\Controllers\Api\V1\IngredientCategories\IngredientCategoryAdminController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/ingredient-categories', [\App\Http\Controllers\Api\V1\IngredientCategories\IngredientCategoryAdminController::class, 'store'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/ingredient-categories/{id}/restore', [\App\Http\Controllers\Api\V1\IngredientCategories\IngredientCategoryAdminController::class, 'restore'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/ingredient-categories/{id}', [\App\Http\Controllers\Api\V1\IngredientCategories\IngredientCategoryAdminController::class, 'show'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/ingredient-categories/{id}', [\App\Http\Controllers\Api\V1\IngredientCategories\IngredientCategoryAdminController::class, 'update'])
        ->middleware('permission:catalog.manage');
    Route::delete('admin/ingredient-categories/{id}', [\App\Http\Controllers\Api\V1\IngredientCategories\IngredientCategoryAdminController::class, 'destroy'])
        ->middleware('permission:catalog.manage');

    Route::get('ingredient-categories', [\App\Http\Controllers\Api\V1\IngredientCategories\IngredientCategoryCatalogController::class, 'index']);

    Route::get('family-groups/{id}/stock-locations', [\App\Http\Controllers\Api\V1\StockLocations\StockLocationController::class, 'index']);
    Route::post('family-groups/{id}/stock-locations', [\App\Http\Controllers\Api\V1\StockLocations\StockLocationController::class, 'store']);
    Route::patch('family-groups/{id}/stock-locations/{locationId}', [\App\Http\Controllers\Api\V1\StockLocations\StockLocationController::class, 'update']);
    Route::delete('family-groups/{id}/stock-locations/{locationId}', [\App\Http\Controllers\Api\V1\StockLocations\StockLocationController::class, 'destroy']);

    Route::get('family-groups/{id}/stock/summary', [\App\Http\Controllers\Api\V1\HouseholdStock\HouseholdStockController::class, 'summary']);
    Route::get('family-groups/{id}/stock/value', [\App\Http\Controllers\Api\V1\HouseholdStock\HouseholdStockController::class, 'value']);
    Route::get('family-groups/{id}/stock', [\App\Http\Controllers\Api\V1\HouseholdStock\HouseholdStockController::class, 'index']);
    Route::post('family-groups/{id}/stock', [\App\Http\Controllers\Api\V1\HouseholdStock\HouseholdStockController::class, 'store']);
    Route::patch('family-groups/{id}/stock/{stockItemId}', [\App\Http\Controllers\Api\V1\HouseholdStock\HouseholdStockController::class, 'update']);
    Route::delete('family-groups/{id}/stock/{stockItemId}', [\App\Http\Controllers\Api\V1\HouseholdStock\HouseholdStockController::class, 'destroy']);
});

Route::prefix('v1')->middleware(['web', 'trace_id', 'api_token', 'auth'])->group(function () {
    Route::pattern('healthPreferenceType', 'dietary-restrictions|health-conditions|allergies');

    Route::get('admin/{healthPreferenceType}', [\App\Http\Controllers\Api\V1\HealthPreferences\AdminHealthPreferenceController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/{healthPreferenceType}', [\App\Http\Controllers\Api\V1\HealthPreferences\AdminHealthPreferenceController::class, 'store'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/{healthPreferenceType}/{id}/restore', [\App\Http\Controllers\Api\V1\HealthPreferences\AdminHealthPreferenceController::class, 'restore'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/{healthPreferenceType}/{id}', [\App\Http\Controllers\Api\V1\HealthPreferences\AdminHealthPreferenceController::class, 'show'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/{healthPreferenceType}/{id}', [\App\Http\Controllers\Api\V1\HealthPreferences\AdminHealthPreferenceController::class, 'update'])
        ->middleware('permission:catalog.manage');
    Route::delete('admin/{healthPreferenceType}/{id}', [\App\Http\Controllers\Api\V1\HealthPreferences\AdminHealthPreferenceController::class, 'destroy'])
        ->middleware('permission:catalog.manage');

    Route::get('catalog/{healthPreferenceType}', [\App\Http\Controllers\Api\V1\HealthPreferences\CatalogHealthPreferenceController::class, 'index']);

    Route::get('users/me/{healthPreferenceType}', [\App\Http\Controllers\Api\V1\HealthPreferences\UserHealthPreferenceController::class, 'index']);
    Route::post('users/me/{healthPreferenceType}', [\App\Http\Controllers\Api\V1\HealthPreferences\UserHealthPreferenceController::class, 'store']);
    Route::delete('users/me/{healthPreferenceType}/{id}', [\App\Http\Controllers\Api\V1\HealthPreferences\UserHealthPreferenceController::class, 'destroy']);
});

Route::prefix('v1')->middleware(['web', 'trace_id', 'api_token', 'auth'])->group(function () {
    // Professional links (patient perspective)
    Route::prefix('professional-links')->group(function () {
        Route::get('',        [\App\Http\Controllers\Api\V1\Professional\ProfessionalLinkController::class, 'index']);
        Route::post('',       [\App\Http\Controllers\Api\V1\Professional\ProfessionalLinkController::class, 'store']);
        Route::patch('{id}',  [\App\Http\Controllers\Api\V1\Professional\ProfessionalLinkController::class, 'update']);
        Route::delete('{id}', [\App\Http\Controllers\Api\V1\Professional\ProfessionalLinkController::class, 'destroy']);
    });

    // Professional panel (dietologist perspective)
    Route::prefix('professional')->middleware('permission:professional.users.read')->group(function () {
        Route::get('linked-users',                         [\App\Http\Controllers\Api\V1\Professional\ProfessionalPanelController::class, 'linkedUsers']);
        Route::get('users/{userId}/profile',               [\App\Http\Controllers\Api\V1\Professional\ProfessionalPanelController::class, 'userProfile']);
        Route::get('users/{userId}/meal-plans',            [\App\Http\Controllers\Api\V1\Professional\ProfessionalPanelController::class, 'mealPlans']);
        Route::patch('users/{userId}/meal-plans/{planId}', [\App\Http\Controllers\Api\V1\Professional\ProfessionalPanelController::class, 'updateMealPlan'])
            ->middleware('permission:professional.meal_plans.write');
    });
});

// ---Nutrientes ---
Route::prefix('v1')->middleware(['web', 'trace_id', 'api_token', 'auth'])->group(function () {
    // ABM de nutrientes
    Route::get('admin/nutrients', [\App\Http\Controllers\Api\V1\Nutrients\AdminNutrientController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/nutrients', [\App\Http\Controllers\Api\V1\Nutrients\AdminNutrientController::class, 'store'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/nutrients/{id}/audit', [\App\Http\Controllers\Api\V1\Nutrients\AdminNutrientController::class, 'audit'])
        ->middleware('permission:audit.read');
    Route::patch('admin/nutrients/{id}/restore', [\App\Http\Controllers\Api\V1\Nutrients\AdminNutrientController::class, 'restore'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/nutrients/{id}', [\App\Http\Controllers\Api\V1\Nutrients\AdminNutrientController::class, 'show'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/nutrients/{id}', [\App\Http\Controllers\Api\V1\Nutrients\AdminNutrientController::class, 'update'])
        ->middleware('permission:catalog.manage');
    Route::delete('admin/nutrients/{id}', [\App\Http\Controllers\Api\V1\Nutrients\AdminNutrientController::class, 'destroy'])
        ->middleware('permission:catalog.manage');

    // Nutrientes de ingredientes
    Route::get('admin/ingredients/{id}/nutrients', [\App\Http\Controllers\Api\V1\Nutrients\IngredientNutrientController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/ingredients/{id}/nutrients', [\App\Http\Controllers\Api\V1\Nutrients\IngredientNutrientController::class, 'store'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/ingredients/{id}/nutrients/{nutrientId}', [\App\Http\Controllers\Api\V1\Nutrients\IngredientNutrientController::class, 'update'])
        ->middleware('permission:catalog.manage');

    // Nutrientes de productos
    Route::get('admin/products/{id}/nutrients', [\App\Http\Controllers\Api\V1\Nutrients\ProductNutrientController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/products/{id}/nutrients', [\App\Http\Controllers\Api\V1\Nutrients\ProductNutrientController::class, 'store'])
        ->middleware('permission:catalog.manage');

    // Códigos de barras de productos
    Route::post('admin/products/{id}/barcodes', [\App\Http\Controllers\Api\V1\Products\AdminProductBarcodeController::class, 'store'])
        ->middleware('permission:catalog.manage');
    Route::delete('admin/products/{id}/barcodes/{barcodeId}', [\App\Http\Controllers\Api\V1\Products\AdminProductBarcodeController::class, 'destroy'])
        ->middleware('permission:catalog.manage');

    // Imágenes de productos
    Route::post('admin/products/{id}/images', [\App\Http\Controllers\Api\V1\Products\AdminProductImageController::class, 'store'])
        ->middleware('permission:catalog.manage');
    Route::delete('admin/products/{id}/images/{imageId}', [\App\Http\Controllers\Api\V1\Products\AdminProductImageController::class, 'destroy'])
        ->middleware('permission:catalog.manage');

    // Scraping — candidatos de productos (acciones especificas antes del {id} generico)
    Route::post('admin/scraping/product-candidates/{id}/approve', [\App\Http\Controllers\Api\V1\ScrapingCandidates\ScrapingCandidateController::class, 'approve'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/scraping/product-candidates/{id}/reject', [\App\Http\Controllers\Api\V1\ScrapingCandidates\ScrapingCandidateController::class, 'reject'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/scraping/product-candidates/{id}/match-product', [\App\Http\Controllers\Api\V1\ScrapingCandidates\ScrapingCandidateController::class, 'matchProduct'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/scraping/product-candidates/{id}/create-product', [\App\Http\Controllers\Api\V1\ScrapingCandidates\ScrapingCandidateController::class, 'createProduct'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/scraping/product-candidates/{id}/assign-ingredient', [\App\Http\Controllers\Api\V1\ScrapingCandidates\ScrapingCandidateController::class, 'assignIngredient'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/scraping/product-candidates/{id}', [\App\Http\Controllers\Api\V1\ScrapingCandidates\ScrapingCandidateController::class, 'show'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/scraping/product-candidates', [\App\Http\Controllers\Api\V1\ScrapingCandidates\ScrapingCandidateController::class, 'index'])
        ->middleware('permission:catalog.manage');

    // Scraping — fuentes
    Route::get('admin/scraping/alerts', [\App\Http\Controllers\Api\V1\ScrapingAlerts\ScrapingAlertController::class, 'index'])
        ->middleware('permission:scraping.manage');
    Route::patch('admin/scraping/alerts/{id}/resolve', [\App\Http\Controllers\Api\V1\ScrapingAlerts\ScrapingAlertController::class, 'resolve'])
        ->middleware('permission:scraping.manage');
    Route::get('admin/reports/scraping-errors', [\App\Http\Controllers\Api\V1\ScrapingAlerts\ScrapingAlertController::class, 'report'])
        ->middleware('permission:scraping.manage');

    Route::get('admin/price-refresh-requests', [\App\Http\Controllers\Api\V1\PriceRefreshRequests\PriceRefreshRequestController::class, 'adminIndex'])
        ->middleware('permission:scraping.manage');
    Route::post('admin/price-refresh-requests/{id}/process', [\App\Http\Controllers\Api\V1\PriceRefreshRequests\PriceRefreshRequestController::class, 'process'])
        ->middleware('permission:scraping.manage');

    Route::get('admin/scraping/sources', [\App\Http\Controllers\Api\V1\Scraping\ScrapingSourceController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/scraping/sources', [\App\Http\Controllers\Api\V1\Scraping\ScrapingSourceController::class, 'store'])
        ->middleware('permission:catalog.manage');

    // Scraping — jobs (retry/cancel antes del {id} para evitar colision)
    Route::post('admin/scraping/jobs/{id}/retry', [\App\Http\Controllers\Api\V1\Scraping\ScrapingJobController::class, 'retry'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/scraping/jobs/{id}/cancel', [\App\Http\Controllers\Api\V1\Scraping\ScrapingJobController::class, 'cancel'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/scraping/jobs/{id}/logs', [\App\Http\Controllers\Api\V1\Scraping\ScrapingJobController::class, 'logs'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/scraping/jobs/{id}', [\App\Http\Controllers\Api\V1\Scraping\ScrapingJobController::class, 'show'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/scraping/jobs', [\App\Http\Controllers\Api\V1\Scraping\ScrapingJobController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/scraping/jobs', [\App\Http\Controllers\Api\V1\Scraping\ScrapingJobController::class, 'store'])
        ->middleware('permission:catalog.manage');
});
