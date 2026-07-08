<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddManualReviewFieldsToProducts extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'origin')) {
                $table->string('origin', 40)->default('catalog')->after('status');
            }

            if (! Schema::hasColumn('products', 'created_by_user_id')) {
                $table->integer('created_by_user_id')->unsigned()->nullable()->after('origin');
            }

            if (! Schema::hasColumn('products', 'family_group_id')) {
                $table->integer('family_group_id')->unsigned()->nullable()->after('created_by_user_id');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'origin')) {
                $table->index(['origin', 'status']);
            }

            if (Schema::hasColumn('products', 'family_group_id')) {
                $table->index(['family_group_id', 'status']);
            }
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'family_group_id')) {
                $table->dropIndex(['family_group_id', 'status']);
            }

            if (Schema::hasColumn('products', 'origin')) {
                $table->dropIndex(['origin', 'status']);
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'family_group_id')) {
                $table->dropColumn('family_group_id');
            }

            if (Schema::hasColumn('products', 'created_by_user_id')) {
                $table->dropColumn('created_by_user_id');
            }

            if (Schema::hasColumn('products', 'origin')) {
                $table->dropColumn('origin');
            }
        });
    }
}
