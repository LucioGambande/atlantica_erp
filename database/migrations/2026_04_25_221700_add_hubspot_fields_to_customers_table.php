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
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('website')->nullable()->after('phone');
            $table->string('city')->nullable()->after('address');
            $table->string('postal_code')->nullable()->after('city');
            $table->string('country')->nullable()->after('postal_code');
            $table->string('hubspot_company_id')->nullable()->unique()->after('country');
            $table->timestamp('hubspot_last_modified_at')->nullable()->after('hubspot_company_id');
            $table->timestamp('last_synced_at')->nullable()->after('hubspot_last_modified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn([
                'website',
                'city',
                'postal_code',
                'country',
                'hubspot_company_id',
                'hubspot_last_modified_at',
                'last_synced_at',
            ]);
        });
    }
};
