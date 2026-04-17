<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Siswa;
use App\Models\OrangTua;
use App\Models\Admin;
use App\Models\Pelatih;
use App\Models\Notifikasi;
use App\Models\NotifikasiTerkirim;
use App\Models\Pembayaran;
use App\Models\Pwndaftaran_Siswa;
use App\Models\BuktiPembayaran;
use App\Models\Jadwal_Latihan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Pendaftaran_Siswa;
use Carbon\Carbon;

class AdminController extends Controller
{
    public function Admin_Pendaftaran_siswa(Request $request)
{
    $query = \App\Models\Pendaftaran_Siswa::with([
        'siswa',
        'siswa.orangtua'
    ]);

    // SUMMARY
    $summary = [
        'disetujui' => \App\Models\Pendaftaran_Siswa::where('status_approval', 'Disetujui')->count(),
        'revisi'    => \App\Models\Pendaftaran_Siswa::where('status_approval', 'Revisi')->count(),
        'ditolak'   => \App\Models\Pendaftaran_Siswa::where('status_approval', 'Ditolak')->count(),
    ];

if ($request->filled('search')) {
    $query->where(function ($q) use ($request) {

        // search nama siswa
        $q->whereHas('siswa', function ($sub) use ($request) {
            $sub->where('nama_siswa', 'like', '%' . $request->search . '%');
        })

        // OR search status approval
        ->orWhere('status_approval', 'like', '%' . $request->search . '%');
    });
}

    // FILTER STATUS
    if ($request->filled('status')) {
        $query->where('status_approval', $request->status);
    }

    // PAGINATION
    $pendaftaran = $query
        ->orderBy('tanggal_daftar', 'desc')
        ->paginate(10);

    return response()->json([
        'status' => true,
        'message' => 'Data pendaftaran siswa',
        'data' => $pendaftaran,
        'summary' => $summary
    ]);
}


public function Admin_validasi_Pendaftaran_siswa($id)
{
    $pendaftaran = Pendaftaran_Siswa::with(['siswa.orangtua'])
        ->where('id_siswa', $id)
        ->first();

    if (!$pendaftaran) {
        return response()->json([
            'success' => false,
            'message' => 'Data tidak ditemukan'
        ], 404);
    }

    return response()->json([
        'success' => true,
        'data' => $pendaftaran
    ]);
}

public function lihatfilePendaftaran($jenis, $filename)
{
    $allowed = ['akta', 'kk', 'rapor', 'foto'];

    if (!in_array($jenis, $allowed)) {
        return response()->json([
            'success' => false,
            'message' => 'Jenis file tidak valid'
        ], 403);
    }

    // ❗ FIX DUPLIKAT .png
    $filename = preg_replace('/(\.png)+$/', '.png', $filename);

    $path = storage_path("app/$jenis/$filename");

    if (!file_exists($path)) {
        return response()->json([
            'success' => false,
            'message' => 'File tidak ditemukan',
            'debug_path' => $path
        ], 404);
    }

    return response()->file($path);
}


public function submitValidasi(Request $request, $id)
{
    $pendaftaran = Pendaftaran_Siswa::where('id_pendaftaran', $id)->firstOrFail();

    $fields = [
        'val_nama_siswa',
        'val_nama_ibu',
        'val_nama_ayah',
        'val_umur',
        'val_akta',
        'val_kk',
        'val_rapor',
        'val_foto',
    ];

    foreach ($fields as $field) {
        if (!$request->has($field)) {
            return response()->json([
                'success' => false,
                'message' => "$field wajib dikirim"
            ], 422);
        }
    }

    foreach ($fields as $field) {
        $pendaftaran->$field = $request->$field;
    }

    $values = array_map(fn($v) => strtolower(trim($v ?? '')), $request->only($fields));

    if (!in_array('valid', $values)) {
        $statusApproval = 'Revisi';
    } elseif (count(array_unique($values)) === 1 && reset($values) === 'valid') {
        $statusApproval = 'Disetujui';
    } else {
        $statusApproval = 'Revisi';
    }

    $pendaftaran->status_approval = $statusApproval;
    $pendaftaran->save();

    // =========================
    // PAYMENT LOGIC
    // =========================
    $paymentCreated = false;
    $paymentData = null;

    if ($statusApproval === 'Disetujui') {

        $cekPembayaran = Pembayaran::where('id_siswa', $pendaftaran->id_siswa)
            ->where('jenis', 'Pendaftaran')
            ->first();

        if (!$cekPembayaran) {
            $paymentData = Pembayaran::create([
                'id_siswa'      => $pendaftaran->id_siswa,
                'periode'       => date('Y'),
                'jumlah'        => 280000,
                'tanggal_bayar' => null,
                'status'        => 'Belum',
                'jenis'         => 'Pendaftaran',
            ]);

            $paymentCreated = true;
        }
    }

    return response()->json([
        'success' => true,
        'message' => 'Validasi berhasil disimpan',
        'status_approval' => $statusApproval,
        'payment_created' => $paymentCreated,
        'data_pembayaran' => $paymentData,
        'data' => $pendaftaran->fresh()
    ]);
}


public function pembayaran_admin(Request $request)
{
    $siswaList = Siswa::whereHas('pendaftaran', function ($q) {
        $q->where('status_approval', 'Disetujui');
    })
    ->select('id_siswa','nama_siswa')
    ->get();

    $query = Pembayaran::with(['siswa.pendaftaran'])
        ->whereHas('siswa.pendaftaran', function ($q) {
            $q->where('status_approval', 'Disetujui');
        });

    // =========================
    // FILTER: NAMA SISWA
    // =========================
    if ($request->filled('nama_siswa')) {
        $query->whereHas('siswa', function ($q) use ($request) {
            $q->where('nama_siswa', 'like', '%' . $request->nama_siswa . '%');
        });
    }

    // =========================
    // FILTER: JENIS
    // =========================
    if ($request->filled('jenis')) {
        $query->where('jenis', $request->jenis);
    }

    // =========================
    // FILTER: STATUS
    // =========================
    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    // =========================
    // PAGINATION
    // =========================
    $pembayaran = $query->orderBy('id_pembayaran', 'desc')
        ->paginate(10)
        ->withQueryString();

    return response()->json([
        'success' => true,
        'message' => 'Data pembayaran berhasil diambil',
        'data' => $pembayaran,
        'filters' => [
            'nama_siswa' => $request->nama_siswa,
            'jenis' => $request->jenis,
            'status' => $request->status,
        ],
        'siswa_list' => $siswaList
    ]);
}


public function buktipembayaran_admin(Request $request, $id_siswa)
{
    $siswa = Siswa::findOrFail($id_siswa);

    $query = BuktiPembayaran::with('siswa')
        ->where('id_siswa', $id_siswa)
        ->whereHas('pembayaran', function ($q) use ($request) {
            if ($request->filled('jenis')) {
                $q->where('jenis', $request->jenis);
            }
        });

    $data = $query->orderBy('tanggal_bukti_bayar', 'DESC')
        ->paginate(10)
        ->withQueryString();

    // summary
    $baseQuery = BuktiPembayaran::where('id_siswa', $id_siswa);

    $pending = (clone $baseQuery)->where('status', 'Menunggu validasi')->count();
    $diterima = (clone $baseQuery)->where('status', 'diterima')->count();
    $ditolak = (clone $baseQuery)->where('status', 'ditolak')->count();

    return response()->json([
        'success' => true,
        'message' => 'Data bukti pembayaran berhasil diambil',
        'siswa' => $siswa,
        'data' => $data,
        'summary' => [
            'pending' => $pending,
            'diterima' => $diterima,
            'ditolak' => $ditolak
        ]
    ]);
}


public function lihatBukti_pembayaran_admin($folder, $file)
{
    $user = auth()->user();

    if (!in_array($user->role, ['admin', 'pelatih'])) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $path = storage_path("app/{$folder}/{$file}");

    if (!file_exists($path)) {
        return response()->json(['message' => 'File tidak ditemukan'], 404);
    }

    return response()->file($path);
}


public function Bukti_Diterima($id)
{
    DB::beginTransaction();

    try {
        $bukti = BuktiPembayaran::with('siswa')->findOrFail($id);

        // ======================
        // APPROVE BUKTI
        // ======================
        $bukti->update([
            'status' => 'diterima'
        ]);

        // ======================
        // UPDATE SISWA
        // ======================
        if ($bukti->siswa) {
            $bukti->siswa->update([
                'status' => 'Active'
            ]);
        }

        // ======================
        // UPDATE PEMBAYARAN
        // ======================
        $pembayaran = Pembayaran::find($bukti->id_pembayaran);

        if ($pembayaran) {
            $pembayaran->update([
                'status' => 'Lunas'
            ]);
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Bukti diterima, akun siswa sudah aktif dan pembayaran lunas',
            'data' => [
                'bukti' => $bukti,
                'siswa_status' => $bukti->siswa->status ?? null,
                'pembayaran_status' => $pembayaran->status ?? null
            ]
        ]);

    } catch (\Throwable $e) {
        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => 'Gagal approve bukti',
            'error' => $e->getMessage()
        ], 500);
    }
}


