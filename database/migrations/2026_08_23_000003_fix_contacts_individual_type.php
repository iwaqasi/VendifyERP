<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Set contact_type to 'individual' for all null values
        DB::table('contacts')->whereNull('contact_type')->update(['contact_type' => 'individual']);

        // Split name into first_name and last_name where they are null
        $contacts = DB::table('contacts')->whereNull('first_name')->get();
        foreach ($contacts as $contact) {
            $parts = explode(' ', trim($contact->name), 2);
            DB::table('contacts')
                ->where('id', $contact->id)
                ->update([
                    'first_name' => $parts[0] ?? '',
                    'last_name' => $parts[1] ?? '',
                ]);
        }
    }

    public function down(): void
    {
        // No rollback needed
    }
};
