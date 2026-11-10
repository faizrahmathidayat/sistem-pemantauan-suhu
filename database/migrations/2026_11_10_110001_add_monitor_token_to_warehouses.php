<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kode rahasia untuk halaman monitor publik (TV gudang). Hanya hash sha256 yang disimpan.
 * Hanya memakai Schema (tanpa model) agar tetap bisa dijalankan ulang bila model berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('monitor_token_hash', 64)->nullable()->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropUnique(['monitor_token_hash']);
            $table->dropColumn('monitor_token_hash');
        });
    }
};
