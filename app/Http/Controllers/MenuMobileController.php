<?php

namespace App\Http\Controllers;

use App\Models\MenuMobile;
use Inertia\Inertia;

class MenuMobileController extends Controller
{
    public function index()
    {
        $menus = MenuMobile::orderBy('id')->get()->map(function ($menu) {
            return [
                'id' => $menu->id,
                'nama' => $menu->nama,
                'code' => $menu->code,
                'status' => (bool) $menu->status,
            ];
        });

        return Inertia::render('MenuMobile/Index', [
            'menus' => $menus,
        ]);
    }

    public function toggleStatus(MenuMobile $menu)
    {
        $newStatus = $menu->status ? 0 : 1;
        
        $menu->update([
            'status' => $newStatus,
        ]);

        return redirect()->route('menu-mobile.index');
    }
}
