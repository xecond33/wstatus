<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encode_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('status')->default('queued');
            $table->string('mode');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->decimal('duration', 8, 2);
            $table->string('input_path');
            $table->json('meta')->nullable();
            $table->json('outputs')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encode_jobs');
    }
};
