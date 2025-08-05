<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompanySettingController extends Controller
{
    public function getPublicSettings()
    {
        $settings = CompanySetting::first();

        return response()->json([
            'name' => $settings->name ?? 'Nama Perusahaan',
            'address' => $settings->address ?? 'Alamat Perusahaan',
            'phone' => $settings->phone ?? '08123456789',
            'receipt_footer' => $settings->receipt_footer ?? 'Terima kasih telah berbelanja',
            'logo_url' => $settings->logo_path ? Storage::url($settings->logo_path) : null,
        ]);
    }

    public function getFullSettings(Request $request)
    {
        $user = $request->user();

        // Cek hak akses langsung di controller
        if ($user && $user->role === 'pegawai') {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Hanya owner yang bisa mengakses'
            ], 403);
        }

        $settings = CompanySetting::first();

        return response()->json([
            'id' => $settings->id ?? null,
            'name' => $settings->name ?? null,
            'address' => $settings->address ?? null,
            'phone' => $settings->phone ?? null,
            'email' => $settings->email ?? null,
            'website' => $settings->website ?? null,
            'tax_id' => $settings->tax_id ?? null,
            'logo_path' => $settings->logo_path ?? null,
            'logo_url' => $settings->logo_path ? Storage::url($settings->logo_path) : null,
            'receipt_footer' => $settings->receipt_footer ?? null,
            'created_at' => $settings->created_at ?? null,
            'updated_at' => $settings->updated_at ?? null,
        ]);
    }

    public function saveSettings(Request $request)
    {
        $user = $request->user();

        // Cek hak akses langsung di controller
        if ($user && $user->role === 'pegawai') {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Hanya owner yang bisa mengubah settings'
            ], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'tax_id' => 'nullable|string|max:50',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'receipt_footer' => 'nullable|string|max:500',
        ]);

        $settings = CompanySetting::firstOrNew();

        if ($request->hasFile('logo')) {
            if ($settings->logo_path) {
                Storage::delete($settings->logo_path);
            }

            $file = $request->file('logo');
            $filename = 'company-logo-' . time() . '.' . $file->extension();
            $path = $file->storeAs('company', $filename, 'public');
            $settings->logo_path = $path;
        }

        $settings->name = $request->name;
        $settings->address = $request->address;
        $settings->phone = $request->phone;
        $settings->email = $request->email;
        $settings->website = $request->website;
        $settings->tax_id = $request->tax_id;
        $settings->receipt_footer = $request->receipt_footer;
        $settings->save();

        return response()->json([
            'success' => true,
            'data' => $settings,
            // 'logo_url' => $settings->logo_path ? Storage::url($settings->logo_path) : null,
            'logo_url' => $settings->logo_path
                ? request()->getSchemeAndHttpHost() . Storage::url($settings->logo_path)
                : null,

            'message' => 'Pengaturan perusahaan berhasil disimpan'
        ]);
    }
}
