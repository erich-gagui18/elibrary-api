<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('gutenberg_id')->nullable()->unique();
            $table->string('title');
            $table->string('author')->index();
            $table->string('category', 120)->index();
            $table->text('description')->nullable();
            $table->string('cover_url', 500)->nullable();
            $table->string('file_url', 500);
            $table->string('language', 10)->default('en');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
