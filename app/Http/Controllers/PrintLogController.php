<?php

namespace App\Http\Controllers;

use App\Models\PrintLog;
use Illuminate\Http\Request;

class PrintLogController extends Controller
{
    public function index()
    {
        return PrintLog::with('user')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'sale_id' => 'required|exists:sales,id',
        ]);

        return PrintLog::create([
            'sale_id' => $request->sale_id,
            'user_id' => $request->user()->id,
        ]);
    }
}
