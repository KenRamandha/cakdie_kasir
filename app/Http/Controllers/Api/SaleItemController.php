<?php

namespace App\Http\Controllers;

use App\Models\SaleItem;
use Illuminate\Http\Request;

class SaleItemController extends Controller
{
    public function index()
    {
        return SaleItem::with(['product', 'sale'])->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer',
            'price' => 'required|numeric',
        ]);

        return SaleItem::create($request->all());
    }

    public function destroy(SaleItem $saleItem)
    {
        $saleItem->delete();
        return response()->noContent();
    }
}
