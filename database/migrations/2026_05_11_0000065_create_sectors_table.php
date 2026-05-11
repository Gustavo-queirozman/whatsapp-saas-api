<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sectors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sectors');
    }
};
