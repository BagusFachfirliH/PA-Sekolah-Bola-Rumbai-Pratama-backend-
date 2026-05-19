<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\Performa_Siswa;
use App\Models\Catatan_Pelatih;
use App\Models\Pencapaian;
use App\Models\Pendaftaran_Siswa;
use App\Models\Pembayaran;
use App\Models\BuktiPembayaran;


class SiswaController extends Controller
{
   public function registrasi_siswa()
{
    return response()->json([
        'status' => true,
        'message' => 'Silahkan Daftarkan Siswa Anda',
        'data' => Auth::user()
    ]);
}

public function daftar_siswa(Request $request)
{
    // VALIDASI
    try {
        $validated = $request->validate([
            'nama_siswa' => 'required',
            'nama_ayah'  => 'required',
            'nama_ibu'   => 'required',
            'umur'       => 'required|numeric',
            'akta_kelahiran' => 'required|file',
            'kartu_keluarga' => 'required|file',
            'rapor' => 'required|file',
            'pas_photo_3x4' => 'required|file',
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'status' => false,
            'message' => 'Validasi gagal',
            'errors' => $e->errors()
        ], 422);
    }

    DB::beginTransaction();

    try {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $ortu = OrangTua::where('user_id', $user->id)->first();

        if (!$ortu) {
            return response()->json([
                'status' => false,
                'message' => 'Data orang tua tidak ditemukan'
            ], 404);
        }

        // 🔥 SIMPAN FILE
        $akta  = $request->file('akta_kelahiran')->store('akta');
        $kk    = $request->file('kartu_keluarga')->store('kk');
        $rapor = $request->file('rapor')->store('rapor');
        $foto  = $request->file('pas_photo_3x4')->store('foto');

        // 🔥 SIMPAN SISWA
        $siswa = Siswa::create([
            'nama_siswa' => $validated['nama_siswa'],
            'nama_ayah'  => $validated['nama_ayah'],
            'nama_ibu'   => $validated['nama_ibu'],
            'umur'       => $validated['umur'],
            'id_ortu'    => $ortu->id_ortu,
            'user_id'    => $user->id,

            'akta_kelahiran' => basename($akta),
            'kartu_keluarga' => basename($kk),
            'rapor'          => basename($rapor),
            'pas_photo_3x4'  => basename($foto),

            'status' => 'Inactive',
        ]);

        // 🔥 SIMPAN PENDAFTARAN
        $pendaftaran = Pendaftaran_Siswa::create([
            'id_siswa' => $siswa->id_siswa,
            'tanggal_daftar' => now(),
            'status_approval' => 'Menunggu',
        ]);

        $pembayaran = Pembayaran::create([
            'id_siswa' => $siswa->id_siswa,
            'jenis' => 'Pendaftaran',
            'periode' => date('Y'),
            'jumlah' => 280000,
            'tanggal_bayar' => null,
            'status' => 'Belum',
        ]);

        DB::commit();

        return response()->json([
            'status' => true,
            'message' => 'Pendaftaran siswa berhasil',
            'data' => [
                'siswa' => $siswa,
                'pendaftaran' => $pendaftaran,
                'pembayaran' => $pembayaran,
            ]
        ], 201);

    } catch (\Exception $e) {

        DB::rollBack();

        Log::error('DAFTAR SISWA GAGAL', [
            'error' => $e->getMessage(),
            'user_id' => Auth::id()
        ]);

        return response()->json([
            'status' => false,
            'message' => 'Pendaftaran siswa gagal',
            'error' => $e->getMessage() // opsional (debug)
        ], 500);
    }
}


public function getAnak()
{
    $userId = Auth::id();

    $anak = \App\Models\Siswa::whereHas('orangtua', function ($q) use ($userId) {
        $q->where('user_id', $userId);
    })->get(['id_siswa', 'nama_siswa']);

    return response()->json([
        'status' => true,
        'message' => 'Data anak berhasil diambil',
        'data' => $anak
    ]);
}

public function setAnak(Request $request)
{
    $request->validate([
        'id_siswa' => 'required|exists:siswa,id_siswa',
    ]);

    $userId = Auth::id();

    $siswa = \App\Models\Siswa::where('id_siswa', $request->id_siswa)
        ->whereHas('orangtua', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->first(['id_siswa', 'nama_siswa']);

    if (!$siswa) {
        return response()->json([
            'status' => false,
            'message' => 'Anak tidak ditemukan atau tidak terkait dengan akun orang tua ini'
        ], 404);
    }

    session(['id_siswa' => $request->id_siswa]);

    return response()->json([
        'status' => true,
        'message' => 'Anak berhasil dipilih',
        'data' => [
            'id_siswa' => $siswa->id_siswa,
            'nama_siswa' => $siswa->nama_siswa,
        ]
    ]);
}

public function revisi_pendaftaran($id_siswa)
{
    $pendaftaran = Pendaftaran_Siswa::with('siswa')
        ->where('id_siswa', $id_siswa)
        ->first();

    if (!$pendaftaran) {
        return response()->json([
            'success' => false,
            'message' => 'Data pendaftaran tidak ditemukan untuk id_siswa tersebut',
            'id_siswa' => $id_siswa,
        ], 404);
    }

    $pembayaranPendaftaran = Pembayaran::where('id_siswa', $id_siswa)
        ->where('jenis', 'Pendaftaran')
        ->first();
    $buktiPembayaranTerakhir = $pembayaranPendaftaran
        ? BuktiPembayaran::where('id_pembayaran', $pembayaranPendaftaran->id_pembayaran)
            ->orderBy('id_bukti_pembayaran', 'desc')
            ->first()
        : null;

    $invalidIdentityFields = [];
    $invalidUploadFields = [];

    // IDENTITAS
    if ($pendaftaran->val_nama_siswa == 'tidak_valid') $invalidIdentityFields[] = 'nama_siswa';
    if ($pendaftaran->val_nama_ayah == 'tidak_valid') $invalidIdentityFields[] = 'nama_ayah';
    if ($pendaftaran->val_nama_ibu == 'tidak_valid') $invalidIdentityFields[] = 'nama_ibu';
    if ($pendaftaran->val_umur == 'tidak_valid') $invalidIdentityFields[] = 'umur';

    // FILE
    if ($pendaftaran->val_akta == 'tidak_valid') $invalidUploadFields[] = 'akta_kelahiran';
    if ($pendaftaran->val_kk == 'tidak_valid') $invalidUploadFields[] = 'kartu_keluarga';
    if ($pendaftaran->val_rapor == 'tidak_valid') $invalidUploadFields[] = 'rapor';
    if ($pendaftaran->val_foto == 'tidak_valid') $invalidUploadFields[] = 'pas_photo_3x4';
    if ($buktiPembayaranTerakhir && $buktiPembayaranTerakhir->status === 'ditolak') {
        $invalidUploadFields[] = 'bukti_bayar';
    }

    return response()->json([
        'success' => true,
        'data' => [
            'siswa' => $pendaftaran->siswa,
            'pendaftaran' => $pendaftaran,
            'pembayaran_pendaftaran' => $pembayaranPendaftaran,
            'bukti_pembayaran_terakhir' => $buktiPembayaranTerakhir,
            'invalidIdentityFields' => $invalidIdentityFields,
            'invalidUploadFields' => $invalidUploadFields,
        ]
    ]);
}

public function update_pendaftaran(Request $request, $id_siswa)
{
    \Log::info('Update revisi pendaftaran', [
        'id_siswa' => $id_siswa,
        'user' => auth()->user()
    ]);

    DB::beginTransaction();

    try {
        // =========================
        // AMBIL DATA VALIDASI
        // =========================
        $pendaftaran = Pendaftaran_Siswa::where('id_siswa', $id_siswa)->first();

        if (!$pendaftaran) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Data pendaftaran tidak ditemukan untuk id_siswa tersebut',
                'id_siswa' => $id_siswa,
            ], 404);
        }