public function Bukti_Ditolak($id)
{
    $bukti = BuktiPembayaran::with('siswa')->findOrFail($id);

    DB::beginTransaction();

    try {

        // =====================
        // UPDATE BUKTI
        // =====================
        $bukti->status = 'ditolak';
        $bukti->save();

        // =====================
        // UPDATE SISWA
        // =====================
        if ($bukti->siswa) {
            $bukti->siswa->status = 'Inactive';
            $bukti->siswa->save();
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Bukti ditolak, siswa tetap inactive',
            'data' => $bukti
        ]);

    } catch (\Throwable $e) {
        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => 'Gagal reject bukti',
            'error' => $e->getMessage()
        ], 500);
    }
}


public function history_pembayaran(Request $request)
{
    // =========================
    // QUERY HISTORY DARI TABEL PEMBAYARAN
    // =========================
    $query = Pembayaran::with([
            'siswa:id_siswa,nama_siswa'
        ]);

    // =========================
    // FILTER: NAMA SISWA
    // =========================
    if ($request->filled('nama_siswa')) {
        $query->whereHas('siswa', function ($q) use ($request) {
            $q->where('nama_siswa', 'like', '%' . $request->nama_siswa . '%');
        });
    }

    // =========================
    // FILTER: JENIS
    // =========================
    if ($request->filled('jenis')) {
        $query->where('jenis', $request->jenis);
    }

    // =========================
    // FILTER: STATUS
    // =========================
    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    // =========================
    // FILTER: TANGGAL BAYAR
    // =========================
    if ($request->filled('tanggal_bayar')) {
        $query->whereDate('tanggal_bayar', $request->tanggal_bayar);
    }

    // =========================
    // DATA HISTORY (SORT TERBARU)
    // =========================
   $history = $query
    ->orderBy('tanggal_bayar', 'desc')
    ->paginate(10)
    ->withQueryString();

    // =========================
    // RESPONSE
    // =========================
    return response()->json([
        'success' => true,
        'message' => 'History pembayaran berhasil diambil',
        'data' => $history,
        'filters' => [
            'nama_siswa' => $request->nama_siswa,
            'jenis' => $request->jenis,
            'status' => $request->status,
            'tanggal_bayar' => $request->tanggal_bayar,
        ]
    ]);
}


