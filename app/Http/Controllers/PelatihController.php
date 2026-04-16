<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Jadwal_Latihan;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Siswa;
use App\Models\Presensi;
use App\Models\Catatan_Pelatih;
use Carbon\Carbon;

class PelatihController extends Controller
{
  public function Kehadiran(Request $request)
{
    $jadwalQuery = Jadwal_Latihan::with(['siswa' => function ($q) use ($request) {

        // FILTER UMUR (U-12 → 12)
        if ($request->filled('kategori_umur')) {
            if (preg_match('/U(\d+)/', strtoupper($request->kategori_umur), $match)) {
                $q->where('umur', (int) $match[1]);
            }
        }

    }]);

    // ambil jadwal terbaru
    $jadwal = $jadwalQuery
        ->orderBy('id_jadwal', 'desc')
        ->first();

    return response()->json([
        'status' => true,
        'jadwal' => $jadwal
    ]);
}

    public function getJadwal()
{
    $jadwal = \App\Models\Jadwal_Latihan::get();

    return response()->json([
        'status' => true,
        'data' => $jadwal
    ]);
}

public function Input_Presensi(Request $request)
{
    $request->validate([
        'id_jadwal' => 'required',
        'data' => 'required|array',
        'data.*.id_siswa' => 'required|exists:siswa,id_siswa',
        'data.*.status' => 'required|in:Hadir,Sakit,Izin'
    ]);

    foreach ($request->data as $item) {
        Presensi::updateOrCreate(
            [
                'id_siswa' => $item['id_siswa'],
                'id_jadwal' => $request->id_jadwal
            ],
            [
                'status_kehadiran' => $item['status']
            ]
        );
    }

    return response()->json([
        'status' => true,
        'message' => 'Presensi berhasil disimpan'
    ]);
}



public function Rekap_Absensi(Request $request)
{
    $bulan = $request->bulan ?? now()->month;
    $tahun = $request->tahun ?? now()->year;

    $siswa = Siswa::all();

    $rekap = $siswa->map(function ($s) use ($bulan, $tahun) {

        $presensi = $s->presensi()
            ->whereMonth('created_at', $bulan)
            ->whereYear('created_at', $tahun)
            ->get();

        $total = $presensi->count();

        $hadir = $presensi->where('status_kehadiran', 'Hadir')->count();
        $sakit = $presensi->where('status_kehadiran', 'Sakit')->count();
        $izin  = $presensi->where('status_kehadiran', 'Izin')->count();

        return [
            'id_siswa' => $s->id_siswa,
            'nama_siswa' => $s->nama_siswa ?? '-',

            // U- format
            'umur' => 'U-' . $s->umur,

            'hadir' => $total ? round(($hadir / $total) * 100, 1) : 0,
            'sakit' => $total ? round(($sakit / $total) * 100, 1) : 0,
            'izin'  => $total ? round(($izin / $total) * 100, 1) : 0,

            'total' => $total,
        ];
            });

    return response()->json([
        'status' => true,
        'message' => 'Rekap semua siswa per bulan berhasil',
        'bulan' => $bulan,
        'tahun' => $tahun,
        'data' => $rekap
    ]);
}

public function Catatan_Pelatih()
{
    $data = Catatan_Pelatih::with(['siswa', 'pelatih'])
        ->latest()
        ->get();

    return response()->json([
        'status' => true,
        'message' => 'Data catatan pelatih',
        'data' => $data
    ]);
}

public function Catatan_perPelatih($id_pelatih)
{
    $data = Catatan_Pelatih::with(['siswa', 'pelatih'])
        ->where('id_pelatih', $id_pelatih)
        ->latest()
        ->get();

    return response()->json([
        'status' => true,
        'message' => 'Data catatan pelatih',
        'data' => $data
    ]);
}

public function Tambah_Catatan_Pelatih(Request $request)
{
    $request->validate([
        'id_pelatih' => 'required',
        'catatan' => 'required',
        'data' => 'required|array'
    ]);

    $insert = [];

    foreach ($request->data as $item) {

        $catatan = Catatan_Pelatih::create([
            'id_siswa' => $item['id_siswa'],
            'id_pelatih' => $request->id_pelatih,
            'catatan' => $request->catatan,
            'tanggal_catatan' => Carbon::now()
        ]);

        // load relasi siswa
        $catatan->load('siswa');

     $insert[] = [
    'id_catatan' => $catatan->id_catatan,
    'id_siswa' => $catatan->id_siswa,
    'nama_siswa' => $catatan->siswa->nama_siswa ?? null,
    'umur' => 'U-' . ($catatan->siswa->umur ?? 0),
    'id_pelatih' => $catatan->id_pelatih,
    'nama_pelatih' => $catatan->pelatih->nama_pelatih ?? null,

    'catatan' => $catatan->catatan,
    'tanggal_catatan' => $catatan->tanggal_catatan
];
    }

    return response()->json([
        'status' => true,
        'message' => 'Catatan berhasil disimpan untuk siswa',
        'data' => $insert
    ]);
}

public function Update_Catatan_Pelatih(Request $request, $id)
{
    $catatan = Catatan_Pelatih::find($id);

    if (!$catatan) {
        return response()->json([
            'status' => false,
            'message' => 'Data tidak ditemukan'
        ], 404);
    }

    $request->validate([
        'id_siswa' => 'required',
        'id_pelatih' => 'required',
        'catatan' => 'required'
    ]);

    $catatan->update([
        'id_siswa' => $request->id_siswa,
        'id_pelatih' => $request->id_pelatih,
        'catatan' => $request->catatan
    ]);

    // reload relasi biar data fresh
    $catatan->load(['siswa', 'pelatih']);

    return response()->json([
        'status' => true,
        'message' => 'Catatan berhasil diupdate',
        'data' => [
            'id_catatan' => $catatan->id_catatan,
            'id_siswa' => $catatan->id_siswa,
            'nama_siswa' => $catatan->siswa->nama_siswa ?? null,
            'umur' => $catatan->siswa->umur ? 'U-' . $catatan->siswa->umur : null,

            'id_pelatih' => $catatan->id_pelatih,
            'nama_pelatih' => $catatan->pelatih->nama_pelatih ?? null,

            'catatan' => $catatan->catatan,
            'tanggal_catatan' => $catatan->tanggal_catatan,
            'updated_at' => $catatan->updated_at
        ]
    ]);
}

public function Hapus_Catatan_Pelatih($id)
{
    $catatan = Catatan_Pelatih::find($id);

    if (!$catatan) {
        return response()->json([
            'status' => false,
            'message' => 'Data tidak ditemukan'
        ], 404);
    }

    $catatan->delete();

    return response()->json([
        'status' => true,
        'message' => 'Catatan berhasil dihapus'
    ]);
}





}
