<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\WareHouse;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class SaleReturnController extends Controller
{

    public function AllSalesReturn()
    {
        $sales = SaleReturn::orderBy('id', 'desc')->get();

        return view('admin.backend.return-sale.all_sales_return', compact('sales'));
    }

    public function AddSaleReturn()
    {
        $customers = Customer::all();
        $warehouses = WareHouse::all();

        return view('admin.backend.return-sale.add_sale_return', compact('customers', 'warehouses'));
    }

    public function StoreSaleReturn(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'status' => 'required',
            'customer_id' => 'required',
        ]);

        try {

            DB::beginTransaction();

            $grandTotal = 0;

            $sale = SaleReturn::create([
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

                SaleReturnItem::create([
                    'sale_return_id' => $sale->id,
                    'product_id' => $productData['id'],
                    'net_unit_cost' => $netUnitCost,
                    'stock' => $product->product_qty + $productData['quantity'],
                    'quantity' => $productData['quantity'],
                    'discount' => $productData['discount'] ?? 0,
                    'subtotal' => $subtotal,
                ]);

                $product->increment('product_qty', $productData['quantity']);
            }

            $sale->update(['grand_total' => $grandTotal + $request->shipping - $request->discount]);

            DB::commit();

            $notif = [
                'message' => 'Sale Return Stored Successfully',
                'alert-type' => 'success',
            ];

            return redirect()->route('all.sale.return')->with($notif);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


}
