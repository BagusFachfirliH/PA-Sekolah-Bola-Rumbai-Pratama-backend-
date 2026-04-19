<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Jadwal_Latihan;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Siswa;
use App\Models\Pelatih;
use App\Models\Presensi;
use App\Models\Catatan_Pelatih;
use App\Models\Performa_Siswa;
use App\Models\Pembayaran;
use App\Models\BuktiPembayaran;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

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

public function Performa_Siswa(Request $request, $id)
{
    // ambil user login
    $user = auth('sanctum')->user();

    // ambil pelatih berdasarkan user login
    $pelatih = Pelatih::where('user_id', $user->id)->first();

    if (!$pelatih) {
        return response()->json([
            'status' => false,
            'message' => 'Data pelatih tidak ditemukan'
        ], 404);
    }

    // 🔥 batasi akses hanya milik pelatih
    $jadwal = Jadwal_Latihan::with('siswa')
        ->where('id_jadwal', $id)
        ->where('id_pelatih', $pelatih->id_pelatih)
        ->first();

    if (!$jadwal) {
        return response()->json([
            'status' => false,
            'message' => 'Jadwal tidak ditemukan atau bukan milik pelatih'
        ], 404);
    }

    return response()->json([
        'status' => true,
        'jadwal' => $jadwal,
    ]);
}


public function Input_Performa_Siswa(Request $request, $id)
{
    $user = auth('sanctum')->user();

    $pelatih = Pelatih::where('user_id', $user->id)->first();

    if (!$pelatih) {
        return response()->json([
            'status' => false,
            'message' => 'Pelatih tidak ditemukan'
        ], 404);
    }


    // 🔥 WAJIB: load relasi siswa
    $jadwal = Jadwal_Latihan::with('siswa')
        ->where('id_jadwal', $id)
        ->where('id_pelatih', $pelatih->id_pelatih)
        ->first();

    if (!$jadwal) {
        return response()->json([
            'status' => false,
            'message' => 'Jadwal tidak ditemukan atau bukan milik pelatih'
        ], 404);
    }

    if (!$request->has('data')) {
        return response()->json([
            'status' => false,
            'message' => 'Masukan Data tidak boleh kosong'
        ], 400);
    }

    $tanggal = now();

    foreach ($request->data as $item) {

        // 🔥 cek siswa di jadwal (lebih aman pakai contains)
        $siswa = $jadwal->siswa->where('id_siswa', $item['id_siswa'])->first();

        if (!$siswa) {
            continue;
        }

        Performa_Siswa::create([
            'id_siswa' => $item['id_siswa'],
            'tanggal_penilaian' => $tanggal,
            'bulan' => $tanggal->format('m'),
            'tahun' => $tanggal->format('Y'),
            'kategori' => 'U-' . $siswa->umur,
            'dribbling' => $item['dribbling'],
            'passing' => $item['passing'],
            'shooting' => $item['shooting'],
        ]);
    }

    return response()->json([
        'status' => true,
        'message' => 'Performa berhasil disimpan'
    ]);
}

