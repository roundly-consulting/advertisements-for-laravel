<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

return new class extends Migration
{
    public function up(): void
    {
        // Silently falls back to bigint for an unrecognized value, so a typo in
        // the host's config never leaves the package unable to migrate.
        $keyType = KeyType::fromConfig('advertisements.key_type');

        Schema::create('advertisements', function (Blueprint $table) use ($keyType): void {
            $table->id();
            $table->morphKey('author', $keyType, nullable: true);
            $table->jsonb('name');
            $table->jsonb('slug');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('description')->nullable();
            $table->integer('price')->nullable();
            $table->string('currency')->nullable();
            $table->jsonb('meta')->nullable();
            $table->unsignedBigInteger('impressions_count')->default(0);
            $table->unsignedBigInteger('clicks_count')->default(0);
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
