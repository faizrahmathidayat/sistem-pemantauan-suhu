<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salinan terenkripsi (APP_KEY) dari kode monitor, supaya admin bisa melihat alamatnya lagi.
 * Pencarian tetap lewat monitor_token_hash. Hanya memakai Schema (tanpa model).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->text('monitor_token')->nullable()->after('monitor_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('monitor_token');
        });
    }
};