public function Update_Performa_Siswa(Request $request, $id_jadwal)
{
    $user = auth('sanctum')->user();

    $pelatih = Pelatih::where('user_id', $user->id)->first();

    if (!$pelatih) {
        return response()->json([
            'status' => false,
            'message' => 'Data pelatih tidak ditemukan'
        ], 404);
    }

    // ambil jadwal + siswa yang ikut jadwal itu
    $jadwal = Jadwal_Latihan::with('siswa')
        ->where('id_jadwal', $id_jadwal)
        ->where('id_pelatih', $pelatih->id_pelatih)
        ->first();

    if (!$jadwal) {
        return response()->json([
            'status' => false,
            'message' => 'Jadwal tidak ditemukan'
        ], 404);
    }

    foreach ($request->data as $item) {

        // pastikan siswa memang ada di jadwal ini
        $cekSiswa = $jadwal->siswa->where('id_siswa', $item['id_siswa'])->first();

        if (!$cekSiswa) {
            continue; // skip kalau bukan siswa di jadwal ini
        }

       Performa_Siswa::updateOrCreate(
    [
        'id_siswa' => $item['id_siswa'],
    ],
    [
        'dribbling' => $item['dribbling'],
        'passing' => $item['passing'],
        'shooting' => $item['shooting'],
    ]
);
    }

    return response()->json([
        'status' => true,
        'message' => 'Performa berhasil disimpan'
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

public function FormUploadBuktiPembayaran(Request $request)
{
    $kategoriUmur = Siswa::select('umur')
        ->distinct()
        ->orderBy('umur')
        ->pluck('umur')
        ->map(fn ($umur) => 'U-' . $umur)
        ->values();

    $siswaQuery = Siswa::select('id_siswa', 'nama_siswa', 'umur', 'status')
        ->orderBy('nama_siswa');

    if ($request->filled('kategori_umur')) {
        $umur = $this->extractUmur($request->kategori_umur);

        if (!is_null($umur)) {
            $siswaQuery->where('umur', $umur);
        }
    }

    if ($request->filled('search')) {
        $siswaQuery->where('nama_siswa', 'like', '%' . $request->search . '%');
    }

    $siswa = $siswaQuery->get()->map(function ($item) {
        return [
            'id_siswa' => $item->id_siswa,
            'nama_siswa' => $item->nama_siswa,
            'kategori_umur' => 'U-' . $item->umur,
            'status' => $item->status,
        ];
    });

    return response()->json([
        'success' => true,
        'message' => 'Data form upload bukti pembayaran berhasil diambil',
        'filters' => [
            'kategori_umur' => $request->kategori_umur,
            'search' => $request->search,
        ],
        'data' => [
            'kategori_umur' => $kategoriUmur,
            'jenis_pembayaran' => ['Harian', 'Bulanan'],
            'siswa' => $siswa,
        ],
    ]);
}

public function Store_Bukti_Pembayaran_Pelatih(Request $request)
{
    $validated = $request->validate([
        'id_siswa' => 'required|exists:siswa,id_siswa',
        'jenis' => 'required|in:Harian,Bulanan',
        'tanggal_bukti_bayar' => 'required|date',
        'bukti_bayar' => 'required|file|mimes:jpg,jpeg,png,pdf|max:4096',
    ]);

    DB::beginTransaction();

    try {
        $siswa = Siswa::findOrFail($validated['id_siswa']);
        $tanggal = Carbon::parse($validated['tanggal_bukti_bayar']);
        $periode = $validated['jenis'] === 'Harian'
            ? $tanggal->format('Y-m-d')
            : $tanggal->format('Y-m');

        $pembayaran = Pembayaran::firstOrCreate(
            [
                'id_siswa' => $siswa->id_siswa,
                'jenis' => $validated['jenis'],
                'periode' => $periode,
            ],
            [
                'jumlah' => 0,
                'tanggal_bayar' => $tanggal->toDateString(),
                'status' => 'Belum',
            ]
        );

        if (!$pembayaran->tanggal_bayar) {
            $pembayaran->update([
                'tanggal_bayar' => $tanggal->toDateString(),
            ]);
        }

        $filePath = $request->file('bukti_bayar')->store('bukti_pembayaran');

        $bukti = BuktiPembayaran::where('id_pembayaran', $pembayaran->id_pembayaran)->first();

        if ($bukti) {
            if ($bukti->bukti_bayar && Storage::exists($bukti->bukti_bayar)) {
                Storage::delete($bukti->bukti_bayar);
            }

            $bukti->update([
                'id_siswa' => $siswa->id_siswa,
                'periode' => $periode,
                'tanggal_bukti_bayar' => $tanggal->toDateString(),
                'status' => 'Menunggu validasi',
                'bukti_bayar' => $filePath,
            ]);
        } else {
            $bukti = BuktiPembayaran::create([
                'id_pembayaran' => $pembayaran->id_pembayaran,
                'id_siswa' => $siswa->id_siswa,
                'periode' => $periode,
                'tanggal_bukti_bayar' => $tanggal->toDateString(),
                'status' => 'Menunggu validasi',
                'bukti_bayar' => $filePath,
            ]);
        }

        $pembayaran->update([
            'tanggal_bayar' => $tanggal->toDateString(),
            'status' => 'Belum',
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Bukti pembayaran berhasil diupload',
            'data' => $bukti->load(['siswa', 'pembayaran']),
        ], 201);
    } catch (\Throwable $e) {
        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => 'Upload bukti pembayaran gagal',
            'error' => $e->getMessage(),
        ], 500);
    }
}

public function History_Bukti_Pembayaran_Pelatih(Request $request)
{
    $query = BuktiPembayaran::with([
        'siswa:id_siswa,nama_siswa,umur',
        'pembayaran:id_pembayaran,id_siswa,jenis,status',
    ]);

    if ($request->filled('kategori_umur')) {
        $umur = $this->extractUmur($request->kategori_umur);

        if (!is_null($umur)) {
            $query->whereHas('siswa', function ($siswaQuery) use ($umur) {
                $siswaQuery->where('umur', $umur);
            });
        }
    }

    if ($request->filled('search')) {
        $query->whereHas('siswa', function ($siswaQuery) use ($request) {
            $siswaQuery->where('nama_siswa', 'like', '%' . $request->search . '%');
        });
    }

    if ($request->filled('jenis')) {
        $query->whereHas('pembayaran', function ($pembayaranQuery) use ($request) {
            $pembayaranQuery->where('jenis', $request->jenis);
        });
    }

    $history = $query->orderBy('tanggal_bukti_bayar', 'desc')
        ->orderBy('id_bukti_pembayaran', 'desc')
        ->paginate(10)
        ->through(function ($item) {
            return [
                'id_bukti_pembayaran' => $item->id_bukti_pembayaran,
                'id_pembayaran' => $item->id_pembayaran,
                'id_siswa' => $item->id_siswa,
                'nama_siswa' => $item->siswa->nama_siswa ?? null,
                'kategori_umur' => isset($item->siswa->umur) ? 'U-' . $item->siswa->umur : null,
                'jenis' => $item->pembayaran->jenis ?? null,
                'periode' => $item->periode,
                'tanggal_bukti_bayar' => $item->tanggal_bukti_bayar,
                'status' => $item->status,
                'bukti_bayar' => $item->bukti_bayar,
                'nama_file' => $item->bukti_bayar ? basename($item->bukti_bayar) : null,
            ];
        });

    $kategoriUmur = Siswa::select('umur')
        ->distinct()
        ->orderBy('umur')
        ->pluck('umur')
        ->map(fn ($umur) => 'U-' . $umur)
        ->values();

    return response()->json([
        'success' => true,
        'message' => 'History bukti pembayaran berhasil diambil',
        'filters' => [
            'kategori_umur' => $request->kategori_umur,
            'jenis' => $request->jenis,
            'search' => $request->search,
        ],
        'options' => [
            'kategori_umur' => $kategoriUmur,
            'jenis_pembayaran' => ['Harian', 'Bulanan'],
        ],
        'data' => $history,
    ]);
}

public function Hapus_Bukti_Pembayaran_Pelatih($id)
{
    $bukti = BuktiPembayaran::find($id);

    if (!$bukti) {
        return response()->json([
            'success' => false,
            'message' => 'Data bukti pembayaran tidak ditemukan',
        ], 404);
    }

    DB::beginTransaction();

    try {
        if ($bukti->bukti_bayar && Storage::exists($bukti->bukti_bayar)) {
            Storage::delete($bukti->bukti_bayar);
        }

        $pembayaran = Pembayaran::find($bukti->id_pembayaran);
        $bukti->delete();

        if ($pembayaran) {
            $masihAdaBukti = BuktiPembayaran::where('id_pembayaran', $pembayaran->id_pembayaran)->exists();

            if (!$masihAdaBukti) {
                $pembayaran->update([
                    'tanggal_bayar' => null,
                    'status' => 'Belum',
                ]);
            }
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Bukti pembayaran berhasil dihapus',
        ]);
    } catch (\Throwable $e) {
        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => 'Gagal menghapus bukti pembayaran',
            'error' => $e->getMessage(),
        ], 500);
    }
}

private function extractUmur(?string $kategoriUmur): ?int
{
    if (!$kategoriUmur) {
        return null;
    }

    if (preg_match('/U-(\d+)/i', $kategoriUmur, $match)) {
        return (int) $match[1];
    }

    if (preg_match('/U(\d+)/i', $kategoriUmur, $match)) {
        return (int) $match[1];
    }

    return null;
}





}
