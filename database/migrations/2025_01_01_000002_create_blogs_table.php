<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blogs', function (Blueprint $table) {
            $table->id();
            $table->text('title');
            $table->string('slug')->unique();
            $table->longText('content');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('tags_json')->nullable();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();

            // Index for faster lookups
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blogs');
    }
};
