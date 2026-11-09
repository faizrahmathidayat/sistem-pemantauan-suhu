<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gudang → Zona → Produk. Hanya memakai Schema/DB (tanpa model) agar tetap bisa dijalankan
 * ulang walau model berubah di kemudian hari.
 */
return new class extends Migration
{
    private const PRODUCT_LABELS = ['telur' => 'Telur', 'buah' => 'Buah', 'sayur' => 'Sayur'];

    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('warehouse_user', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['warehouse_id', 'user_id']);
        });

        Schema::create('zone_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedSmallInteger('position')->default(0);
        });

        Schema::table('zones', function (Blueprint $table) {
            $table->unsignedBigInteger('warehouse_id')->nullable()->after('id');
            // FK (restrictOnDelete) hanya di database selain SQLite: di SQLite penambahan FK membangun
            // ulang tabel `zones`, dan FK cascade dari insiden/produk ikut menghapus datanya. Di SQLite
            // aturan "gudang yang masih punya zona tidak boleh dihapus" ditegakkan di aplikasi.
            if (! $this->isSqlite()) {
                $table->foreign('warehouse_id')->references('id')->on('warehouses')->restrictOnDelete();
            }
        });

        $this->backfill();

        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn('product_type');
        });
        // warehouse_id tetap NULLABLE di tingkat database: mengubahnya menjadi NOT NULL di SQLite
        // membangun ulang tabel `zones`, dan FK cascade ikut menghapus insiden dan produk.
        // "Wajib" ditegakkan di aplikasi (model, form, factory).
        Schema::table('zones', function (Blueprint $table) {
            $table->unique(['warehouse_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->dropUnique(['warehouse_id', 'name']);
        });
        Schema::table('zones', function (Blueprint $table) {
            $table->string('product_type')->default('sayur');
            if ($this->isSqlite()) {
                $table->dropColumn('warehouse_id');
            } else {
                $table->dropConstrainedForeignId('warehouse_id');
            }
        });
        Schema::dropIfExists('zone_products');
        Schema::dropIfExists('warehouse_user');
        Schema::dropIfExists('warehouses');
    }

    private function isSqlite(): bool
    {
        return DB::getDriverName() === 'sqlite';
    }

    /** Zona lama masuk ke "Gudang Utama"; jenis produk lama menjadi satu produk per zona. */
    private function backfill(): void
    {
        $zones = DB::table('zones')->get(['id', 'product_type']);
        if ($zones->isEmpty()) {
            return;
        }

        $now = now();
        $warehouseId = DB::table('warehouses')->insertGetId(['name' => 'Gudang Utama', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('zones')->update(['warehouse_id' => $warehouseId]);

        foreach ($zones as $zone) {
            DB::table('zone_products')->insert([
                'zone_id' => $zone->id,
                'name' => self::PRODUCT_LABELS[$zone->product_type] ?? ucfirst((string) $zone->product_type),
                'position' => 0,
            ]);
        }

        // Administrator melihat semua gudang; user lain di-assign agar tidak kehilangan tampilan.
        $administratorRoles = DB::table('roles')->where('is_system', true)->pluck('id');
        $userIds = DB::table('users')
            ->where(fn ($query) => $query->whereNull('role_id')->orWhereNotIn('role_id', $administratorRoles))
            ->pluck('id');
        foreach ($userIds as $userId) {
            DB::table('warehouse_user')->insert(['warehouse_id' => $warehouseId, 'user_id' => $userId]);
        }
    }
};
