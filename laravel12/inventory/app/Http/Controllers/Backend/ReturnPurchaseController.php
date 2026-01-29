<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ReturnPurchase;
use App\Models\ReturnPurchaseItem;
use App\Models\Supplier;
use App\Models\WareHouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class ReturnPurchaseController extends Controller
{
    public function AllReturnPurchase(){
        $purchases = ReturnPurchase::orderBy('id', 'desc')->get();

        return view('admin.backend.return-purchase.all_return_purchase', compact('purchases'));
    }

    public function AddReturnPurchase(){
        $suppliers = Supplier::all();
        $warehouses = WareHouse::all();
        return view('admin.backend.return-purchase.add_return_purchase', compact('suppliers', 'warehouses'));
    }

    public function StoreReturnPurchase(Request $request){
        $request->validate([
            'date' => 'required|date',
            'status' => "required",
            'supplier_id' => "required",
        ]);

        try{

            DB::beginTransaction();

            $grandTotal = 0;

            $purchase = ReturnPurchase::create([
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

                ReturnPurchaseItem::create([
                    'return_purchase_id' => $purchase->id,
                    'product_id' => $productData['id'],
                    'net_unit_cost' => $netUnitCost,
                    'stock' => $product->product_qty + $productData['quantity'],
                    'quantity' => $productData['quantity'],
                    'discount' => $productData['discount'] ?? 0,
                    'subtotal' => $subtotal
                ]);

                $product->decrement('product_qty', $productData['quantity']);
            }

            $purchase->update(['grand_total' =>$grandTotal + $request->shipping - $request->discount]);

            DB::commit();

            $notif = array(
                'message' => "Purchase Stored Successfully",
                'alert-type' => 'success'
            );
            return redirect()->route('all.return.purchase')->with($notif);

        }catch(\Throwable $e){
            DB::rollBack();
            return response()->json(['error'=>$e->getMessage()], 500);
        }
    }

        public function DetailsReturnPurchase($id){
            $purchase = ReturnPurchase::with(['supplier','purchaseItems.product'])
            ->find($id);

            return view('admin.backend.return-purchase.return_purchase_details', compact(
                'purchase'
            ));
        }


        public function InvoiceReturnPurchase($id){
            $purchase = ReturnPurchase::with(['supplier','warehouse' ,'purchaseItems.product'])
            ->find($id);

            $pdf = Pdf::loadView('admin.backend.return-purchase.invoice_pdf', compact('purchase'));
            $pdf->setPaper('A4', 'portrait');
            return $pdf->download('purchase_invoice_'.$purchase->id.'.pdf');
        }


    public function EditReturnPurchase($id) {
        $purchase = ReturnPurchase::with('purchaseItems.product')->findOrFail($id);
        $warehouses = WareHouse::all();
        $suppliers = Supplier::all();
        return view('admin.backend.return-purchase.edit_return_purchase', compact(
            'purchase', 'warehouses', 'suppliers'
        ));
    }

    public function UpdateReturnPurchase(Request $request, $id){
        $request->validate([
            'date' => 'required|date',
            'status' => "required",
        ]);

        // dd($request);

            DB::beginTransaction();

            try{

            $purchase = ReturnPurchase::findOrFail($id);

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
            $oldPurchaseItems = ReturnPurchaseItem::where('return_purchase_id', $purchase->id)->get();

            // Loop for old purchase items and decremnt product qty
            foreach($oldPurchaseItems as $oldItem){
                $product = Product::find($oldItem->product_id);

                if($product){
                    $product->increment('product_qty', $oldItem->quantity);
                    // Increment old quantity
                }
            }

            // Delete old Purchase Items
            ReturnPurchaseItem::where('return_purchase_id', $purchase->id)->delete();

            // Loop for new products and insert new purchase items

            foreach($request->products as $product_id=>$productData){
                ReturnPurchaseItem::create([
                    'return_purchase_id' => $purchase->id,
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
                    $product->decrement('product_qty', $productData['quantity']);
                    // Decrement new quantity
                }

            }
            DB::commit();

            $notif = array(
                'message' => "Return Purchase Stored Successfully",
                'alert-type' => 'success'
            );
            return redirect()->route('all.return.purchase')->with($notif);

            }catch(\Throwable $e){
                DB::rollBack();
                return response()->json(['error'=>$e->getMessage()], 500);
            }


        }


        public function DeleteReturnPurchase($id){
            DB::beginTransaction();

            try{
                $purchase = ReturnPurchase::findOrFail($id);

                // Get purchase items
                $purchaseItems = ReturnPurchaseItem::where('return_purchase_id', $purchase->id)->get();

                // Loop for purchase items and decrement product qty
                foreach($purchaseItems as $item){
                    $product = Product::find($item->product_id);
                    if($product){
                        $product->increment('product_qty', $item->quantity);
                    }
                }

                // Delete purchase items
                ReturnPurchaseItem::where('return_purchase_id', $purchase->id)->delete();

                // Delete purchase
                $purchase->delete();

                DB::commit();

                $notif = array(
                    'message' => "Return Purchase Deleted Successfully",
                    'alert-type' => 'success'
                );
                return redirect()->route('all.return.purchase')->with($notif);

            }catch(\Exception $e){
                DB::rollBack();
                return response()->json(['error'=>$e->getMessage()], 500);
            }
        }

}
