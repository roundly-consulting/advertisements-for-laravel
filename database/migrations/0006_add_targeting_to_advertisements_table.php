<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertisements', function (Blueprint $table): void {
            // Country allow/deny lists + optional radius centre, as a small JSON document
            // cast to a Targeting value object.
            $table->json('targeting')->nullable()->after('meta');

            // Denormalized centre coordinates so the radius bounding-box pre-filter runs in SQL.
            $table->decimal('target_latitude', 10, 7)->nullable()->after('targeting');
            $table->decimal('target_longitude', 10, 7)->nullable()->after('target_latitude');
        });
    }
};