        $pembayaranPendaftaran = Pembayaran::where('id_siswa', $id_siswa)
            ->where('jenis', 'Pendaftaran')
            ->first();
        $buktiPembayaranTerakhir = $pembayaranPendaftaran
            ? BuktiPembayaran::where('id_pembayaran', $pembayaranPendaftaran->id_pembayaran)
                ->orderBy('id_bukti_pembayaran', 'desc')
                ->first()
            : null;
        $buktiPembayaranPerluRevisi = $buktiPembayaranTerakhir
            && $buktiPembayaranTerakhir->status === 'ditolak';

        // =========================
        // VALIDASI DINAMIS
        // =========================
        $rules = [];

        if ($pendaftaran->val_nama_siswa === 'tidak_valid') {
            $rules['nama_siswa'] = 'required|string';
        }

        if ($pendaftaran->val_nama_ayah === 'tidak_valid') {
            $rules['nama_ayah'] = 'required|string';
        }

        if ($pendaftaran->val_nama_ibu === 'tidak_valid') {
            $rules['nama_ibu'] = 'required|string';
        }

        if ($pendaftaran->val_umur === 'tidak_valid') {
            $rules['umur'] = 'required|numeric';
        }

        if ($pendaftaran->val_akta === 'tidak_valid') {
            $rules['akta_kelahiran'] = 'nullable|file|mimes:pdf,jpg,jpeg,png';
        }

        if ($pendaftaran->val_kk === 'tidak_valid') {
            $rules['kartu_keluarga'] = 'nullable|file|mimes:pdf,jpg,jpeg,png';
        }

