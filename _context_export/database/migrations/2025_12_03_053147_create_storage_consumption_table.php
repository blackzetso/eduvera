<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('storage_consumption', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('files')->onDelete('cascade');
            $table->decimal('storage_gb', 10, 4)->default(0);
            $table->decimal('bandwidth_gb', 10, 4)->default(0);
            $table->decimal('bunny_cost', 10, 4)->default(0); // Cost from Bunny
            $table->decimal('platform_cost', 10, 4)->default(0); // Cost charged to tenant
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storage_consumption');
    }
};
