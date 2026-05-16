<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_stream_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_stream_id')->constrained('live_streams')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('live_stream_comments')->cascadeOnDelete();
            $table->string('author_name', 100);
            $table->string('author_email', 150)->nullable();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_stream_comments');
    }
};