        if ($pendaftaran->val_rapor === 'tidak_valid') {
            $rules['rapor'] = 'nullable|file|mimes:pdf,jpg,jpeg,png';
        }

        if ($pendaftaran->val_foto === 'tidak_valid') {
            $rules['pas_photo_3x4'] = 'nullable|file|mimes:jpg,jpeg,png';
        }

        if ($buktiPembayaranPerluRevisi) {
            $rules['tanggal_bukti_bayar'] = 'required|date';
            $rules['bukti_bayar'] = 'required|file|mimes:jpg,jpeg,png,pdf';
        }

        // =========================
        // VALIDATE REQUEST
        // =========================
        $request->validate($rules);

        // =========================
        // AMBIL DATA SISWA
        // =========================
        $siswa = Siswa::findOrFail($id_siswa);

        // =========================
        // UPDATE TEXT (HANYA JIKA ADA FIELD & REQUIRED)
        // =========================
        if ($pendaftaran->val_nama_siswa === 'tidak_valid' && $request->filled('nama_siswa')) {
            $siswa->nama_siswa = $request->nama_siswa;
        }

        if ($pendaftaran->val_nama_ayah === 'tidak_valid' && $request->filled('nama_ayah')) {
            $siswa->nama_ayah = $request->nama_ayah;
        }

        if ($pendaftaran->val_nama_ibu === 'tidak_valid' && $request->filled('nama_ibu')) {
            $siswa->nama_ibu = $request->nama_ibu;
        }

        if ($pendaftaran->val_umur === 'tidak_valid' && $request->filled('umur')) {
            $siswa->umur = $request->umur;
        }

        // =========================
        // UPDATE FILE
        // =========================
        if ($pendaftaran->val_akta === 'tidak_valid' && $request->hasFile('akta_kelahiran')) {
            $path = $request->file('akta_kelahiran')->store('akta');
            $siswa->akta_kelahiran = basename($path);
        }

        if ($pendaftaran->val_kk === 'tidak_valid' && $request->hasFile('kartu_keluarga')) {
            $path = $request->file('kartu_keluarga')->store('kk');
            $siswa->kartu_keluarga = basename($path);
        }

        if ($pendaftaran->val_rapor === 'tidak_valid' && $request->hasFile('rapor')) {
            $path = $request->file('rapor')->store('rapor');
            $siswa->rapor = basename($path);
        }

        if ($pendaftaran->val_foto === 'tidak_valid' && $request->hasFile('pas_photo_3x4')) {
            $path = $request->file('pas_photo_3x4')->store('foto');
            $siswa->pas_photo_3x4 = basename($path);
        }

        if ($buktiPembayaranPerluRevisi && $request->hasFile('bukti_bayar')) {
            $filePath = $request->file('bukti_bayar')->store('bukti_pembayaran');

            $buktiPembayaranTerakhir->update([
                'tanggal_bukti_bayar' => $request->tanggal_bukti_bayar,
                'status' => 'Menunggu validasi',
                'bukti_bayar' => $filePath,
            ]);

            if ($pembayaranPendaftaran) {
                $pembayaranPendaftaran->update([
                    'status' => 'Belum',
                    'tanggal_bayar' => $request->tanggal_bukti_bayar,
                ]);
            }
        }

        // =========================
        // SAVE
        // =========================
        $siswa->save();
        $pendaftaran->status_approval = 'Menunggu';
        $pendaftaran->save();

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Revisi pendaftaran berhasil diperbarui',
            'data' => [
                'siswa' => $siswa,
                'pendaftaran' => $pendaftaran,
                'pembayaran_pendaftaran' => $pembayaranPendaftaran,
                'bukti_pembayaran_terakhir' => $buktiPembayaranTerakhir ? $buktiPembayaranTerakhir->fresh() : null,
            ]
        ]);

    } catch (\Exception $e) {
        DB::rollBack();

        \Log::error('UPDATE REVISI GAGAL', [
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Gagal update revisi',
            'error' => $e->getMessage()
        ], 422);
    }
}


public function Upload_Bukti_Pembayaran($id_pembayaran, $id_siswa)
{
    $siswa = Siswa::with('pembayaran')
        ->where('id_siswa', $id_siswa)
        ->firstOrFail();

$pembayaranBelum = Pembayaran::where('id_pembayaran', $id_pembayaran)
    ->first();

if (!$pembayaranBelum || $pembayaranBelum->id_siswa != $id_siswa) {
    return response()->json([
        'success' => false,
        'message' => 'Data pembayaran tidak cocok'
    ], 404);
}

    if (!$pembayaranBelum) {
        return response()->json([
            'success' => false,
            'message' => 'Pembayaran belum ditemukan'
        ], 404);
    }

    return response()->json([
        'success' => true,
        'message' => 'Data siap upload bukti',
        'data' => [
            'siswa' => $siswa,
            'pembayaran' => $pembayaranBelum
        ]
    ]);
}


