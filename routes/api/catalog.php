<?php

use Illuminate\Support\Facades\Route;

// Units
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

// Brands
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

// Product categories
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

// Products — admin
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

// Products — catalog (specific routes before wildcard {id})
Route::get('products/barcode/{barcode}', [\App\Http\Controllers\Api\V1\Products\ProductCatalogController::class, 'findByBarcode']);
Route::get('products/{id}/nutrition',    [\App\Http\Controllers\Api\V1\Products\ProductCatalogController::class, 'nutrition']);
Route::get('products/{id}/prices',       [\App\Http\Controllers\Api\V1\Products\ProductCatalogController::class, 'prices']);
Route::get('products/{id}/alternatives', [\App\Http\Controllers\Api\V1\Products\ProductCatalogController::class, 'alternatives']);
Route::post('products/{id}/request-price-refresh', [\App\Http\Controllers\Api\V1\PriceRefreshRequests\PriceRefreshRequestController::class, 'store']);
Route::post('products/{id}/reports',     [\App\Http\Controllers\Api\V1\ProductReports\ProductReportController::class, 'store']);
Route::get('products/{id}',              [\App\Http\Controllers\Api\V1\Products\ProductCatalogController::class, 'show']);
Route::get('products',                   [\App\Http\Controllers\Api\V1\Products\ProductCatalogController::class, 'index']);
Route::get('users/me/price-refresh-requests', [\App\Http\Controllers\Api\V1\PriceRefreshRequests\PriceRefreshRequestController::class, 'mine']);

// Product reports — admin
Route::get('admin/product-reports', [\App\Http\Controllers\Api\V1\ProductReports\AdminProductReportController::class, 'index'])
    ->middleware('permission:catalog.manage');
Route::patch('admin/product-reports/{id}/resolve', [\App\Http\Controllers\Api\V1\ProductReports\AdminProductReportController::class, 'resolve'])
    ->middleware('permission:catalog.manage');

// Product barcodes — admin
Route::post('admin/products/{id}/barcodes', [\App\Http\Controllers\Api\V1\Products\AdminProductBarcodeController::class, 'store'])
    ->middleware('permission:catalog.manage');
Route::delete('admin/products/{id}/barcodes/{barcodeId}', [\App\Http\Controllers\Api\V1\Products\AdminProductBarcodeController::class, 'destroy'])
    ->middleware('permission:catalog.manage');

// Product images — admin
Route::post('admin/products/{id}/images', [\App\Http\Controllers\Api\V1\Products\AdminProductImageController::class, 'store'])
    ->middleware('permission:catalog.manage');
Route::delete('admin/products/{id}/images/{imageId}', [\App\Http\Controllers\Api\V1\Products\AdminProductImageController::class, 'destroy'])
    ->middleware('permission:catalog.manage');

// Payment methods — admin, catalog and user
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

Route::get('users/me/payment-methods',      [\App\Http\Controllers\Api\V1\PaymentMethods\UserPaymentMethodController::class, 'index']);
Route::post('users/me/payment-methods',     [\App\Http\Controllers\Api\V1\PaymentMethods\UserPaymentMethodController::class, 'store']);
Route::delete('users/me/payment-methods/{id}', [\App\Http\Controllers\Api\V1\PaymentMethods\UserPaymentMethodController::class, 'destroy']);

// Promotions — admin and catalog
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
Route::get('promotions', [\App\Http\Controllers\Api\V1\Promotions\PromotionCatalogController::class, 'index']);

// Supermarket products — admin and catalog
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

Route::get('products/{productId}/best-price',         [\App\Http\Controllers\Api\V1\SupermarketProducts\ProductPriceController::class, 'bestPrice']);
Route::get('products/{productId}/supermarket-prices', [\App\Http\Controllers\Api\V1\SupermarketProducts\ProductPriceController::class, 'supermarketPrices']);
Route::get('products/{productId}/price-history',      [\App\Http\Controllers\Api\V1\SupermarketProducts\ProductPriceController::class, 'priceHistory']);

// Supermarket branches — admin and catalog
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

// nearby before {id} to avoid wildcard shadowing
Route::get('supermarket-branches/nearby', [\App\Http\Controllers\Api\V1\SupermarketBranches\SupermarketBranchCatalogController::class, 'nearby']);
Route::get('supermarket-branches/{id}',   [\App\Http\Controllers\Api\V1\SupermarketBranches\SupermarketBranchCatalogController::class, 'show']);
Route::get('supermarket-branches',        [\App\Http\Controllers\Api\V1\SupermarketBranches\SupermarketBranchCatalogController::class, 'index']);

// Supermarket chains — admin and catalog
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
Route::get('supermarkets',      [\App\Http\Controllers\Api\V1\Supermarkets\SupermarketCatalogController::class, 'index']);

// Cities — admin and catalog
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

// Food tags — admin and catalog
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
