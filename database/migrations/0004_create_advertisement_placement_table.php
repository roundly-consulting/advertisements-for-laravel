<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisement_placement', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('advertisement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('placement_id')->constrained()->cascadeOnDelete();
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->unique(['advertisement_id', 'placement_id']);
        });
    }
};
