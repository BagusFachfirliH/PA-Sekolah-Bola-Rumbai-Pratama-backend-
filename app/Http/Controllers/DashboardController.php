<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Siswa;

class DashboardController extends Controller
{
   public function siswaDashboard(Request $request)
{
    $user = Auth::user();

    if ($user->role !== 'orang_tua') {
        return response()->json([
            'status' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    $selectedChildId = session('id_siswa');

    $siswaQuery = $user->siswa();

    if ($selectedChildId) {
        $siswaQuery->where('id_siswa', $selectedChildId);
    }

    $siswa = $siswaQuery->first();

    if (!$siswa) {
        return response()->json([
            'status' => false,
            'message' => 'Data siswa tidak ditemukan'
        ], 404);
    }

    $pembayaranBelum = $siswa->pembayaran()
        ->whereIn('status', ['Belum', 'Lunas'])
        ->whereIn('jenis', ['Pendaftaran', 'Bulanan'])
        ->get();

    return response()->json([
        'status' => true,
        'message' => 'Dashboard siswa',
        'data' => [
            'nama_siswa' => $siswa->nama_siswa,
            'userName' => $user->name,
            'status_siswa' => $siswa->status,
            'pembayaranBelum' => $pembayaranBelum
        ]
    ]);
}

public function adminDashboard()
{
    if (auth()->user()->role !== 'admin') {
        return response()->json([
            'status' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    return response()->json([
        'status' => true,
        'message' => 'Dashboard admin',
        'data' => [
            'role' => 'admin'
        ]
    ]);
}

public function pelatihDashboard()
{
    if (auth()->user()->role !== 'pelatih') {
        return response()->json([
            'status' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    return response()->json([
        'status' => true,
        'message' => 'Dashboard pelatih',
        'data' => [
            'role' => 'pelatih'
        ]
    ]);
}

}