public function Store_Bukti_Pembayaran(Request $request, $id_pembayaran)
{
    $request->validate([
        'tanggal_bukti_bayar' => 'required|date',
        'bukti_bayar' => 'required|file|mimes:jpg,jpeg,png,pdf'
    ]);

    DB::beginTransaction();

    try {

        $pembayaran = Pembayaran::where('id_pembayaran', $id_pembayaran)
            ->firstOrFail();

        $filePath = $request->file('bukti_bayar')
            ->store('bukti_pembayaran');

        $bukti = BuktiPembayaran::create([
            'id_pembayaran' => $pembayaran->id_pembayaran,
            'id_siswa' => $pembayaran->id_siswa,
            'periode' => $pembayaran->periode,
            'tanggal_bukti_bayar' => $request->tanggal_bukti_bayar,
            'status' => 'Menunggu validasi',
            'bukti_bayar' => $filePath
        ]);

        $pembayaran->update([
            'tanggal_bayar' => $request->tanggal_bukti_bayar
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Bukti pembayaran berhasil diupload',
            'data' => $bukti
        ]);

    } catch (\Throwable $e) {
        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => 'Upload gagal',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function kehadiranSiswa(Request $request)
{
    $user = Auth::user();
    $bulan = (int) ($request->bulan ?? now()->month);
    $tahun = (int) ($request->tahun ?? now()->year);
    $tahunRekap = (int) ($request->tahun_rekap ?? $tahun);

    if ($bulan < 1 || $bulan > 12) {
        return response()->json([
            'status' => false,
            'message' => 'Bulan harus bernilai 1 sampai 12'
        ], 422);
    }

    $ortu = OrangTua::where('user_id', $user->id)->first();

    if (!$ortu) {
        return response()->json([
            'status' => false,
            'message' => 'Data orang tua tidak ditemukan'
        ], 404);
    }

    $idSiswa = $request->id_siswa ?? session('id_siswa');

    $siswaQuery = Siswa::where('id_ortu', $ortu->id_ortu);

    if ($idSiswa) {
        $siswaQuery->where('id_siswa', $idSiswa);
    }

    $siswa = $siswaQuery->first(['id_siswa', 'nama_siswa', 'umur']);

    if (!$siswa) {
        return response()->json([
            'status' => false,
            'message' => $idSiswa
                ? 'Anak tidak ditemukan atau tidak terkait dengan akun orang tua ini'
                : 'Silakan pilih anak terlebih dahulu'
        ], 404);
    }

    session(['id_siswa' => $siswa->id_siswa]);

    $presensiBulanIni = $siswa->presensi()
        ->whereMonth('created_at', $bulan)
        ->whereYear('created_at', $tahun)
        ->get();

    $ringkasanBulanIni = $this->formatRingkasanKehadiran($presensiBulanIni);

    $riwayatBulanan = $siswa->presensi()
        ->selectRaw('YEAR(created_at) as tahun, MONTH(created_at) as bulan, COUNT(*) as total')
        ->selectRaw("SUM(CASE WHEN status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) as hadir")
        ->selectRaw("SUM(CASE WHEN status_kehadiran = 'Sakit' THEN 1 ELSE 0 END) as sakit")
        ->selectRaw("SUM(CASE WHEN status_kehadiran = 'Izin' THEN 1 ELSE 0 END) as izin")
        ->whereYear('created_at', $tahunRekap)
        ->groupByRaw('YEAR(created_at), MONTH(created_at)')
        ->orderByRaw('YEAR(created_at) DESC, MONTH(created_at) DESC')
        ->get()
        ->map(function ($item) {
            $persentaseHadir = $item->total > 0
                ? round(($item->hadir / $item->total) * 100, 1)
                : 0;

            return [
                'tahun' => (int) $item->tahun,
                'bulan' => (int) $item->bulan,
                'nama_bulan' => $this->namaBulanIndonesia((int) $item->bulan),
                'hadir' => (int) $item->hadir,
                'sakit' => (int) $item->sakit,
                'izin' => (int) $item->izin,
                'total' => (int) $item->total,
                'persentase_hadir' => $persentaseHadir,
            ];
        })
        ->values();

    return response()->json([
        'status' => true,
        'message' => 'Rekap kehadiran anak berhasil diambil',
        'anak' => [
            'id_siswa' => $siswa->id_siswa,
            'nama_siswa' => $siswa->nama_siswa,
            'umur' => 'U-' . $siswa->umur,
        ],
        'filter' => [
            'bulan' => $bulan,
            'nama_bulan' => $this->namaBulanIndonesia($bulan),
            'tahun' => $tahun,
            'tahun_rekap' => $tahunRekap,
        ],
        'bulan_berjalan' => $ringkasanBulanIni,
        'grafik_bulan_berjalan' => [
            [
                'label' => 'Hadir',
                'jumlah' => $ringkasanBulanIni['hadir'],
                'persentase' => $ringkasanBulanIni['persen_hadir'],
            ],
            [
                'label' => 'Sakit',
                'jumlah' => $ringkasanBulanIni['sakit'],
                'persentase' => $ringkasanBulanIni['persen_sakit'],
            ],
            [
                'label' => 'Izin',
                'jumlah' => $ringkasanBulanIni['izin'],
                'persentase' => $ringkasanBulanIni['persen_izin'],
            ],
        ],
        'rekap_bulanan' => $riwayatBulanan,
    ]);
}

private function formatRingkasanKehadiran($presensi)
{
    $total = $presensi->count();
    $hadir = $presensi->where('status_kehadiran', 'Hadir')->count();
    $sakit = $presensi->where('status_kehadiran', 'Sakit')->count();
    $izin = $presensi->where('status_kehadiran', 'Izin')->count();

    return [
        'hadir' => $hadir,
        'sakit' => $sakit,
        'izin' => $izin,
        'total' => $total,
        'persen_hadir' => $total ? round(($hadir / $total) * 100, 1) : 0,
        'persen_sakit' => $total ? round(($sakit / $total) * 100, 1) : 0,
        'persen_izin' => $total ? round(($izin / $total) * 100, 1) : 0,
    ];
}

private function namaBulanIndonesia(int $bulan): string
{
    $namaBulan = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    return $namaBulan[$bulan] ?? '-';
}

public function performaSiswa(Request $request)
{
    $user = Auth::user();
    $idSiswaRequest = $request->id_siswa ?? session('id_siswa');

    if ($user->role === 'orang_tua') {
        $ortu = OrangTua::where('user_id', $user->id)->first();

        if (!$ortu) {
            return response()->json([
                'status' => false,
                'message' => 'Data orang tua tidak ditemukan'
            ], 404);
        }

        $siswaQuery = Siswa::where('id_ortu', $ortu->id_ortu);

        if ($idSiswaRequest) {
            $siswaQuery->where('id_siswa', $idSiswaRequest);
        }

        $siswa = $siswaQuery->first(['id_siswa', 'nama_siswa', 'umur']);
    } else {
        $siswa = Siswa::where('user_id', $user->id)
            ->first(['id_siswa', 'nama_siswa', 'umur']);
    }

    if (!$siswa) {
        return response()->json([
            'status' => false,
            'message' => $idSiswaRequest
                ? 'Siswa tidak ditemukan atau tidak terkait dengan akun ini'
                : 'Silakan pilih siswa terlebih dahulu'
        ], 404);
    }

    session(['id_siswa' => $siswa->id_siswa]);

    $tahunOptions = Performa_Siswa::where('id_siswa', $siswa->id_siswa)
        ->selectRaw('YEAR(tanggal_penilaian) as tahun')
        ->whereNotNull('tanggal_penilaian')
        ->distinct()
        ->orderBy('tahun', 'desc')
        ->pluck('tahun')
        ->map(fn ($tahun) => (int) $tahun)
        ->values();

    $tahunDipilih = (int) ($request->tahun ?: ($tahunOptions->first() ?? now()->year));

    $performaBulanan = Performa_Siswa::where('id_siswa', $siswa->id_siswa)
        ->whereYear('tanggal_penilaian', $tahunDipilih)
        ->selectRaw('MONTH(tanggal_penilaian) as bulan')
        ->selectRaw('AVG(dribbling) as dribbling')
        ->selectRaw('AVG(passing) as passing')
        ->selectRaw('AVG(shooting) as shooting')
        ->groupByRaw('MONTH(tanggal_penilaian)')
        ->orderByRaw('MONTH(tanggal_penilaian)')
        ->get()
        ->keyBy(fn ($item) => (int) $item->bulan);

    $grafikBatang = collect(range(1, 12))->map(function ($bulan) use ($performaBulanan) {
        $data = $performaBulanan->get($bulan);
        $rataRata = $data
            ? round((((float) $data->dribbling) + ((float) $data->passing) + ((float) $data->shooting)) / 3, 1)
            : null;

        return [
            'bulan' => $bulan,
            'nama_bulan' => $this->namaBulanIndonesia($bulan),
            'total_nilai' => $rataRata,
        ];
    })->values();

    $performaPerBulan = collect(range(1, 12))->map(function ($bulan) use ($performaBulanan, $tahunDipilih) {
        $data = $performaBulanan->get($bulan);

        if (!$data) {
            return [
                'bulan' => $bulan,
                'nama_bulan' => $this->namaBulanIndonesia($bulan),
                'waktu' => $this->namaBulanIndonesia($bulan) . ' ' . $tahunDipilih,
                'dribbling' => null,
                'passing' => null,
                'shooting' => null,
                'rata_rata' => null,
                'keterangan' => null,
            ];
        }

        $dribbling = round((float) $data->dribbling, 1);
        $passing = round((float) $data->passing, 1);
        $shooting = round((float) $data->shooting, 1);
        $rataRata = round(($dribbling + $passing + $shooting) / 3, 1);

        return [
            'bulan' => $bulan,
            'nama_bulan' => $this->namaBulanIndonesia($bulan),
            'waktu' => $this->namaBulanIndonesia($bulan) . ' ' . $tahunDipilih,
            'dribbling' => $dribbling,
            'passing' => $passing,
            'shooting' => $shooting,
            'rata_rata' => $rataRata,
            'keterangan' => $this->tentukanKeteranganPerforma($rataRata),
        ];
    })->values();

    $catatanPelatih = Catatan_Pelatih::with('pelatih:id_pelatih,nama_pelatih')
        ->where('id_siswa', $siswa->id_siswa)
        ->orderBy('tanggal_catatan', 'desc')
        ->orderBy('id_catatan', 'desc')
        ->get()
        ->map(function ($item) {
            return [
                'id_catatan' => $item->id_catatan,
                'id_pelatih' => $item->id_pelatih,
                'nama_pelatih' => $item->pelatih->nama_pelatih ?? null,
                'catatan' => $item->catatan,
                'tanggal_catatan' => $item->tanggal_catatan,
            ];
        })
        ->values();

    return response()->json([
        'status' => true,
        'message' => 'Data performa siswa berhasil diambil',
        'siswa' => [
            'id_siswa' => $siswa->id_siswa,
            'nama_siswa' => $siswa->nama_siswa,
            'umur' => 'U-' . $siswa->umur,
        ],
        'filter' => [
            'tahun' => $tahunDipilih,
            'tahun_options' => $tahunOptions->isNotEmpty()
                ? $tahunOptions
                : collect([(int) now()->year]),
        ],
        'grafik_performa' => $grafikBatang,
        'catatan_pelatih' => $catatanPelatih,
        'performa_per_bulan' => $performaPerBulan,
    ]);
}

private function tentukanKeteranganPerforma(float $rataRata): string
{
    if ($rataRata >= 85) {
        return 'A';
    }

    if ($rataRata >= 70) {
        return 'B';
    }

    if ($rataRata >= 55) {
        return 'C';
    }

    return 'D';
}

public function prestasiSiswa(Request $request)
{
    $user = Auth::user();
    $idSiswaRequest = $request->id_siswa ?? session('id_siswa');
    $bulanDipilih = (int) ($request->bulan ?? now()->month);
    $tahunSekarang = now()->year;

    if ($bulanDipilih < 1 || $bulanDipilih > 12) {
        return response()->json([
            'status' => false,
            'message' => 'Bulan harus bernilai 1 sampai 12'
        ], 422);
    }

    if ($user->role === 'orang_tua') {
        $ortu = OrangTua::where('user_id', $user->id)->first();

        if (!$ortu) {
            return response()->json([
                'status' => false,
                'message' => 'Data orang tua tidak ditemukan'
            ], 404);
        }

        $siswaQuery = Siswa::where('id_ortu', $ortu->id_ortu);

        if ($idSiswaRequest) {
            $siswaQuery->where('id_siswa', $idSiswaRequest);
        }

        $siswa = $siswaQuery->first(['id_siswa', 'nama_siswa', 'umur']);
    } else {
        $siswa = Siswa::where('user_id', $user->id)
            ->first(['id_siswa', 'nama_siswa', 'umur']);
    }

    if (!$siswa) {
        return response()->json([
            'status' => false,
            'message' => $idSiswaRequest
                ? 'Siswa tidak ditemukan atau tidak terkait dengan akun ini'
                : 'Silakan pilih siswa terlebih dahulu'
        ], 404);
    }

    session(['id_siswa' => $siswa->id_siswa]);

    $tahunOptions = Pencapaian::where('id_siswa', $siswa->id_siswa)
        ->selectRaw('YEAR(tanggal_diberikan) as tahun')
        ->whereNotNull('tanggal_diberikan')
        ->distinct()
        ->orderBy('tahun', 'desc')
        ->pluck('tahun')
        ->map(fn ($tahun) => (int) $tahun)
        ->values();

    $tahunDipilih = (int) ($request->tahun ?: ($tahunOptions->first() ?? $tahunSekarang));

    $prestasi = Pencapaian::with('badge:id_badge,nama_badge,deskripsi,icon_badge')
        ->where('id_siswa', $siswa->id_siswa)
        ->whereMonth('tanggal_diberikan', $bulanDipilih)
        ->whereYear('tanggal_diberikan', $tahunDipilih)
        ->orderBy('tanggal_diberikan', 'desc')
        ->orderBy('id_pencapaian', 'desc')
        ->get()
        ->map(function ($item) {
            return [
                'id_pencapaian' => $item->id_pencapaian,
                'id_badge' => $item->id_badge,
                'nama_prestasi' => $item->badge->nama_badge ?? null,
                'deskripsi' => $item->badge->deskripsi ?? null,
                'icon_badge' => $item->badge->icon_badge ?? null,
                'tanggal_diberikan' => $item->tanggal_diberikan,
            ];
        })
        ->values();

    $bulanOptions = collect(range(1, 12))->map(function ($bulan) {
        return [
            'bulan' => $bulan,
            'nama_bulan' => $this->namaBulanIndonesia($bulan),
        ];
    })->values();

    return response()->json([
        'status' => true,
        'message' => 'Data prestasi siswa berhasil diambil',
        'siswa' => [
            'id_siswa' => $siswa->id_siswa,
            'nama_siswa' => $siswa->nama_siswa,
            'umur' => 'U-' . $siswa->umur,
        ],
        'filter' => [
            'bulan' => $bulanDipilih,
            'nama_bulan' => $this->namaBulanIndonesia($bulanDipilih),
            'tahun' => $tahunDipilih,
        ],
        'options' => [
            'bulan' => $bulanOptions,
            'tahun' => $tahunOptions->isNotEmpty()
                ? $tahunOptions
                : collect([(int) $tahunSekarang]),
        ],
        'total_prestasi' => $prestasi->count(),
        'data' => $prestasi,
    ]);
}

public function catatanPelatihSiswa(Request $request)
{
    $user = Auth::user();
    $idSiswaRequest = $request->id_siswa ?? session('id_siswa');
    $bulanDipilih = (int) ($request->bulan ?? now()->month);
    $tahunSekarang = now()->year;

    if ($bulanDipilih < 1 || $bulanDipilih > 12) {
        return response()->json([
            'status' => false,
            'message' => 'Bulan harus bernilai 1 sampai 12'
        ], 422);
    }

    if ($user->role === 'orang_tua') {
        $ortu = OrangTua::where('user_id', $user->id)->first();

        if (!$ortu) {
            return response()->json([
                'status' => false,
                'message' => 'Data orang tua tidak ditemukan'
            ], 404);
        }

        $siswaQuery = Siswa::where('id_ortu', $ortu->id_ortu);

        if ($idSiswaRequest) {
            $siswaQuery->where('id_siswa', $idSiswaRequest);
        }

        $siswa = $siswaQuery->first(['id_siswa', 'nama_siswa', 'umur']);
    } else {
        $siswa = Siswa::where('user_id', $user->id)
            ->first(['id_siswa', 'nama_siswa', 'umur']);
    }

    if (!$siswa) {
        return response()->json([
            'status' => false,
            'message' => $idSiswaRequest
                ? 'Siswa tidak ditemukan atau tidak terkait dengan akun ini'
                : 'Silakan pilih siswa terlebih dahulu'
        ], 404);
    }

    session(['id_siswa' => $siswa->id_siswa]);

    $tahunOptions = Catatan_Pelatih::where('id_siswa', $siswa->id_siswa)
        ->selectRaw('YEAR(tanggal_catatan) as tahun')
        ->whereNotNull('tanggal_catatan')
        ->distinct()
        ->orderBy('tahun', 'desc')
        ->pluck('tahun')
        ->map(fn ($tahun) => (int) $tahun)
        ->values();

    $tahunDipilih = (int) ($request->tahun ?: ($tahunOptions->first() ?? $tahunSekarang));

    $catatan = Catatan_Pelatih::with('pelatih:id_pelatih,nama_pelatih')
        ->where('id_siswa', $siswa->id_siswa)
        ->whereMonth('tanggal_catatan', $bulanDipilih)
        ->whereYear('tanggal_catatan', $tahunDipilih)
        ->orderBy('tanggal_catatan', 'desc')
        ->orderBy('id_catatan', 'desc')
        ->get()
        ->map(function ($item) {
            return [
                'id_catatan' => $item->id_catatan,
                'id_pelatih' => $item->id_pelatih,
                'nama_pelatih' => $item->pelatih->nama_pelatih ?? null,
                'catatan' => $item->catatan,
                'tanggal_catatan' => $item->tanggal_catatan,
            ];
        })
        ->values();

    $bulanOptions = collect(range(1, 12))->map(function ($bulan) {
        return [
            'bulan' => $bulan,
            'nama_bulan' => $this->namaBulanIndonesia($bulan),
        ];
    })->values();

    return response()->json([
        'status' => true,
        'message' => 'Data catatan pelatih siswa berhasil diambil',
        'siswa' => [
            'id_siswa' => $siswa->id_siswa,
            'nama_siswa' => $siswa->nama_siswa,
            'umur' => 'U-' . $siswa->umur,
        ],
        'filter' => [
            'bulan' => $bulanDipilih,
            'nama_bulan' => $this->namaBulanIndonesia($bulanDipilih),
            'tahun' => $tahunDipilih,
        ],
        'options' => [
            'bulan' => $bulanOptions,
            'tahun' => $tahunOptions->isNotEmpty()
                ? $tahunOptions
                : collect([(int) $tahunSekarang]),
        ],
        'total_catatan' => $catatan->count(),
        'data' => $catatan,
    ]);
}

public function historyPembayaranSiswa(Request $request)
{
    $user = Auth::user();
    $idSiswaRequest = $request->id_siswa ?? session('id_siswa');

    if ($user->role === 'orang_tua') {
        $ortu = OrangTua::where('user_id', $user->id)->first();

        if (!$ortu) {
            return response()->json([
                'status' => false,
                'message' => 'Data orang tua tidak ditemukan'
            ], 404);
        }

        $siswaQuery = Siswa::where('id_ortu', $ortu->id_ortu);

        if ($idSiswaRequest) {
            $siswaQuery->where('id_siswa', $idSiswaRequest);
        }

        $siswa = $siswaQuery->first(['id_siswa', 'nama_siswa', 'umur', 'status']);
    } else {
        $siswa = Siswa::where('user_id', $user->id)
            ->first(['id_siswa', 'nama_siswa', 'umur', 'status']);
    }

    if (!$siswa) {
        return response()->json([
            'status' => false,
            'message' => $idSiswaRequest
                ? 'Siswa tidak ditemukan atau tidak terkait dengan akun ini'
                : 'Silakan pilih siswa terlebih dahulu'
        ], 404);
    }

    session(['id_siswa' => $siswa->id_siswa]);

    $query = Pembayaran::where('id_siswa', $siswa->id_siswa);

    if ($request->filled('jenis')) {
        $query->where('jenis', $request->jenis);
    }

    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    $pembayaran = $query
        ->orderByRaw('CASE WHEN tanggal_bayar IS NULL THEN 0 ELSE 1 END ASC')
        ->orderBy('id_pembayaran', 'desc')
        ->get();

    $buktiPembayaran = BuktiPembayaran::where('id_siswa', $siswa->id_siswa)
        ->whereIn('id_pembayaran', $pembayaran->pluck('id_pembayaran'))
        ->orderBy('tanggal_bukti_bayar', 'desc')
        ->orderBy('id_bukti_pembayaran', 'desc')
        ->get()
        ->groupBy('id_pembayaran');

    $data = $pembayaran->map(function ($item) use ($buktiPembayaran) {
        $buktiTerakhir = optional($buktiPembayaran->get($item->id_pembayaran))->first();

        return [
            'id_pembayaran' => $item->id_pembayaran,
            'jenis' => $item->jenis,
            'periode' => $item->periode,
            'jumlah' => $item->jumlah,
            'status_pembayaran' => $item->status,
            'tanggal_bayar' => $item->tanggal_bayar,
            'status_bukti' => $buktiTerakhir->status ?? null,
            'tanggal_bukti_bayar' => $buktiTerakhir->tanggal_bukti_bayar ?? null,
            'bukti_bayar' => $buktiTerakhir->bukti_bayar ?? null,
        ];
    })->values();

    $summaryBukti = BuktiPembayaran::where('id_siswa', $siswa->id_siswa);

    return response()->json([
        'status' => true,
        'message' => 'History pembayaran siswa berhasil diambil',
        'siswa' => [
            'id_siswa' => $siswa->id_siswa,
            'nama_siswa' => $siswa->nama_siswa,
            'umur' => 'U-' . $siswa->umur,
            'status' => $siswa->status,
        ],
        'filters' => [
            'jenis' => $request->jenis,
            'status' => $request->status,
        ],
        'summary' => [
            'total_tagihan' => $data->count(),
            'belum_lunas' => $data->where('status_pembayaran', 'Belum')->count(),
            'lunas' => $data->where('status_pembayaran', 'Lunas')->count(),
            'pending' => (clone $summaryBukti)->where('status', 'Menunggu validasi')->count(),
            'diterima' => (clone $summaryBukti)->where('status', 'diterima')->count(),
            'ditolak' => (clone $summaryBukti)->where('status', 'ditolak')->count(),
        ],
        'data' => $data,
    ]);
}



}
