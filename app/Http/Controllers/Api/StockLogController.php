<?php

namespace App\Http\Controllers;

use App\Models\StockLog;
use Illuminate\Http\Request;

class StockLogController extends Controller
{
    public function index()
    {
        return StockLog::with('product')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'change' => 'required|integer',
            'note' => 'nullable|string',
        ]);

        return StockLog::create([
            'product_id' => $request->product_id,
            'change' => $request->change,
            'note' => $request->note,
            'user_id' => $request->user()->id,
        ]);
    }
}
