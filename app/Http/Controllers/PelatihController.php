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
    $jadwal = Jadwal_Latihan::with(['siswa' => function ($q) use ($request) {

        if ($request->filled('kategori_umur')) {
            if (preg_match('/U(\d+)/', strtoupper($request->kategori_umur), $match)) {
                $q->where('umur', (int) $match[1]);
            }
        }

    }])
    ->orderBy('id_jadwal', 'desc')
    ->paginate(5);

    return response()->json([
        'status' => true,
        'data' => $jadwal
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
        'id_jadwal' => 'required|exists:jadwal_latihan,id_jadwal',
        'id_pelatih' => 'required|exists:pelatih,id_pelatih',
        'data' => 'required|array',
        'data.*.id_siswa' => 'required|exists:siswa,id_siswa',
        'data.*.status' => 'required|in:Hadir,Sakit,Izin'
    ]);

    $jadwal = Jadwal_Latihan::where('id_jadwal', $request->id_jadwal)
        ->first();

    if (!$jadwal) {
        return response()->json([
            'status' => false,
            'message' => 'Jadwal tidak ditemukan'
        ], 404);
    }

    if (Presensi::where('id_jadwal', $request->id_jadwal)->exists()) {
        Presensi::where('id_jadwal', $request->id_jadwal)
            ->whereNull('id_pelatih')
            ->update(['id_pelatih' => $request->id_pelatih]);

        return response()->json([
            'status' => false,
            'message' => 'Presensi untuk jadwal ini sudah pernah diinput'
        ], 422);
    }

   foreach ($request->data as $item) {
    Presensi::updateOrCreate(
        [
            'id_siswa' => $item['id_siswa'],
            'id_jadwal' => $request->id_jadwal
        ],
        [
            'id_pelatih' => $request->id_pelatih,
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
    $bulan  = $request->query('bulan');
    $tahun  = $request->query('tahun') ?? now()->year;
    $jadwal = $request->query('id_jadwal');

    // 🔥 PAGINATION DI SISWA (INI KUNCI UTAMA)
    $siswa = Siswa::paginate(10);

    // transform data per siswa
    $rekap = $siswa->getCollection()->map(function ($s) use ($bulan, $tahun, $jadwal) {

        $presensiQuery = $s->presensi()
            ->whereYear('created_at', $tahun);

        // filter bulan
        if (!empty($bulan)) {
            $presensiQuery->whereMonth('created_at', $bulan);
        }

        // filter jadwal
        if (!empty($jadwal) && $jadwal != 'Semua') {
            $presensiQuery->where('id_jadwal', $jadwal);
        }

        $presensi = $presensiQuery->get();

        $total = $presensi->count();

        return [
            'id_siswa'   => $s->id_siswa,
            'nama_siswa' => $s->nama_siswa ?? '-',
            'umur'       => 'U-' . $s->umur,

            'hadir' => $total ? round($presensi->where('status_kehadiran','Hadir')->count() / $total * 100, 1) : 0,
            'sakit' => $total ? round($presensi->where('status_kehadiran','Sakit')->count() / $total * 100, 1) : 0,
            'izin'  => $total ? round($presensi->where('status_kehadiran','Izin')->count() / $total * 100, 1) : 0,

            'total' => $total
        ];
    });

    return response()->json([
        'status'  => true,
        'message' => 'Rekap absensi berhasil',
        'bulan'   => $bulan ?? 'all',
        'tahun'   => $tahun,
        'jadwal'  => $jadwal ?? 'Semua',

        // 🔥 DATA HASIL PAGINATION
        'data' => $rekap,

        // 🔥 META PAGINATION
        'pagination' => [
            'current_page' => $siswa->currentPage(),
            'last_page'    => $siswa->lastPage(),
            'per_page'     => $siswa->perPage(),
            'total'        => $siswa->total(),
        ]
    ]);
}


public function performa_siswa(Request $request)
{
    // ambil dropdown kategori umur dari jadwal_latihan
    $kategoriUmur = Jadwal_Latihan::select('kategori_umur')
        ->distinct()
        ->get();

    $query = Jadwal_Latihan::with([
        'siswa.kehadiran',
        'siswa.performa' // penting: tanggal dari sini
    ])
    ->whereDoesntHave('performa');

    // filter kategori umur
    if ($request->filled('kategori_umur')) {
        if (preg_match('/U(\d+)/', strtoupper($request->kategori_umur), $match)) {
            $umur = (int) $match[1];

            $query->whereHas('siswa', function ($q) use ($umur) {
                $q->where('umur', $umur);
            });
        }
    }

    // filter jadwal (jika dipilih)
    if ($request->filled('id_jadwal')) {
        $query->where('id_jadwal', $request->id_jadwal);
    }

    $jadwal = $query->orderBy('id_jadwal', 'desc')
        ->paginate(5);

    return response()->json([
        'status' => true,
        'kategori_umur' => $kategoriUmur,
        'data' => $jadwal
    ]);
}


public function Input_Performa_Siswa(Request $request, $id)
{
    $request->validate([
        'id_pelatih' => 'required|exists:pelatih,id_pelatih',
        'data' => 'required|array',
        'data.*.id_siswa' => 'required|exists:siswa,id_siswa',
        'data.*.dribbling' => 'required|numeric|min:0|max:100',
        'data.*.passing' => 'required|numeric|min:0|max:100',
        'data.*.shooting' => 'required|numeric|min:0|max:100',
        'tanggal_penilaian' => 'nullable|date',
    ]);

    // 🔥 WAJIB: load relasi siswa
    $jadwal = Jadwal_Latihan::with('siswa')
        ->where('id_jadwal', $id)
        ->first();

    if (!$jadwal) {
        return response()->json([
            'status' => false,
            'message' => 'Jadwal tidak ditemukan atau bukan milik pelatih'
        ], 404);
    }

    $performaSudahAda = Performa_Siswa::where('id_jadwal', $jadwal->id_jadwal);

    if ($performaSudahAda->exists()) {
        Performa_Siswa::where('id_jadwal', $jadwal->id_jadwal)
            ->whereNull('id_pelatih')
            ->update(['id_pelatih' => $request->id_pelatih]);

        return response()->json([
            'status' => false,
            'message' => 'Performa untuk jadwal ini sudah pernah diinput'
        ], 422);
    }

    $tanggal = $request->filled('tanggal_penilaian')
        ? Carbon::parse($request->tanggal_penilaian)->toDateString()
        : now()->toDateString();

    foreach ($request->data as $item) {

        // 🔥 cek siswa di jadwal (lebih aman pakai contains)
        $siswa = $jadwal->siswa->where('id_siswa', $item['id_siswa'])->first();

        if (!$siswa) {
            continue;
        }

        Performa_Siswa::updateOrCreate([
            'id_jadwal' => $jadwal->id_jadwal,
            'id_siswa' => $item['id_siswa'],
        ], [
            'id_pelatih' => $request->id_pelatih,
            'tanggal_penilaian' => $tanggal,
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
    $request->validate([
        'id_pelatih' => 'required|exists:pelatih,id_pelatih',
        'data' => 'required|array',
        'data.*.id_siswa' => 'required|exists:siswa,id_siswa',
        'data.*.dribbling' => 'required|numeric|min:0|max:100',
        'data.*.passing' => 'required|numeric|min:0|max:100',
        'data.*.shooting' => 'required|numeric|min:0|max:100',
        'tanggal_penilaian' => 'nullable|date',
    ]);

    // ambil jadwal + siswa yang ikut jadwal itu
    $jadwal = Jadwal_Latihan::with('siswa')
        ->where('id_jadwal', $id_jadwal)
        ->first();

    if (!$jadwal) {
        return response()->json([
            'status' => false,
            'message' => 'Jadwal tidak ditemukan'
        ], 404);
    }

    $tanggal = $request->filled('tanggal_penilaian')
        ? Carbon::parse($request->tanggal_penilaian)->toDateString()
        : now()->toDateString();

    foreach ($request->data as $item) {

        // pastikan siswa memang ada di jadwal ini
        $cekSiswa = $jadwal->siswa->where('id_siswa', $item['id_siswa'])->first();

        if (!$cekSiswa) {
            continue; // skip kalau bukan siswa di jadwal ini
        }

        Performa_Siswa::updateOrCreate(
            [
                'id_jadwal' => $jadwal->id_jadwal,
                'id_siswa' => $item['id_siswa'],
            ],
            [
                'id_pelatih' => $request->id_pelatih,
                'tanggal_penilaian' => $tanggal,
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
        'jumlah' => 'required|numeric|min:1',
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

        // 🔥 CEGAH DUPLIKAT PEMBAYARAN
        $pembayaran = Pembayaran::updateOrCreate(
            [
                'id_siswa' => $siswa->id_siswa,
                'jenis' => $validated['jenis'],
                'periode' => $periode,
            ],
            [
                'jumlah' => $validated['jumlah'],
                'tanggal_bayar' => $tanggal->toDateString(),
                'status' => 'belum',
            ]
        );

        // 🔥 OPSIONAL: hapus bukti lama biar tidak numpuk
        BuktiPembayaran::where('id_pembayaran', $pembayaran->id_pembayaran)->delete();

        // upload file baru
        $filePath = $request->file('bukti_bayar')->store('bukti_pembayaran');

        $bukti = BuktiPembayaran::create([
            'id_pembayaran' => $pembayaran->id_pembayaran,
            'id_siswa' => $siswa->id_siswa,
            'periode' => $periode,
            'tanggal_bukti_bayar' => $tanggal->toDateString(),
            'status' => 'Menunggu validasi',
            'bukti_bayar' => $filePath,
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

    // Validasi: bukti yang sudah diterima tidak boleh dihapus
    if ($bukti->status === 'diterima') {
        return response()->json([
            'success' => false,
            'message' => 'Bukti pembayaran yang sudah diterima tidak dapat dihapus',
        ], 403);
    }

    DB::beginTransaction();

    try {
        if ($bukti->bukti_bayar && Storage::exists($bukti->bukti_bayar)) {
            Storage::delete($bukti->bukti_bayar);
        }

        $pembayaran = Pembayaran::find($bukti->id_pembayaran);
        $bukti->delete();

        if ($pembayaran) {
            $masihAdaBukti = BuktiPembayaran::where(
                'id_pembayaran',
                $pembayaran->id_pembayaran
            )->exists();

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
