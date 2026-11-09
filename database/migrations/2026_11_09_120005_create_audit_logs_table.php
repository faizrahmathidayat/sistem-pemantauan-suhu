<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // Nama pengguna disalin (snapshot) dan user_id dilepas bila akun dihapus,
            // supaya riwayat tetap menunjukkan siapa pelakunya.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name');
            $table->string('event', 64);
            // Tanpa foreign key: log tetap ada walau data yang diubah (mis. zona) dihapus.
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_label');
            $table->json('diff');
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
