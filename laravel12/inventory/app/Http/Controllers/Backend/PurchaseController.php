<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\WareHouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class PurchaseController extends Controller
{
    public function AllPurchase(){
        $purchases = Purchase::orderBy('id', 'desc')->get();

        return view('admin.backend.purchase.all_purchase', compact('purchases'));
    }

    public function AddPurchase(){
        $suppliers = Supplier::all();
        $warehouses = WareHouse::all();
        return view('admin.backend.purchase.add_purchase', compact('suppliers', 'warehouses'));
    }

    public function PurchaseProductSearch(Request $request){
        $query = $request->input('query');
        $warehouse_id = $request->input('warehouse_id');

        // $product = Product::all();
        $products = Product::where(function($q) use ($query) {
            $q->where('name', 'LIKE', "%{$query}%")
              ->orWhere('code', 'LIKE', "%{$query}%");
        })
        ->when('warehouse_id', function($q) use ($warehouse_id) {
            $q->where('warehouse_id', $warehouse_id);
        })
        ->select('id', 'name', 'code', 'price', 'product_qty')
        ->limit(10)
        ->get();

        return response()->json($products);
    }

    public function StorePurchase(Request $request){
        $request->validate([
            'date' => 'required|date',
            'status' => "required",
            'supplier_id' => "required",
        ]);

        try{

            DB::beginTransaction();

            $grandTotal = 0;

            $purchase = Purchase::create([
                'date' => $request->date,
                'warehouse_id' => $request->warehouse_id,
                'status' => $request->status,
                'discount' => $request->discount ?? 0,
                'note' => $request->note,
                'supplier_id' => $request->supplier_id,
                'grand_total' => 0,
                'shipping' => $request->shipping ?? 0,
            ]);

            // Store Purchase Items & Updaate Stock
            foreach($request->products as $productData){
                $product = Product::findOrFail($productData['id']);
                $netUnitCost = $productData['net_unit_cost'] ?? $product->price;

                if($netUnitCost === null) {
                    throw new \Exception("Net Unit Cost is missing for the product id " . $productData['id']);
                }

                $subtotal = ($netUnitCost * $productData['quantity']) - ($productData['discount'] ?? 0);
                $grandTotal += $subtotal;

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $productData['id'],
                    'net_unit_cost' => $netUnitCost,
                    'stock' => $product->product_qty + $productData['quantity'],
                    'quantity' => $productData['quantity'],
                    'discount' => $productData['discount'] ?? 0,
                    'subtotal' => $subtotal
                ]);

                $product->increment('product_qty', $productData['quantity']);
            }

            $purchase->update(['grand_total' =>$grandTotal + $request->shipping - $request->discount]);

            DB::commit();

            $notif = array(
                'message' => "Purchase Stored Successfully",
                'alert-type' => 'success'
            );
            return redirect()->route('all.purchase')->with($notif);

        }catch(\Throwable $e){
            DB::rollBack();
            return response()->json(['error'=>$e->getMessage()], 500);
        }
    }

    public function EditPurchase($id) {
        $purchase = Purchase::with('purchaseItems.product')->findOrFail($id);
        $warehouses = WareHouse::all();
        $suppliers = Supplier::all();
        return view('admin.backend.purchase.edit_purchase', compact(
            'purchase', 'warehouses', 'suppliers'
        ));
    }


    public function UpdatePurchase(Request $request, $id){
        $request->validate([
            'date' => 'required|date',
            'status' => "required",
        ]);

        // dd($request);

            DB::beginTransaction();

            try{

            $purchase = Purchase::findOrFail($id);

            $purchase->update([
                'date' => $request->date,
                'warehouse_id' => $request->warehouse_id,
                'status' => $request->status,
                'discount' => $request->discount ?? 0,
                'note' => $request->note,
                'supplier_id' => $request->supplier_id,
                'grand_total' => $request->grand_total,
                'shipping' => $request->shipping ?? 0,
            ]);

            // Get old purchase items
            $oldPurchaseItems = PurchaseItem::where('purchase_id', $purchase->id)->get();

            // Loop for old purchase items and decremnt product qty
            foreach($oldPurchaseItems as $oldItem){
                $product = Product::find($oldItem->product_id);

                if($product){
                    $product->decrement('product_qty', $oldItem->quantity);
                    // Decrement old quantity
                }
            }

            // Delete old Purchase Items
            PurchaseItem::where('purchase_id', $purchase->id)->delete();

            // Loop for new products and insert new purchase items

            foreach($request->products as $product_id=>$productData){
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $product_id,
                    'net_unit_cost' => $productData['net_unit_cost'],
                    'stock' => $productData['stock'],
                    'quantity' => $productData['quantity'],
                    'discount' => $productData['discount'] ?? 0,
                    'subtotal' => $productData['subtotal'],
                ]);

                // Update product stock by increenting the new quantity
                $product = Product::find($product_id);
                if($product){
                    $product->increment('product_qty', $productData['quantity']);
                    // Increment new quantity
                }

            }
            DB::commit();

            $notif = array(
                'message' => "Purchase Stored Successfully",
                'alert-type' => 'success'
            );
            return redirect()->route('all.purchase')->with($notif);

            }catch(\Throwable $e){
                DB::rollBack();
                return response()->json(['error'=>$e->getMessage()], 500);
            }
        }

        public function DetailsPurchase($id){
            $purchase = Purchase::with(['supplier','purchaseItems.product'])
            ->find($id);

            return view('admin.backend.purchase.purchasse_details', compact(
                'purchase'
            ));
        }


        public function InvoicePurchase($id){
            $purchase = Purchase::with(['supplier','warehouse' ,'purchaseItems.product'])
            ->find($id);

            $pdf = Pdf::loadView('admin.backend.purchase.invoice_pdf', compact('purchase'));
            $pdf->setPaper('A4', 'portrait');
            return $pdf->download('purchase_invoice_'.$purchase->id.'.pdf');
        }

        public function DeletePurchase($id){
            DB::beginTransaction();

            try{
                $purchase = Purchase::findOrFail($id);

                // Get purchase items
                $purchaseItems = PurchaseItem::where('purchase_id', $purchase->id)->get();

                // Loop for purchase items and decrement product qty
                foreach($purchaseItems as $item){
                    $product = Product::find($item->product_id);
                    if($product){
                        $product->decrement('product_qty', $item->quantity);
                    }
                }

                // Delete purchase items
                PurchaseItem::where('purchase_id', $purchase->id)->delete();

                // Delete purchase
                $purchase->delete();

                DB::commit();

                $notif = array(
                    'message' => "Purchase Deleted Successfully",
                    'alert-type' => 'success'
                );
                return redirect()->route('all.purchase')->with($notif);

            }catch(\Exception $e){
                DB::rollBack();
                return response()->json(['error'=>$e->getMessage()], 500);
            }
        }
}
