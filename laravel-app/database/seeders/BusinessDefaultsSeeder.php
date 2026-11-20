<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Services\BusinessSetupService;
use Illuminate\Database\Seeder;

class BusinessDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $setup = app(BusinessSetupService::class);

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($setup): void {
            $owner = $tenant->users()->wherePivot('role', 'owner')->first()
                ?? $tenant->users()->orderBy('users.id')->first();
            $setup->provision($tenant, $owner);
        });
    }
}
