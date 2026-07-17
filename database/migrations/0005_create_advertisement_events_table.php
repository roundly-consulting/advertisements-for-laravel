<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisement_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('advertisement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('placement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index();
            $table->timestamp('occurred_at')->index();
            $table->jsonb('meta')->nullable();
            $table->timestamps();
        });
    }
};
