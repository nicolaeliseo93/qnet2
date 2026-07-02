<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CreateSuperAdminRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'roles:create-super-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or update the super-admin role';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $role = Role::firstOrCreate(['name' => 'super-admin']);

        $permissions = Permission::query()->pluck('name')->all();

        if ($permissions !== []) {
            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info(sprintf(
            'Role %s is ready with %d synced permissions.',
            $role->name,
            count($permissions)
        ));

        return self::SUCCESS;
    }
}
