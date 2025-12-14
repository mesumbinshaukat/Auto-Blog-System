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
        Schema::create('blog_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_id')->constrained()->onDelete('cascade');
            $table->string('ip_address', 45); // IPv4/IPv6
            $table->text('user_agent')->nullable();
            $table->string('referer')->nullable();
            $table->string('country_code', 2)->default('XX'); // ISO 3166-1 alpha-2
            $table->integer('read_time_seconds')->default(0); // Estimated or tracked
            $table->timestamps();

            // Index for dashboard query performance
            $table->index(['blog_id', 'created_at']);
            $table->index('created_at');
            $table->index('country_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_views');
    }
};
