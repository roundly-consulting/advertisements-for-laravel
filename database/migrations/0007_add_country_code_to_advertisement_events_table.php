<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertisement_events', function (Blueprint $table): void {
            // The viewer's resolved ISO country, indexed for per-country reporting. Region,
            // city, and coordinates live in the event `meta` payload.
            $table->string('country_code', 2)->nullable()->index()->after('placement_id');
        });
    }
};
