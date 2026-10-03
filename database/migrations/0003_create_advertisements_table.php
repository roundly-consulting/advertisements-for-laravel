<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\Sluggable\DataTransferObjects\SlugIndexSpec;
use RoundlyConsulting\Sluggable\Schema\SlugIndexes;

return new class extends Migration
{
    public function up(): void
    {
        // An unrecognized value throws, so a typo in the host's config fails the
        // migration loudly instead of keying the author column wrong.
        $keyType = KeyType::fromConfig('advertisements.key_type');

        Schema::create('advertisements', function (Blueprint $table) use ($keyType): void {
            $table->id();
            $table->morphKey('author', $keyType, nullable: true);
            $table->jsonb('name');
            $table->localizedSlug('slug');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('description')->nullable();
            // decimal(38,0) amount in minor units + a currency code column sized by
            // money.schema.currency_length (money-for-laravel's `money()` macro).
            $table->money('price', currency: 'currency', nullable: true);
            $table->jsonb('meta')->nullable();
            $table->unsignedBigInteger('impressions_count')->default(0);
            $table->unsignedBigInteger('clicks_count')->default(0);
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // One unique index per supported locale on `slug->{locale}`, trashed rows included
        // (they keep their slug reserved). Add a locale later with `sluggable:indexes`.
        SlugIndexes::ensure(SlugIndexSpec::localeMap('advertisements', 'slug'));
    }
};
