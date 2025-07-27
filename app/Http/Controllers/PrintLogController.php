<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PrintLog;
use App\Models\Sale;

class PrintLogController extends Controller
{
    public function index()
    {
        return PrintLog::with('user')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'sale_code' => 'required|exists:sales,code',
        ]);

        $sale = Sale::where('code', $request->sale_code)->firstOrFail();

        return PrintLog::create([
            'sale_id' => $sale->id,
            'user_id' => $request->user()->user_id,
        ]);
    }
}

