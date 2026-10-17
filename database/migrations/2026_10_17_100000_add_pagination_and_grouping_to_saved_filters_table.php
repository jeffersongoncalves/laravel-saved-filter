<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('saved-filter.table', 'saved_filters'), function (Blueprint $table) {
            $table->string('records_per_page', 10)->nullable()->after('search');
            $table->string('grouping')->nullable()->after('records_per_page');
            $table->string('grouping_direction', 4)->nullable()->after('grouping');
        });
    }

    public function down(): void
    {
        Schema::table(config('saved-filter.table', 'saved_filters'), function (Blueprint $table) {
            $table->dropColumn(['records_per_page', 'grouping', 'grouping_direction']);
        });
    }
};
