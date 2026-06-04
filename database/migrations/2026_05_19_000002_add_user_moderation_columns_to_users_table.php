<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUserModerationColumnsToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_enabled')) {
                $table->boolean('is_enabled')->default(true)->after('email_verified');
            }
            if (!Schema::hasColumn('users', 'session_version')) {
                $table->unsignedInteger('session_version')->default(0)->after('is_enabled');
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'session_version')) {
                $table->dropColumn('session_version');
            }
            if (Schema::hasColumn('users', 'is_enabled')) {
                $table->dropColumn('is_enabled');
            }
        });
    }
}
