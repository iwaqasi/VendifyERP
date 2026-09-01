<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

class AddRepairPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissions = [
            'repair.create', 'repair.update', 'repair.view', 'repair.delete',
            'repair_status.update', 'repair_status.access',
        ];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