public function Data_Siswa(Request $request)
{
    $query = Siswa::with('user');

    // =========================
    // SEARCH (NAMA + EMAIL + PARAM EMAIL)
    // =========================
    if ($request->filled('nama_siswa') || $request->filled('email')) {

        $search = $request->nama_siswa ?? $request->email;

        $query->where(function ($q) use ($search) {
            $q->where('nama_siswa', 'like', "%$search%")
              ->orWhereHas('user', function ($q2) use ($search) {
                  $q2->where('email', 'like', "%$search%");
              });
        });
    }

    // =========================
    // FILTER UMUR U9 - U18
    // =========================
    if ($request->filled('kategori_umur')) {
        if (preg_match('/U(\d+)/', strtoupper($request->kategori_umur), $match)) {
            $query->where('umur', (int) $match[1]);
        }
    }

    // =========================
    // RESULT
    // =========================
    $siswa = $query
        ->orderBy('nama_siswa', 'desc')
        ->paginate(10)
        ->withQueryString();

    return response()->json([
        'success' => true,
        'message' => 'Data siswa berhasil diambil',
        'data' => $siswa
    ]);
}

public function performaperSiswa(Request $request, $id_siswa)
{
    $bulan = $request->bulan;
    $tahun = $request->tahun;

    $query = DB::table('performa_siswa')
        ->where('id_siswa', $id_siswa);

    // ✅ filter dari tanggal_penilaian
    if ($bulan) {
        $query->whereMonth('tanggal_penilaian', $bulan);
    }

    if ($tahun) {
        $query->whereYear('tanggal_penilaian', $tahun);
    }

    $data = $query->selectRaw('
        COUNT(*) as total_latihan,
        AVG(dribbling) as rata_dribbling,
        AVG(passing) as rata_passing,
        AVG(shooting) as rata_shooting
    ')->first();

    if (!$data || $data->total_latihan == 0) {
        return response()->json([
            'status' => false,
            'message' => 'Belum ada data performa'
        ]);
    }

    return response()->json([
        'status' => true,
        'id_siswa' => $id_siswa,
        'filter' => [
            'bulan' => $bulan,
            'tahun' => $tahun
        ],
        'data' => $data
    ]);
}

public function Rekap_Absensi_PerSiswa(Request $request, $id_siswa)
{
    $bulan = $request->bulan ?? now()->month;
    $tahun = $request->tahun ?? now()->year;

    $siswa = Siswa::find($id_siswa);

    if (!$siswa) {
        return response()->json([
            'status' => false,
            'message' => 'Siswa tidak ditemukan'
        ], 404);
    }

    $presensi = $siswa->presensi()
        ->whereMonth('created_at', $bulan)
        ->whereYear('created_at', $tahun)
        ->get();

    $total = $presensi->count();

    $hadir = $presensi->where('status_kehadiran', 'Hadir')->count();
    $sakit = $presensi->where('status_kehadiran', 'Sakit')->count();
    $izin  = $presensi->where('status_kehadiran', 'Izin')->count();

    return response()->json([
        'status' => true,
        'message' => 'Rekap absensi per siswa berhasil',
        'id_siswa' => $siswa->id_siswa,
        'nama_siswa' => $siswa->nama_siswa,
        'umur' => 'U-' . $siswa->umur,

        'filter' => [
            'bulan' => $bulan,
            'tahun' => $tahun
        ],

        // 🔢 jumlah
        'hadir' => $hadir,
        'sakit' => $sakit,
        'izin'  => $izin,
        'total' => $total,

        // 📊 persen
        'persen_hadir' => $total ? round(($hadir / $total) * 100, 1) : 0,
        'persen_sakit' => $total ? round(($sakit / $total) * 100, 1) : 0,
        'persen_izin'  => $total ? round(($izin / $total) * 100, 1) : 0,
    ]);
}

public function Data_Pelatih(Request $request)
{
    $pelatih = Pelatih::with('user');

    // =========================
    // FILTER NAMA PELATIH
    // =========================
    if ($request->filled('nama_pelatih')) {
        $pelatih->where('nama_pelatih', 'like', '%' . $request->nama_pelatih . '%');
    }

    // =========================
    // FILTER EMAIL (dari tabel user)
    // =========================
    if ($request->filled('email')) {
        $pelatih->whereHas('user', function ($q) use ($request) {
            $q->where('email', 'like', '%' . $request->email . '%');
        });
    }

    $result = $pelatih
        ->orderBy('nama_pelatih', 'desc')
        ->paginate(10)
        ->withQueryString();

    return response()->json([
        'success' => true,
        'message' => 'Data pelatih berhasil diambil',
        'data' => $result
    ]);
}

public function Tambah_Pelatih(Request $request)
{
    Log::info('REGISTER REQUEST', $request->all());

  $request->validate([
    'nama' => 'required|string|max:100|unique:pelatih,nama_pelatih',
    'email' => 'required|email|unique:users,email',
    'password' => 'required|min:6|confirmed',
    'no_hp' => 'required|string|max:100',
], [
    'nama.required' => 'Nama wajib diisi',
    'nama.unique' => 'Nama pelatih sudah digunakan',

    'email.required' => 'Email wajib diisi',
    'email.email' => 'Format email tidak valid',
    'email.unique' => 'Email sudah digunakan',

    'password.required' => 'Password wajib diisi',
    'password.min' => 'Password minimal 6 karakter',
    'password.confirmed' => 'Password tidak sama',

    'no_hp.required' => 'No HP wajib diisi',
]);

    DB::beginTransaction();

    try {

        $user = User::create([
            'name' => $request->nama,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'pelatih',
        ]);

        // format no HP
        $no_hp = str_replace(' ', '', $request->no_hp);

        if (substr($no_hp, 0, 1) === '0') {
            $no_hp = '+62' . substr($no_hp, 1);
        } elseif (substr($no_hp, 0, 2) === '62') {
            $no_hp = '+' . $no_hp;
        } elseif (substr($no_hp, 0, 3) !== '+62') {
            $no_hp = '+62' . $no_hp;
        }

        $pelatih = Pelatih::create([
            'user_id' => $user->id,
            'nama_pelatih' => $request->nama,
            'email' => $request->email,
            'no_hp' => $no_hp,
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Pelatih berhasil ditambahkan',
            'data' => [
                'user' => $user,
                'pelatih' => $pelatih
            ]
        ]);

    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => 'Gagal menambahkan pelatih',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function Update_Pelatih(Request $request, $id)
{
    $pelatih = Pelatih::findOrFail($id);

    // =========================
    // VALIDASI
    // =========================
    $request->validate([
        'nama' => 'required|string|max:100',
        'email' => 'required|email',
        'no_hp' => 'required|string|max:100',
    ]);

    // =========================
    // UPDATE USER (optional sync email/name)
    // =========================
    if ($pelatih->user) {
        $pelatih->user->update([
            'name' => $request->nama,
            'email' => $request->email,
        ]);
    }

    // =========================
    // UPDATE PELATIH
    // =========================
    $pelatih->update([
        'nama_pelatih' => $request->nama,
        'email' => $request->email,
        'no_hp' => $request->no_hp,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Berhasil update pelatih',
        'data' => $pelatih
    ]);
}

public function Hapus_Pelatih($id)
{
    $pelatih = Pelatih::findOrFail($id);

    DB::beginTransaction();

    try {

        // Hapus user dulu (relasi aman)
        if ($pelatih->user_id) {
            User::where('id', $pelatih->user_id)->delete();
        }

        // Hapus pelatih
        $pelatih->delete();

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Pelatih berhasil dihapus'
        ]);

    } catch (\Throwable $e) {
        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => 'Gagal menghapus pelatih',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function Jadwallatihan_Siswa(Request $request)
{
    $id_pelatih = $request->id_pelatih;

    $jadwal = \App\Models\Jadwal_Latihan::with('siswa.user')
        ->when($id_pelatih, function ($query) use ($id_pelatih) {
            $query->where('id_pelatih', $id_pelatih);
        })
        ->paginate(10);

    return response()->json([
        'status' => true,
        'total_jadwal_aktif' => $jadwal->total(),
        'data' => $jadwal
    ]);
}

public function JadwalperPelatih($id_pelatih)
{
    $jadwal = \App\Models\Jadwal_Latihan::with('siswa', 'pelatih')
        ->where('id_pelatih', $id_pelatih)
        ->get();

    if ($jadwal->isEmpty()) {
        return response()->json([
            'status' => false,
            'message' => 'Jadwal pelatih tidak ditemukan'
        ], 404);
    }

    return response()->json([
        'status' => true,
        'total' => $jadwal->count(),
        'data' => $jadwal
    ]);
}

public function Tambah_Jadwal(Request $request)
{
    $request->validate([
        'tanggal' => 'required|date',
        'jam_mulai' => 'required',
        'jam_selesai' => 'required',
        'lokasi' => 'required',
        'id_pelatih' => 'required|exists:pelatih,id_pelatih',
        'id_siswa' => 'required|array',
        'id_siswa.*' => 'exists:siswa,id_siswa',
    ]);

    $jadwal = \App\Models\Jadwal_Latihan::create([
        'tanggal' => $request->tanggal,
        'jam_mulai' => $request->jam_mulai,
        'jam_selesai' => $request->jam_selesai,
        'lokasi' => $request->lokasi,
        'id_pelatih' => $request->id_pelatih, // 👈 TAMBAHAN
    ]);

    $jadwal->siswa()->attach($request->id_siswa);

    return response()->json([
        'status' => true,
        'message' => 'Jadwal berhasil ditambahkan',
        'data' => $jadwal->load(['siswa', 'pelatih'])
    ]);
}

public function Update_Jadwal(Request $request, $id)
{
    $request->validate([
        'tanggal' => 'required|date',
        'jam_mulai' => 'required',
        'jam_selesai' => 'required',
        'lokasi' => 'required',
        'id_pelatih' => 'required|exists:pelatih,id_pelatih',
        'id_siswa' => 'required|array',
        'id_siswa.*' => 'exists:siswa,id_siswa',
    ]);

    $jadwal = \App\Models\Jadwal_Latihan::findOrFail($id);

    $jadwal->update([
        'tanggal' => $request->tanggal,
        'jam_mulai' => $request->jam_mulai,
        'jam_selesai' => $request->jam_selesai,
        'lokasi' => $request->lokasi,
        'id_pelatih' => $request->id_pelatih, // 👈 TAMBAHAN
    ]);

    $jadwal->siswa()->sync($request->id_siswa);

    return response()->json([
        'status' => true,
        'message' => 'Jadwal berhasil diupdate',
        'data' => $jadwal->load(['siswa', 'pelatih'])
    ]);
}

public function Hapus_Jadwal($id)
{
    $jadwal = \App\Models\Jadwal_Latihan::findOrFail($id);

    $jadwal->siswa()->detach();
    $jadwal->delete();

    return response()->json([
        'status' => true,
        'message' => 'Jadwal berhasil dihapus'
    ]);
}



}
