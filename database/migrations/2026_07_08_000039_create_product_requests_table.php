<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateProductRequestsTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('product_requests')) {
            Schema::create('product_requests', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('requested_by_user_id')->unsigned();
                $table->integer('family_group_id')->unsigned()->nullable();
                $table->string('name', 200);
                $table->string('normalized_name', 200);
                $table->string('brand', 150)->nullable();
                $table->string('presentation', 150)->nullable();
                $table->string('barcode', 80)->nullable();
                $table->integer('unit_id')->unsigned()->nullable();
                $table->text('comment')->nullable();
                $table->string('source', 50)->default('user_request');
                $table->string('status', 30)->default('pending');
                $table->unsignedBigInteger('product_id')->nullable();
                $table->integer('reviewed_by_user_id')->unsigned()->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('status');
                $table->index('barcode');
                $table->index('requested_by_user_id');
                $table->index('family_group_id');
                $table->index(['requested_by_user_id', 'normalized_name', 'status']);
                $table->index(['requested_by_user_id', 'barcode', 'status']);

                $table->foreign('requested_by_user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('family_group_id')->references('id')->on('family_groups')->onDelete('set null');
                $table->foreign('unit_id')->references('id')->on('unit_measures')->onDelete('set null');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
                $table->foreign('reviewed_by_user_id')->references('id')->on('users')->onDelete('set null');
            });
        }

        $permission = [
            'code'        => 'web.admin.product-requests',
            'module'      => 'web.admin',
            'action'      => 'access',
            'description' => 'Acceder a pantalla admin: product-requests',
            'status'      => 'active',
        ];

        DB::table('permissions')->updateOrInsert(['code' => $permission['code']], $permission);

        $permissionId = DB::table('permissions')->where('code', $permission['code'])->value('id');
        $roleIds = DB::table('roles')->whereIn('code', ['catalog_admin', 'super_admin'])->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId],
                ['created_at' => now()]
            );
        }
    }

    public function down()
    {
        Schema::dropIfExists('product_requests');
    }
}
