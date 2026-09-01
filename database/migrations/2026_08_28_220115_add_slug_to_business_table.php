<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name')->unique();
        });

        // Backfill existing businesses with slugs
        $businesses = DB::table('business')->get();
        foreach ($businesses as $business) {
            $slug = Str::slug($business->name);
            $originalSlug = $slug;
            $counter = 1;

            // Ensure uniqueness
            while (DB::table('business')->where('slug', $slug)->where('id', '!=', $business->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            DB::table('business')->where('id', $business->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('business', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
