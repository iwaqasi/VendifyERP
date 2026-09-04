<?php

namespace App\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LandingController extends Controller
{
    protected $moduleUtil;

    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display the VendifyERP landing page.
     */
    public function index()
    {
        // If user is already logged in, redirect to dashboard
        if (Auth::check()) {
            return redirect('/home');
        }

        $packages = [];
        $permission_formatted = [];

        // Try to load packages from Superadmin module
        if ($this->moduleUtil->isSuperadminInstalled()) {
            try {
                $packageClass = '\\Modules\\Superadmin\\Entities\\Package';
                if (class_exists($packageClass)) {
                    $packages = $packageClass::listPackages(true);

                    // Get module permissions for package features
                    $permissions = $this->moduleUtil->getModuleData('superadmin_package');
                    foreach ($permissions as $permission) {
                        foreach ($permission as $details) {
                            $permission_formatted[$details['name']] = $details['label'];
                        }
                    }
                }
            } catch (\Exception $e) {
                // Silently handle - packages section will be hidden
            }
        }

        return view('landing', compact('packages', 'permission_formatted'));
    }
}
