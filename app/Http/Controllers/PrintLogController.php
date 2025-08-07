<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PrintLog;
use App\Models\Sale;
use Illuminate\Validation\ValidationException;

class PrintLogController extends Controller
{
    public function index()
    {
        try {
            $printLogs = PrintLog::with('user')->get();
            return response()->json($printLogs);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data log pencetakan: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'sale_code' => 'required|exists:sales,code',
            ], [
                'sale_code.required' => 'Kode penjualan wajib diisi',
                'sale_code.exists' => 'Kode penjualan tidak valid'
            ]);

            $sale = Sale::where('code', $request->sale_code)->firstOrFail();

            $printLog = PrintLog::create([
                'sale_id' => $sale->code,
                'user_id' => $request->user()->user_id,
            ]);

            return response()->json([
                'message' => 'Log pencetakan berhasil disimpan',
                'data' => $printLog->load('user')
            ], 201);
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->first()[0];
            return response()->json([
                'message' => 'Validasi gagal: ' . $firstError,
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menyimpan log pencetakan: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }
}
