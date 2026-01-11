<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\WareHouse;
use Illuminate\Http\Request;

class WareHouseController extends Controller
{
    public function AllWareHouse()
    {
        $warehouses = WareHouse::latest()->get();
        return view('admin.backend.warehouse.all_warehouse', compact('warehouses'));
    }

    public function AddWareHouse()
    {
        return view('admin.backend.warehouse.add_warehouse');
    }

    public function StoreWareHouse(Request $request){

        $validatedData = $request->validate([
            'name' => 'required|unique:ware_houses|max:255',
            'email' => 'nullable|email|unique:ware_houses,email|max:255',
            'phone' => 'nullable|max:20',
            'city' => 'nullable',
        ]);

            WareHouse::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'phone' => $validatedData['phone'],
                'city' => $validatedData['city'],
            ]);

            $notification = array(
                'message' => 'Warehouse Inserted Successfully',
                'alert-type' => 'success'
            );
            return redirect()->route('all.warehouse')->with($notification);

    }

    // Edit Warehouse
    public function EditWarehouse($id){
        $warehouse = WareHouse::findOrFail($id);
        return view('admin.backend.warehouse.edit_warehouse', compact('warehouse'));
    }

    // Update Warehouse
    public function UpdateWarehouse(Request $request){
        $warehouse_id = $request->id;

        $validatedData = $request->validate([
            'name' => 'required|max:255|unique:ware_houses,name,'.$warehouse_id,
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|max:20',
            'city' => 'nullable',
        ]);

        $warehouse = WareHouse::findOrFail($warehouse_id);
        $warehouse->update([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'phone' => $validatedData['phone'],
            'city' => $validatedData['city'],
        ]);
        $notif = array(
            'message' => 'Warehouse Updated Successfully',
            'alert-type' => 'success'
        );

        return redirect()->route('all.warehouse')->with($notif);
    }

    // Delete Warehouse
    public function DeleteWarehouse($id){
        $warehouse = WareHouse::findOrFail($id);

        $warehouse->delete();

        $notif = array(
            'message' => 'Warehouse Deleted Successfully',
            'alert-type' => 'success'
        );

        return redirect()->back()->with($notif);
    }
}
