<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('color', 7)->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();

            $table->unique(['company_id', 'pipeline_id', 'name']);
            $table->index(['company_id', 'pipeline_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_stages');
    }
};
