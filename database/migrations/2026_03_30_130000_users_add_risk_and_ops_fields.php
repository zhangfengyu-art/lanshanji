<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UsersAddRiskAndOpsFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('session_version')->default(1)->after('is_enabled');
            $table->string('user_group', 50)->nullable()->after('session_version');
            $table->string('user_tags', 191)->nullable()->after('user_group');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['session_version', 'user_group', 'user_tags']);
        });
    }
}
