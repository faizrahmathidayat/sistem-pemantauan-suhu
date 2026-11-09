<?php

use App\Support\StarterRoles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 64);

            $table->unique(['role_id', 'permission']);
        });

        Schema::table('users', function (Blueprint $table) {
            // restrictOnDelete: role yang masih dipakai tidak boleh hilang diam-diam.
            $table->foreignId('role_id')->nullable()->after('password')->constrained()->restrictOnDelete();
        });

        // Membuat role awal dan menjadikan semua user yang ada sebagai Administrator.
        StarterRoles::install();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
    }
};
