<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Passport\Client;

class SetupApiAuth extends Command
{
    protected $signature = 'pos:setup-api-auth';
    protected $description = 'Set up Laravel Passport for VendifyPOS API authentication';

    public function handle()
    {
        $this->info('Setting up VendifyPOS API Authentication...');
        $this->newLine();

        // Step 1: Run Passport migrations
        $this->info('Step 1: Running Passport migrations...');
        $this->call('passport:install', ['--force' => true]);
        $this->newLine();

        // Step 2: Create Personal Access Client if not exists
        $this->info('Step 2: Creating Personal Access Client...');
        $client = Client::where('name', 'VendifyPOS App')
            ->where('personal_access_client', true)
            ->first();

        if (!$client) {
            $client = Client::create([
                'name' => 'VendifyPOS App',
                'personal_access_client' => true,
                'password_client' => false,
                'redirect' => '',
            ]);
            $this->info('   Personal Access Client created: ' . $client->id);
        } else {
            $this->info('   Personal Access Client already exists: ' . $client->id);
        }

        // Step 3: Output the client secret
        $this->newLine();
        $this->info('Step 3: Configuration');
        $this->newLine();
        $this->warn('Add these to your .env file:');
        $this->line('PASSPORT_PERSONAL_ACCESS_CLIENT_ID=' . $client->id);
        $this->line('PASSPORT_PERSONAL_ACCESS_CLIENT_SECRET=' . $client->secret);
        $this->newLine();

        // Step 4: Create a test user if needed
        $this->info('Step 4: Creating test API user...');
        $testUser = \App\User::where('email', 'pos@vendifypos.com')->first();
        if (!$testUser) {
            $testUser = \App\User::create([
                'username' => 'pos_operator',
                'email' => 'pos@vendifypos.com',
                'password' => bcrypt('pos123'),
                'first_name' => 'POS',
                'last_name' => 'Operator',
                'business_id' => 1,
                'user_type' => 'user',
                'is_active' => 1,
                'allow_login' => 1,
                'status' => 'active',
            ]);
            $this->info('   Test user created: pos@vendifypos.com / pos123');
        } else {
            $this->info('   Test user already exists: ' . $testUser->email);
        }

        // Assign POS role if it exists
        if (\Spatie\Permission\Models\Role::where('name', 'POS Operator')->exists()) {
            $testUser->assignRole('POS Operator');
            $this->info('   Assigned "POS Operator" role');
        }

        $this->newLine();
        $this->info('=== Setup Complete ===');
        $this->newLine();

        $this->info('API Endpoints:');
        $this->line('   Login:    POST /api/v1/auth/login');
        $this->line('   Products: GET  /api/v1/products');
        $this->line('   Sells:    POST /api/v1/sells');
        $this->newLine();

        $this->info('Test with curl:');
        $this->line('   curl -X POST http://localhost:8000/api/v1/auth/login \\');
        $this->line('     -H "Content-Type: application/json" \\');
        $this->line('     -d \'{"email":"pos@vendifypos.com","password":"pos123","business_id":1}\'');

        return Command::SUCCESS;
    }
}
