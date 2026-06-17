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

    Route::get('admin/recipe-categories', [\App\Http\Controllers\Api\V1\RecipeCategories\RecipeCategoryAdminController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/recipe-categories', [\App\Http\Controllers\Api\V1\RecipeCategories\RecipeCategoryAdminController::class, 'store'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/recipe-categories/{id}/restore', [\App\Http\Controllers\Api\V1\RecipeCategories\RecipeCategoryAdminController::class, 'restore'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/recipe-categories/{id}', [\App\Http\Controllers\Api\V1\RecipeCategories\RecipeCategoryAdminController::class, 'show'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/recipe-categories/{id}', [\App\Http\Controllers\Api\V1\RecipeCategories\RecipeCategoryAdminController::class, 'update'])
        ->middleware('permission:catalog.manage');
    Route::delete('admin/recipe-categories/{id}', [\App\Http\Controllers\Api\V1\RecipeCategories\RecipeCategoryAdminController::class, 'destroy'])
        ->middleware('permission:catalog.manage');

    Route::get('recipe-categories', [\App\Http\Controllers\Api\V1\RecipeCategories\RecipeCategoryCatalogController::class, 'index']);

    Route::get('meal-types', [\App\Http\Controllers\Api\V1\MealTypes\MealTypeCatalogController::class, 'index']);

    Route::get('admin/meal-types', [\App\Http\Controllers\Api\V1\MealTypes\MealTypeAdminController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/meal-types', [\App\Http\Controllers\Api\V1\MealTypes\MealTypeAdminController::class, 'store'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/meal-types/{id}/restore', [\App\Http\Controllers\Api\V1\MealTypes\MealTypeAdminController::class, 'restore'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/meal-types/{id}', [\App\Http\Controllers\Api\V1\MealTypes\MealTypeAdminController::class, 'show'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/meal-types/{id}', [\App\Http\Controllers\Api\V1\MealTypes\MealTypeAdminController::class, 'update'])
        ->middleware('permission:catalog.manage');
    Route::delete('admin/meal-types/{id}', [\App\Http\Controllers\Api\V1\MealTypes\MealTypeAdminController::class, 'destroy'])
        ->middleware('permission:catalog.manage');

    Route::get('admin/recipe-tags', [\App\Http\Controllers\Api\V1\RecipeTags\RecipeTagAdminController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/recipe-tags', [\App\Http\Controllers\Api\V1\RecipeTags\RecipeTagAdminController::class, 'store'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/recipe-tags/{id}/restore', [\App\Http\Controllers\Api\V1\RecipeTags\RecipeTagAdminController::class, 'restore'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/recipe-tags/{id}', [\App\Http\Controllers\Api\V1\RecipeTags\RecipeTagAdminController::class, 'show'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/recipe-tags/{id}', [\App\Http\Controllers\Api\V1\RecipeTags\RecipeTagAdminController::class, 'update'])
        ->middleware('permission:catalog.manage');
    Route::delete('admin/recipe-tags/{id}', [\App\Http\Controllers\Api\V1\RecipeTags\RecipeTagAdminController::class, 'destroy'])
        ->middleware('permission:catalog.manage');

    Route::get('recipe-tags', [\App\Http\Controllers\Api\V1\RecipeTags\RecipeTagCatalogController::class, 'index']);

    Route::get('admin/recipes', [\App\Http\Controllers\Api\V1\Recipes\AdminRecipeController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/recipes', [\App\Http\Controllers\Api\V1\Recipes\AdminRecipeController::class, 'store'])
        ->middleware('permission:catalog.manage');

    Route::get('admin/recipes/import-candidates', [\App\Http\Controllers\Api\V1\RecipeImportCandidates\RecipeImportCandidatesController::class, 'index'])
        ->middleware('permission:recipes.manage');
    Route::patch('admin/recipes/import-candidates/{id}', [\App\Http\Controllers\Api\V1\RecipeImportCandidates\RecipeImportCandidatesController::class, 'update'])
        ->middleware('permission:recipes.manage');
    Route::post('admin/recipes/import-candidates/{id}/approve', [\App\Http\Controllers\Api\V1\RecipeImportCandidates\RecipeImportCandidatesController::class, 'approve'])
        ->middleware('permission:recipes.manage');
    Route::post('admin/recipes/import-candidates/{id}/reject', [\App\Http\Controllers\Api\V1\RecipeImportCandidates\RecipeImportCandidatesController::class, 'reject'])
        ->middleware('permission:recipes.manage');
    Route::post('admin/recipes/import-candidates/{id}/map-ingredient', [\App\Http\Controllers\Api\V1\RecipeImportCandidates\RecipeImportCandidatesController::class, 'mapIngredient'])
        ->middleware('permission:recipes.manage');
    Route::post('admin/recipes/import-candidates/{id}/create-recipe', [\App\Http\Controllers\Api\V1\RecipeImportCandidates\RecipeImportCandidatesController::class, 'createRecipe'])
        ->middleware('permission:recipes.manage');
    Route::get('admin/recipes/import-candidates/{id}', [\App\Http\Controllers\Api\V1\RecipeImportCandidates\RecipeImportCandidatesController::class, 'show'])
        ->middleware('permission:recipes.manage');

    Route::get('admin/recipes/{id}', [\App\Http\Controllers\Api\V1\Recipes\AdminRecipeController::class, 'show'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/recipes/{id}', [\App\Http\Controllers\Api\V1\Recipes\AdminRecipeController::class, 'update'])
        ->middleware('permission:catalog.manage');
    Route::delete('admin/recipes/{id}', [\App\Http\Controllers\Api\V1\Recipes\AdminRecipeController::class, 'destroy'])
        ->middleware('permission:catalog.manage');

    Route::get('recipes/{id}/availability', [\App\Http\Controllers\Api\V1\RecipeAvailability\RecipeAvailabilityController::class, 'availability']);
    Route::get('recipes/{id}/missing-ingredients', [\App\Http\Controllers\Api\V1\RecipeAvailability\RecipeAvailabilityController::class, 'missingIngredients']);

    Route::get('recipes/{id}/cost', [\App\Http\Controllers\Api\V1\RecipeCost\RecipeCostController::class, 'show']);
    Route::post('admin/recipes/scraping/jobs', [\App\Http\Controllers\Api\V1\RecipeScraping\RecipeScrapingJobController::class, 'store'])
        ->middleware('permission:recipes.manage');
    Route::get('admin/recipes/scraping/jobs', [\App\Http\Controllers\Api\V1\RecipeScraping\RecipeScrapingJobController::class, 'index'])
        ->middleware('permission:recipes.manage');
    Route::get('admin/recipes/scraping/jobs/{id}', [\App\Http\Controllers\Api\V1\RecipeScraping\RecipeScrapingJobController::class, 'show'])
        ->middleware('permission:recipes.manage');
    Route::post('admin/recipes/scraping/jobs/{id}/retry', [\App\Http\Controllers\Api\V1\RecipeScraping\RecipeScrapingJobController::class, 'retry'])
        ->middleware('permission:recipes.manage');

    Route::post('admin/recipes/import/text', [\App\Http\Controllers\Api\V1\RecipeImportText\RecipeImportTextController::class, '__invoke'])
        ->middleware('permission:recipes.manage');

    Route::post('admin/recipes/import/url', [\App\Http\Controllers\Api\V1\RecipeImportUrl\RecipeImportUrlController::class, '__invoke'])
        ->middleware('permission:recipes.manage');

    Route::post('admin/recipes/{id}/recalculate-cost', [\App\Http\Controllers\Api\V1\RecipeCost\RecipeCostController::class, 'recalculate'])
        ->middleware('permission:recipes.manage');

    Route::get('recipes/{id}/nutrition', [\App\Http\Controllers\Api\V1\RecipeNutrition\RecipeNutritionController::class, 'show']);
    Route::post('admin/recipes/{id}/recalculate-nutrition', [\App\Http\Controllers\Api\V1\RecipeNutrition\RecipeNutritionController::class, 'recalculate'])
        ->middleware('permission:recipes.manage');

    Route::post('recipes/{id}/steps', [\App\Http\Controllers\Api\V1\RecipeSteps\RecipeStepController::class, 'store']);
    Route::patch('recipes/{id}/steps/{stepId}', [\App\Http\Controllers\Api\V1\RecipeSteps\RecipeStepController::class, 'update']);
    Route::delete('recipes/{id}/steps/{stepId}', [\App\Http\Controllers\Api\V1\RecipeSteps\RecipeStepController::class, 'destroy']);

    Route::post('recipes/{id}/ingredients', [\App\Http\Controllers\Api\V1\RecipeIngredients\RecipeIngredientController::class, 'store']);
    Route::patch('recipes/{id}/ingredients/{ingredientId}', [\App\Http\Controllers\Api\V1\RecipeIngredients\RecipeIngredientController::class, 'update']);
    Route::delete('recipes/{id}/ingredients/{ingredientId}', [\App\Http\Controllers\Api\V1\RecipeIngredients\RecipeIngredientController::class, 'destroy']);

    Route::get('recipes/search', [\App\Http\Controllers\Api\V1\RecipeSearch\RecipeSearchController::class, '__invoke']);
    Route::get('recipes/suggestions', [\App\Http\Controllers\Api\V1\RecipeSuggestions\RecipeSuggestionsController::class, 'suggestions']);

    Route::get('recipes/{id}/substitutions', [\App\Http\Controllers\Api\V1\RecipeSubstitutions\RecipeSubstitutionsController::class, '__invoke']);

    Route::post('recipes/{id}/share', [\App\Http\Controllers\Api\V1\RecipeSharingBranch\RecipeSharingBranchController::class, 'share']);
    Route::post('recipes/{id}/unshare', [\App\Http\Controllers\Api\V1\RecipeSharingBranch\RecipeSharingBranchController::class, 'unshare']);
    Route::post('recipes/{id}/branch', [\App\Http\Controllers\Api\V1\RecipeSharingBranch\RecipeSharingBranchController::class, 'branch']);

    Route::post('recipes/{id}/favorite', [\App\Http\Controllers\Api\V1\RecipeFavoritesCooked\RecipeFavoritesCookedController::class, 'addFavorite']);
    Route::delete('recipes/{id}/favorite', [\App\Http\Controllers\Api\V1\RecipeFavoritesCooked\RecipeFavoritesCookedController::class, 'removeFavorite']);
    Route::post('recipes/{id}/cook', [\App\Http\Controllers\Api\V1\RecipeFavoritesCooked\RecipeFavoritesCookedController::class, 'cook']);
    Route::get('users/me/favorite-recipes', [\App\Http\Controllers\Api\V1\RecipeFavoritesCooked\RecipeFavoritesCookedController::class, 'listFavorites']);
    Route::get('users/me/cooked-recipes', [\App\Http\Controllers\Api\V1\RecipeFavoritesCooked\RecipeFavoritesCookedController::class, 'listCooked']);

    Route::get('family-groups/{id}/recipes/available',        [\App\Http\Controllers\Api\V1\RecipeSuggestions\RecipeSuggestionsController::class, 'available']);
    Route::get('family-groups/{id}/recipes/almost-available', [\App\Http\Controllers\Api\V1\RecipeSuggestions\RecipeSuggestionsController::class, 'almostAvailable']);
    Route::get('family-groups/{id}/recipes/by-expiring-stock',[\App\Http\Controllers\Api\V1\RecipeSuggestions\RecipeSuggestionsController::class, 'byExpiringStock']);
    Route::get('family-groups/{id}/recipes/by-budget',        [\App\Http\Controllers\Api\V1\RecipeSuggestions\RecipeSuggestionsController::class, 'byBudget']);
    Route::get('family-groups/{id}/recipes/by-objectives',    [\App\Http\Controllers\Api\V1\RecipeSuggestions\RecipeSuggestionsController::class, 'byObjectives']);

    Route::get('recipes', [\App\Http\Controllers\Api\V1\Recipes\RecipeController::class, 'index']);
    Route::post('recipes', [\App\Http\Controllers\Api\V1\Recipes\RecipeController::class, 'store']);
    Route::get('recipes/{id}', [\App\Http\Controllers\Api\V1\Recipes\RecipeController::class, 'show']);
    Route::patch('recipes/{id}', [\App\Http\Controllers\Api\V1\Recipes\RecipeController::class, 'update']);
    Route::delete('recipes/{id}', [\App\Http\Controllers\Api\V1\Recipes\RecipeController::class, 'destroy']);

    Route::get('family-groups/{id}/stock-locations', [\App\Http\Controllers\Api\V1\StockLocations\StockLocationController::class, 'index']);
    Route::post('family-groups/{id}/stock-locations', [\App\Http\Controllers\Api\V1\StockLocations\StockLocationController::class, 'store']);
    Route::patch('family-groups/{id}/stock-locations/{locationId}', [\App\Http\Controllers\Api\V1\StockLocations\StockLocationController::class, 'update']);
    Route::delete('family-groups/{id}/stock-locations/{locationId}', [\App\Http\Controllers\Api\V1\StockLocations\StockLocationController::class, 'destroy']);

    Route::get('family-groups/{id}/stock/summary', [\App\Http\Controllers\Api\V1\HouseholdStock\HouseholdStockController::class, 'summary']);
    Route::get('family-groups/{id}/stock/value', [\App\Http\Controllers\Api\V1\HouseholdStock\HouseholdStockController::class, 'value']);
    Route::get('family-groups/{id}/reports/waste', [\App\Http\Controllers\Api\V1\WasteReports\WasteReportController::class, 'show']);
    Route::get('family-groups/{id}/stock/expiring', [\App\Http\Controllers\Api\V1\StockAlerts\StockAlertController::class, 'expiring']);
    Route::get('family-groups/{id}/stock/low-stock', [\App\Http\Controllers\Api\V1\StockAlerts\StockAlertController::class, 'lowStock']);
    Route::post('family-groups/{id}/stock/scan', [\App\Http\Controllers\Api\V1\StockScan\StockScanController::class, 'store']);
    Route::get('family-groups/{id}/stock-alerts', [\App\Http\Controllers\Api\V1\StockAlerts\StockAlertController::class, 'index']);
    Route::patch('family-groups/{id}/stock-alerts/{alertId}/read', [\App\Http\Controllers\Api\V1\StockAlerts\StockAlertController::class, 'read']);
    Route::get('family-groups/{id}/stock-minimum-rules', [\App\Http\Controllers\Api\V1\StockAlerts\StockMinimumRuleController::class, 'index']);
    Route::post('family-groups/{id}/stock-minimum-rules', [\App\Http\Controllers\Api\V1\StockAlerts\StockMinimumRuleController::class, 'store']);
    Route::get('family-groups/{id}/stock-minimum-rules/{ruleId}', [\App\Http\Controllers\Api\V1\StockAlerts\StockMinimumRuleController::class, 'show']);
    Route::patch('family-groups/{id}/stock-minimum-rules/{ruleId}', [\App\Http\Controllers\Api\V1\StockAlerts\StockMinimumRuleController::class, 'update']);
    Route::delete('family-groups/{id}/stock-minimum-rules/{ruleId}', [\App\Http\Controllers\Api\V1\StockAlerts\StockMinimumRuleController::class, 'destroy']);
    Route::get('family-groups/{id}/stock-movements', [\App\Http\Controllers\Api\V1\StockMovements\StockMovementController::class, 'index']);
    Route::post('family-groups/{id}/stock/{stockItemId}/adjust', [\App\Http\Controllers\Api\V1\StockMovements\StockMovementController::class, 'adjust']);
    Route::post('family-groups/{id}/stock/{stockItemId}/consume', [\App\Http\Controllers\Api\V1\StockMovements\StockMovementController::class, 'consume']);
    Route::post('family-groups/{id}/stock/{stockItemId}/discard', [\App\Http\Controllers\Api\V1\StockMovements\StockMovementController::class, 'discard']);
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

    Route::get('users/me/supplements',        [\App\Http\Controllers\Api\V1\UserSupplements\UserSupplementController::class, 'index']);
    Route::post('users/me/supplements',       [\App\Http\Controllers\Api\V1\UserSupplements\UserSupplementController::class, 'store']);
    Route::patch('users/me/supplements/{id}', [\App\Http\Controllers\Api\V1\UserSupplements\UserSupplementController::class, 'update']);
    Route::delete('users/me/supplements/{id}',[\App\Http\Controllers\Api\V1\UserSupplements\UserSupplementController::class, 'destroy']);
    Route::get('users/me/supplements/{id}/schedule',                           [\App\Http\Controllers\Api\V1\UserSupplements\SupplementScheduleController::class, 'index']);
    Route::post('users/me/supplements/{id}/schedule',                          [\App\Http\Controllers\Api\V1\UserSupplements\SupplementScheduleController::class, 'store']);
    Route::patch('users/me/supplements/{id}/schedule/{scheduleId}',            [\App\Http\Controllers\Api\V1\UserSupplements\SupplementScheduleController::class, 'update']);
    Route::delete('users/me/supplements/{id}/schedule/{scheduleId}',           [\App\Http\Controllers\Api\V1\UserSupplements\SupplementScheduleController::class, 'destroy']);
    Route::post('users/me/supplements/{id}/log',                               [\App\Http\Controllers\Api\V1\UserSupplements\SupplementScheduleController::class, 'storeLog']);

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

// Meal Plan Incompatibilities
Route::prefix('v1')->middleware(['web', 'trace_id', 'api_token', 'auth'])->group(function () {
    Route::get('family-groups/{id}/meal-plans/{planId}/incompatibilities',       [\App\Http\Controllers\Api\V1\MealPlanIncompatibilities\MealPlanIncompatibilityController::class, 'index']);
    Route::post('family-groups/{id}/meal-plans/{planId}/check-incompatibilities', [\App\Http\Controllers\Api\V1\MealPlanIncompatibilities\MealPlanIncompatibilityController::class, 'check']);
});

// Meal Plan Portions
Route::prefix('v1')->middleware(['web', 'trace_id', 'api_token', 'auth'])->group(function () {
    Route::get('family-groups/{id}/meal-plans/{planId}/items/{itemId}/portions',                  [\App\Http\Controllers\Api\V1\MealPlanPortions\MealPlanPortionController::class, 'index']);
    Route::post('family-groups/{id}/meal-plans/{planId}/items/{itemId}/portions',                 [\App\Http\Controllers\Api\V1\MealPlanPortions\MealPlanPortionController::class, 'store']);
    Route::patch('family-groups/{id}/meal-plans/{planId}/items/{itemId}/portions/{portionId}',    [\App\Http\Controllers\Api\V1\MealPlanPortions\MealPlanPortionController::class, 'update']);
});

// Meal Plan Items
Route::prefix('v1')->middleware(['web', 'trace_id', 'api_token', 'auth'])->group(function () {
    Route::get('family-groups/{id}/meal-plans/{planId}/items',              [\App\Http\Controllers\Api\V1\MealPlanItems\MealPlanItemController::class, 'index']);
    Route::post('family-groups/{id}/meal-plans/{planId}/items',             [\App\Http\Controllers\Api\V1\MealPlanItems\MealPlanItemController::class, 'store']);
    Route::post('family-groups/{id}/meal-plans/{planId}/items/{itemId}/mark-cooked', [\App\Http\Controllers\Api\V1\MealPlanItemStatus\MealPlanItemStatusController::class, 'markCooked']);
    Route::post('family-groups/{id}/meal-plans/{planId}/items/{itemId}/skip',        [\App\Http\Controllers\Api\V1\MealPlanItemStatus\MealPlanItemStatusController::class, 'skip']);
    Route::patch('family-groups/{id}/meal-plans/{planId}/items/{itemId}',   [\App\Http\Controllers\Api\V1\MealPlanItems\MealPlanItemController::class, 'update']);
    Route::delete('family-groups/{id}/meal-plans/{planId}/items/{itemId}',  [\App\Http\Controllers\Api\V1\MealPlanItems\MealPlanItemController::class, 'destroy']);
});

// Meal Plans — generation routes before {planId} to avoid first-match collision on POST
Route::prefix('v1')->middleware(['web', 'trace_id', 'api_token', 'auth'])->group(function () {
    Route::post('family-groups/{id}/meal-plans/generate',            [\App\Http\Controllers\Api\V1\MealPlanGeneration\MealPlanGenerationController::class, 'generate']);
    Route::post('family-groups/{id}/meal-plans/{planId}/approve',    [\App\Http\Controllers\Api\V1\MealPlanGeneration\MealPlanGenerationController::class, 'approve']);
    Route::post('family-groups/{id}/meal-plans/{planId}/regenerate', [\App\Http\Controllers\Api\V1\MealPlanGeneration\MealPlanGenerationController::class, 'regenerate']);
    Route::get('family-groups/{id}/meal-plans/{planId}/shopping-list-preview', [\App\Http\Controllers\Api\V1\ShoppingListPreview\ShoppingListPreviewController::class, 'preview']);
    Route::post('family-groups/{id}/meal-plans/{planId}/generate-shopping-list', [\App\Http\Controllers\Api\V1\ShoppingListPreview\ShoppingListPreviewController::class, 'generate']);
    Route::get('family-groups/{id}/shopping-lists', [\App\Http\Controllers\Api\V1\ShoppingLists\ShoppingListController::class, 'index']);
    Route::post('family-groups/{id}/shopping-lists', [\App\Http\Controllers\Api\V1\ShoppingLists\ShoppingListController::class, 'store']);
    Route::post('family-groups/{id}/shopping-lists/generate-from-meal-plan', [\App\Http\Controllers\Api\V1\ShoppingListGeneration\ShoppingListGenerationController::class, 'fromMealPlan']);
    Route::post('family-groups/{id}/shopping-lists/generate-from-history', [\App\Http\Controllers\Api\V1\ShoppingListGeneration\ShoppingListGenerationController::class, 'fromHistory']);
    Route::post('family-groups/{id}/shopping-lists/{listId}/start-session', [\App\Http\Controllers\Api\V1\ShoppingSessions\ShoppingSessionController::class, 'start']);
    Route::get('family-groups/{id}/shopping-lists/{listId}/compare-supermarkets', [\App\Http\Controllers\Api\V1\SupermarketComparison\SupermarketComparisonController::class, 'compare']);
    Route::get('family-groups/{id}/shopping-lists/{listId}/optimize', [\App\Http\Controllers\Api\V1\SupermarketComparison\SupermarketComparisonController::class, 'optimize']);
    Route::get('family-groups/{id}/shopping-lists/{listId}/alternatives', [\App\Http\Controllers\Api\V1\ShoppingAlternatives\ShoppingAlternativeController::class, 'index']);
    Route::get('family-groups/{id}/shopping-lists/{listId}/items', [\App\Http\Controllers\Api\V1\ShoppingListItems\ShoppingListItemController::class, 'index']);
    Route::post('family-groups/{id}/shopping-lists/{listId}/items', [\App\Http\Controllers\Api\V1\ShoppingListItems\ShoppingListItemController::class, 'store']);
    Route::post('family-groups/{id}/shopping-lists/{listId}/items/{itemId}/select-alternative', [\App\Http\Controllers\Api\V1\ShoppingAlternatives\ShoppingAlternativeController::class, 'select']);
    Route::patch('family-groups/{id}/shopping-lists/{listId}/items/{itemId}', [\App\Http\Controllers\Api\V1\ShoppingListItems\ShoppingListItemController::class, 'update']);
    Route::delete('family-groups/{id}/shopping-lists/{listId}/items/{itemId}', [\App\Http\Controllers\Api\V1\ShoppingListItems\ShoppingListItemController::class, 'destroy']);
    Route::get('family-groups/{id}/shopping-lists/{listId}', [\App\Http\Controllers\Api\V1\ShoppingLists\ShoppingListController::class, 'show']);
    Route::patch('family-groups/{id}/shopping-lists/{listId}', [\App\Http\Controllers\Api\V1\ShoppingLists\ShoppingListController::class, 'update']);
    Route::delete('family-groups/{id}/shopping-lists/{listId}', [\App\Http\Controllers\Api\V1\ShoppingLists\ShoppingListController::class, 'destroy']);
    Route::patch('family-groups/{id}/shopping-sessions/{sessionId}', [\App\Http\Controllers\Api\V1\ShoppingSessions\ShoppingSessionController::class, 'update']);
    Route::post('family-groups/{id}/shopping-sessions/{sessionId}/scan', [\App\Http\Controllers\Api\V1\ShoppingSessions\ShoppingSessionController::class, 'scan']);
    Route::post('family-groups/{id}/shopping-sessions/{sessionId}/finish', [\App\Http\Controllers\Api\V1\ShoppingSessions\ShoppingSessionController::class, 'finish']);
});

// Budgets
Route::prefix('v1')->middleware(['web', 'trace_id', 'api_token', 'auth'])->group(function () {
    Route::get('family-groups/{id}/budgets/{budgetId}/alerts',                     [\App\Http\Controllers\Api\V1\Budgets\BudgetAlertController::class, 'index']);
    Route::patch('family-groups/{id}/budgets/{budgetId}/alerts/{alertId}/read',    [\App\Http\Controllers\Api\V1\Budgets\BudgetAlertController::class, 'markAsRead']);
    Route::get('family-groups/{id}/budgets/{budgetId}/movements',   [\App\Http\Controllers\Api\V1\Budgets\BudgetMovementController::class, 'index']);
    Route::post('family-groups/{id}/budgets/{budgetId}/adjustments', [\App\Http\Controllers\Api\V1\Budgets\BudgetMovementController::class, 'storeAdjustment']);
    Route::get('family-groups/{id}/budgets/{budgetId}/summary',    [\App\Http\Controllers\Api\V1\Budgets\BudgetSummaryController::class, 'summary']);
    Route::get('family-groups/{id}/budgets/{budgetId}/projection', [\App\Http\Controllers\Api\V1\Budgets\BudgetSummaryController::class, 'projection']);
    Route::get('family-groups/{id}/budgets/{budgetId}/categories',             [\App\Http\Controllers\Api\V1\Budgets\BudgetCategoryController::class, 'index']);
    Route::post('family-groups/{id}/budgets/{budgetId}/categories',            [\App\Http\Controllers\Api\V1\Budgets\BudgetCategoryController::class, 'store']);
    Route::patch('family-groups/{id}/budgets/{budgetId}/categories/{categoryId}',  [\App\Http\Controllers\Api\V1\Budgets\BudgetCategoryController::class, 'update']);
    Route::delete('family-groups/{id}/budgets/{budgetId}/categories/{categoryId}', [\App\Http\Controllers\Api\V1\Budgets\BudgetCategoryController::class, 'destroy']);
    Route::get('family-groups/{id}/budgets/current',         [\App\Http\Controllers\Api\V1\Budgets\BudgetController::class, 'current']);
    Route::get('family-groups/{id}/budgets',                 [\App\Http\Controllers\Api\V1\Budgets\BudgetController::class, 'index']);
    Route::post('family-groups/{id}/budgets',                [\App\Http\Controllers\Api\V1\Budgets\BudgetController::class, 'store']);
    Route::patch('family-groups/{id}/budgets/{budgetId}',    [\App\Http\Controllers\Api\V1\Budgets\BudgetController::class, 'update']);
    Route::delete('family-groups/{id}/budgets/{budgetId}',   [\App\Http\Controllers\Api\V1\Budgets\BudgetController::class, 'destroy']);
});

// Purchases
Route::prefix('v1')->middleware(['web', 'trace_id', 'api_token', 'auth'])->group(function () {
    Route::get('family-groups/{id}/purchases',                                          [\App\Http\Controllers\Api\V1\Purchases\PurchaseController::class, 'index']);
    Route::post('family-groups/{id}/purchases',                                         [\App\Http\Controllers\Api\V1\Purchases\PurchaseController::class, 'store']);
    Route::post('family-groups/{id}/purchases/{purchaseId}/confirm',                    [\App\Http\Controllers\Api\V1\Purchases\PurchaseConfirmationController::class, 'confirm']);
    Route::post('family-groups/{id}/purchases/{purchaseId}/add-to-stock',               [\App\Http\Controllers\Api\V1\Purchases\PurchaseConfirmationController::class, 'addToStock']);
    Route::get('family-groups/{id}/purchases/{purchaseId}/items',                       [\App\Http\Controllers\Api\V1\Purchases\PurchaseItemController::class, 'index']);
    Route::post('family-groups/{id}/purchases/{purchaseId}/items',                      [\App\Http\Controllers\Api\V1\Purchases\PurchaseItemController::class, 'store']);
    Route::patch('family-groups/{id}/purchases/{purchaseId}/items/{itemId}',            [\App\Http\Controllers\Api\V1\Purchases\PurchaseItemController::class, 'update']);
    Route::delete('family-groups/{id}/purchases/{purchaseId}/items/{itemId}',           [\App\Http\Controllers\Api\V1\Purchases\PurchaseItemController::class, 'destroy']);
    Route::get('family-groups/{id}/purchases/{purchaseId}',                             [\App\Http\Controllers\Api\V1\Purchases\PurchaseController::class, 'show']);
    Route::patch('family-groups/{id}/purchases/{purchaseId}',                           [\App\Http\Controllers\Api\V1\Purchases\PurchaseController::class, 'update']);
    Route::delete('family-groups/{id}/purchases/{purchaseId}',                          [\App\Http\Controllers\Api\V1\Purchases\PurchaseController::class, 'destroy']);
});

// Meal Plans — CRUD
Route::prefix('v1')->middleware(['web', 'trace_id', 'api_token', 'auth'])->group(function () {
    Route::get('family-groups/{id}/meal-plans',              [\App\Http\Controllers\Api\V1\MealPlans\MealPlanController::class, 'index']);
    Route::post('family-groups/{id}/meal-plans',             [\App\Http\Controllers\Api\V1\MealPlans\MealPlanController::class, 'store']);
    Route::get('family-groups/{id}/meal-plans/{planId}',     [\App\Http\Controllers\Api\V1\MealPlans\MealPlanController::class, 'show']);
    Route::patch('family-groups/{id}/meal-plans/{planId}',   [\App\Http\Controllers\Api\V1\MealPlans\MealPlanController::class, 'update']);
    Route::delete('family-groups/{id}/meal-plans/{planId}',  [\App\Http\Controllers\Api\V1\MealPlans\MealPlanController::class, 'destroy']);
});
