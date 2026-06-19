<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisements', function (Blueprint $table): void {
            $table->id();
            $table->nullableMorphs('author');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category')->nullable()->index();
            $table->text('description')->nullable();
            $table->integer('price')->nullable();
            $table->string('currency')->nullable();
            $table->json('meta')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
