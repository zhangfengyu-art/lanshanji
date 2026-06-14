<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPasswordChangedAtToAdminUsersTable extends Migration
{
    public function up()
    {
        $table = config('admin.database.users_table', 'admin_users');

        if (!Schema::hasTable($table) || Schema::hasColumn($table, 'password_changed_at')) {
            return;
        }

        Schema::table($table, function (Blueprint $table) {
            $table->timestamp('password_changed_at')->nullable()->after('remember_token');
        });
    }

    public function down()
    {
        $table = config('admin.database.users_table', 'admin_users');

        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'password_changed_at')) {
            return;
        }

        Schema::table($table, function (Blueprint $table) {
            $table->dropColumn('password_changed_at');
        });
    }
}
