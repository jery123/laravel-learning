<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\WareHouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class SaleController extends Controller
{
    public function AllSales()
    {
        $sales = Sale::orderBy('id', 'desc')->get();

        return view('admin.backend.sales.all_sales', compact('sales'));
    }

    public function AddSales()
    {
        $customers = Customer::all();
        $warehouses = WareHouse::all();

        return view('admin.backend.sales.add_sale', compact('customers', 'warehouses'));
    }

    public function StoreSale(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'status' => 'required',
            'customer_id' => 'required',
        ]);

        try {

            DB::beginTransaction();

            $grandTotal = 0;

            $sale = Sale::create([
                'date' => $request->date,
                'warehouse_id' => $request->warehouse_id,
                'status' => $request->status,
                'discount' => $request->discount ?? 0,
                'note' => $request->note,
                'customer_id' => $request->customer_id,
                'grand_total' => 0,
                'shipping' => $request->shipping ?? 0,
                'paid_amount' => $request->paid_amount ?? 0,
                'due_amount' => $request->due_amount ?? 0,
            ]);

            // Store Sale Items & Update Stock
            foreach ($request->products as $productData) {
                $product = Product::findOrFail($productData['id']);
                $netUnitCost = $productData['net_unit_cost'] ?? $product->price;

                if ($netUnitCost === null) {
                    throw new \Exception('Net Unit Cost is missing for the product id '.$productData['id']);
                }

                $subtotal = ($netUnitCost * $productData['quantity']) - ($productData['discount'] ?? 0);
                $grandTotal += $subtotal;

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $productData['id'],
                    'net_unit_cost' => $netUnitCost,
                    'stock' => $product->product_qty + $productData['quantity'],
                    'quantity' => $productData['quantity'],
                    'discount' => $productData['discount'] ?? 0,
                    'subtotal' => $subtotal,
                ]);

                $product->decrement('product_qty', $productData['quantity']);
            }

            $sale->update(['grand_total' => $grandTotal + $request->shipping - $request->discount]);

            DB::commit();

            $notif = [
                'message' => 'Sale Stored Successfully',
                'alert-type' => 'success',
            ];

            return redirect()->route('all.sale')->with($notif);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function EditSale($id)
    {
        $sale = Sale::with('saleItems.product')->findOrFail($id);
        $warehouses = WareHouse::all();
        $customers = Customer::all();

        return view('admin.backend.sales.edit_sale', compact(
            'sale', 'warehouses', 'customers'
        ));
    }

    public function UpdateSale(Request $request, $id)
    {
        $request->validate([
            'date' => 'required|date',
            'status' => 'required',
        ]);
        // Implementation for updating a sale
        $sale = Sale::findOrFail($id);
        $sale->update([
            'date' => $request->date,
            'warehouse_id' => $request->warehouse_id,
            'status' => $request->status,
            'discount' => $request->discount ?? 0,
            'note' => $request->note,
            'customer_id' => $request->customer_id,
            'grand_total' => $request->grand_total,
            'shipping' => $request->shipping ?? 0,
            'paid_amount' => $request->paid_amount ?? 0,
            'full_paid' => $request->full_paid ?? null,
            'due_amount' => $request->due_amount ?? 0,
        ]);

        $saleItems = SaleItem::where('sale_id', $sale->id)->delete();

        foreach ($request->products as $product_id => $product) {
            SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $product_id,
                'net_unit_cost' => $product['net_unit_cost'],
                'stock' => $product['stock'],
                'quantity' => $product['quantity'],
                'discount' => $product['discount'] ?? 0,
                'subtotal' => $product['subtotal'],
            ]);

            // update product stock if necessary
            $productModel = Product::findOrFail($product_id);
            // Logic to update stock based on new sale items can be added here
            if ($productModel) {
                $productModel->product_qty += $product['quantity']; // decrement('product_qty', $product['quantity']);
            }
        }
        $notif = [
            'message' => 'Sale Updated Successfully',
            'alert-type' => 'success',
        ];

        return redirect()->route('all.sale')->with($notif);

    }


        public function DeleteSale($id){
            DB::beginTransaction();

            try{
                $sale = Sale::findOrFail($id);

                // Get sale items
                $saleItems = SaleItem::where('sale_id', $sale->id)->get();

                // Loop for sale items and decrement product qty
                foreach($saleItems as $item){
                    $product = Product::find($item->product_id);
                    if($product){
                        $product->increment('product_qty', $item->quantity);
                    }
                }

                // Delete sale items
                SaleItem::where('sale_id', $sale->id)->delete();
                // Delete sale
                $sale->delete();

                DB::commit();

                $notif = array(
                    'message' => "Sale Deleted Successfully",
                    'alert-type' => 'success'
                );
                return redirect()->route('all.sale')->with($notif);

            }catch(\Exception $e){
                DB::rollBack();
                return response()->json(['error'=>$e->getMessage()], 500);
            }
        }

           public function DetailsSale($id){
            $sale = Sale::with(['customer','saleItems.product'])->find($id);

            return view('admin.backend.sales.sale_details', compact(
                'sale'
            ));
        }


        public function InvoiceSale($id){
            $sale = Sale::with(['customer','saleItems.product'])
            ->find($id);

            $pdf = Pdf::loadView('admin.backend.sales.invoice_pdf', compact('sale'));
            $pdf->setPaper('A4', 'portrait');
            return $pdf->download('sale_invoice_'.$sale->id.'.pdf');
        }

}
