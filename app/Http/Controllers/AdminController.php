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
use App\Models\Promosi;
use App\Models\Pencapaian;
use App\Models\MasterBadge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Pendaftaran_Siswa;
use Carbon\Carbon;

class AdminController extends Controller
{
    public function Admin_Pendaftaran_siswa(Request $request)
{
    $query = Pendaftaran_Siswa::with([
        'siswa.orangtua',
        'siswa.pembayaran',
        'siswa.bukti_pembayaran',
    ]);

    $summary = [
        'menunggu' => Pendaftaran_Siswa::where('status_approval', 'Menunggu')->count(),
        'belum_diperiksa' => Pendaftaran_Siswa::where('status_approval', 'Menunggu')->count(),
        'disetujui' => Pendaftaran_Siswa::where('status_approval', 'Disetujui')->count(),
        'revisi' => Pendaftaran_Siswa::where('status_approval', 'Revisi')->count(),
        'ditolak' => Pendaftaran_Siswa::where('status_approval', 'Ditolak')->count(),
    ];

    if ($request->filled('search')) {
        $search = $request->search;

        $query->where(function ($q) use ($search) {
            $q->whereHas('siswa', function ($sub) use ($search) {
                $sub->where('nama_siswa', 'like', '%' . $search . '%')
                    ->orWhere('nama_ibu', 'like', '%' . $search . '%')
                    ->orWhere('nama_ayah', 'like', '%' . $search . '%');
            })
            ->orWhere('status_approval', 'like', '%' . $search . '%');
        });
    }

    if ($request->filled('status')) {
        $status = $request->status === 'Belum Diperiksa' ? 'Menunggu' : $request->status;
        $query->where('status_approval', $status);
    }

    $pendaftaran = $query
        ->orderBy('tanggal_daftar', 'desc')
        ->paginate($request->per_page ?? 10);

    return response()->json([
        'status' => true,
        'message' => 'Data pendaftaran siswa',
        'data' => $pendaftaran,
        'summary' => $summary,
    ]);
}


public function Admin_validasi_Pendaftaran_siswa($id)
{
    $pendaftaran = Pendaftaran_Siswa::with([
        'siswa.orangtua',
        'siswa.pembayaran',
        'siswa.bukti_pembayaran',
    ])
        ->where('id_pendaftaran', $id)
        ->orWhere('id_siswa', $id)
        ->first();

    if (!$pendaftaran) {
        return response()->json([
            'success' => false,
            'message' => 'Data tidak ditemukan'
        ], 404);
    }

    return response()->json([
        'success' => true,
        'data' => $pendaftaran,
        'status_label' => $pendaftaran->status_approval === 'Menunggu'
            ? 'Belum Diperiksa'
            : $pendaftaran->status_approval,
    ]);
}

public function lihatfilePendaftaran($jenis, $filename)
{
    $folderMap = [
        'akta' => 'akta',
        'akta_kelahiran' => 'akta',
        'kk' => 'kk',
        'kartu_keluarga' => 'kk',
        'rapor' => 'rapor',
        'foto' => 'foto',
        'pas_photo_3x4' => 'foto',
        'bukti_pembayaran' => 'bukti_pembayaran',
        'bukti' => 'bukti_pembayaran',
    ];

    if (!array_key_exists($jenis, $folderMap)) {
        return response()->json([
            'success' => false,
            'message' => 'Jenis file tidak valid'
        ], 403);
    }

    $filename = basename($filename);
    $filename = preg_replace('/(\.png)+$/i', '.png', $filename);
    $path = storage_path('app/' . $folderMap[$jenis] . '/' . $filename);

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
    $pendaftaran = Pendaftaran_Siswa::where('id_pendaftaran', $id)
        ->orWhere('id_siswa', $id)
        ->firstOrFail();

    $fieldMap = [
        'nama_siswa' => 'val_nama_siswa',
        'val_nama_siswa' => 'val_nama_siswa',
        'nama_ibu' => 'val_nama_ibu',
        'val_nama_ibu' => 'val_nama_ibu',
        'nama_ayah' => 'val_nama_ayah',
        'val_nama_ayah' => 'val_nama_ayah',
        'umur' => 'val_umur',
        'val_umur' => 'val_umur',
        'akta' => 'val_akta',
        'akta_kelahiran' => 'val_akta',
        'val_akta' => 'val_akta',
        'kk' => 'val_kk',
        'kartu_keluarga' => 'val_kk',
        'val_kk' => 'val_kk',
        'rapor' => 'val_rapor',
        'val_rapor' => 'val_rapor',
        'foto' => 'val_foto',
        'pas_photo_3x4' => 'val_foto',
        'val_foto' => 'val_foto',
        'upload_fotocopy_akta_kelahiran' => 'val_akta',
        'upload_fotocopy_kartu_keluarga' => 'val_kk',
        'upload_fotocopy_rapor' => 'val_rapor',
        'upload_fotocopy_rapor_biodata' => 'val_rapor',
        'upload_pas_foto_warna_3x4' => 'val_foto',
        'bukti' => null,
        'bukti_pembayaran' => null,
        'bukti_pembayaran_pendaftaran' => null,
        'bukti_bayar' => null,
        'upload_bukti_pembayaran' => null,
        'upload_bukti_pembayaran_pendaftaran' => null,
    ];
    $paymentProofFields = [
        'bukti',
        'bukti_pembayaran',
        'bukti_pembayaran_pendaftaran',
        'bukti_bayar',
        'upload_bukti_pembayaran',
        'upload_bukti_pembayaran_pendaftaran',
    ];

    $fields = array_values(array_unique($fieldMap));
    $fields = array_filter($fields);
    $requestedInvalidFields = collect($request->input('invalid_fields', $request->input('fields', [])))
        ->map(fn ($field) => trim(preg_replace('/_+/', '_', preg_replace('/[^a-z0-9]+/i', '_', strtolower((string) $field))), '_'))
        ->values();
    $invalidFields = $requestedInvalidFields
        ->map(fn ($field) => array_key_exists($field, $fieldMap) ? $fieldMap[$field] : null)
        ->filter()
        ->unique()
        ->values()
        ->all();
    $unsupportedInvalidFields = $requestedInvalidFields
        ->filter(fn ($field) => array_key_exists($field, $fieldMap) && is_null($fieldMap[$field]) && !in_array($field, $paymentProofFields))
        ->values()
        ->all();
    $invalidPaymentFields = $requestedInvalidFields
        ->filter(fn ($field) => in_array($field, $paymentProofFields))
        ->values()
        ->all();

    foreach ($paymentProofFields as $paymentProofField) {
        if ($request->has($paymentProofField) && $this->normalizeNilaiValidasi($request->input($paymentProofField)) === 'tidak_valid') {
            $invalidPaymentFields[] = $paymentProofField;
        }
    }

    $invalidPaymentFields = collect($invalidPaymentFields)->unique()->values()->all();

    $hasManualValidationValues = collect($fields)
        ->contains(fn ($field) => $request->has($field));

    if ($hasManualValidationValues) {
        foreach ($fields as $field) {
            $pendaftaran->$field = $this->normalizeNilaiValidasi($request->input($field, 'valid'));
        }
    } else {
        foreach ($fields as $field) {
            $pendaftaran->$field = in_array($field, $invalidFields)
                ? 'tidak_valid'
                : 'valid';
        }
    }

    $action = strtolower($request->input('action', $request->input('status', '')));
    $values = collect($fields)->map(fn ($field) => $pendaftaran->$field);

    if (in_array($action, ['ditolak', 'tolak', 'reject'])) {
        $statusApproval = 'Ditolak';
    } elseif (in_array($action, ['revisi', 'tidak_valid', 'invalid']) || !empty($unsupportedInvalidFields) || !empty($invalidPaymentFields)) {
        $statusApproval = 'Revisi';
    } elseif ($values->every(fn ($value) => $value === 'valid')) {
        $statusApproval = 'Disetujui';
    } else {
        $statusApproval = 'Revisi';
    }

    $pendaftaran->status_approval = $statusApproval;
    $pendaftaran->save();

    $paymentCreated = false;
    $paymentUpdated = false;
    $paymentData = Pembayaran::where('id_siswa', $pendaftaran->id_siswa)
        ->where('jenis', 'Pendaftaran')
        ->first();
    $paymentProofUpdated = false;

    if (!empty($invalidPaymentFields) && $paymentData) {
        $latestProof = BuktiPembayaran::where('id_pembayaran', $paymentData->id_pembayaran)
            ->orderBy('id_bukti_pembayaran', 'desc')
            ->first();

        if ($latestProof && $latestProof->status !== 'ditolak') {
            $latestProof->update(['status' => 'ditolak']);
            $paymentProofUpdated = true;
        } elseif (!$latestProof) {
            BuktiPembayaran::create([
                'id_pembayaran' => $paymentData->id_pembayaran,
                'id_siswa' => $pendaftaran->id_siswa,
                'periode' => $paymentData->periode,
                'tanggal_bukti_bayar' => null,
                'status' => 'ditolak',
                'bukti_bayar' => null,
            ]);
            $paymentProofUpdated = true;
        }

        if ($paymentData->status !== 'Belum' || !empty($paymentData->tanggal_bayar)) {
            $paymentData->update([
                'status' => 'Belum',
                'tanggal_bayar' => null,
            ]);
            $paymentUpdated = true;
        }

        Siswa::where('id_siswa', $pendaftaran->id_siswa)->update([
            'status' => 'Inactive',
        ]);
    } elseif ($statusApproval === 'Disetujui' && $paymentData) {
        if ($paymentData->status !== 'Lunas' || empty($paymentData->tanggal_bayar)) {
            $paymentData->update([
                'status' => 'Lunas',
                'tanggal_bayar' => $paymentData->tanggal_bayar ?: now()->toDateString(),
            ]);
            $paymentUpdated = true;
        }

        $paymentProofUpdated = BuktiPembayaran::where('id_pembayaran', $paymentData->id_pembayaran)
            ->where('status', '!=', 'diterima')
            ->update(['status' => 'diterima']) > 0;

        Siswa::where('id_siswa', $pendaftaran->id_siswa)->update([
            'status' => 'Active',
        ]);
    }

    return response()->json([
        'success' => true,
        'message' => $statusApproval === 'Revisi'
            ? 'Validasi berhasil disimpan, siswa perlu melakukan perbaikan'
            : 'Validasi berhasil disimpan',
        'status_approval' => $statusApproval,
        'status_label' => $statusApproval === 'Menunggu' ? 'Belum Diperiksa' : $statusApproval,
        'invalid_fields' => $invalidFields,
        'invalid_payment_fields' => $invalidPaymentFields,
        'unsupported_invalid_fields' => $unsupportedInvalidFields,
        'payment_created' => $paymentCreated,
        'payment_updated' => $paymentUpdated,
        'payment_proof_updated' => $paymentProofUpdated,
        'data_pembayaran' => $paymentData,
        'data' => $pendaftaran->fresh(['siswa.orangtua', 'siswa.pembayaran', 'siswa.bukti_pembayaran']),
    ]);
}

private function normalizeNilaiValidasi($value): string
{
    $value = strtolower(trim((string) $value));

    return in_array($value, ['valid', 'true', '1', 'ya', 'yes'])
        ? 'valid'
        : 'tidak_valid';
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
                'status' => 'Lunas',
                'tanggal_bayar' => $pembayaran->tanggal_bayar ?: now()->toDateString(),
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

    // âœ… filter dari tanggal_penilaian
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

        // ðŸ”¢ jumlah
        'hadir' => $hadir,
        'sakit' => $sakit,
        'izin'  => $izin,
        'total' => $total,

        // ðŸ“Š persen
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
        'id_pelatih' => $request->id_pelatih, // ðŸ‘ˆ TAMBAHAN
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
        'id_pelatih' => $request->id_pelatih, // ðŸ‘ˆ TAMBAHAN
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

public function MediaPromosiAdmin(Request $request)
{
    $query = Promosi::with(['siswa:id_siswa,nama_siswa,umur,status', 'dibuatOleh:id_admin,nama_admin']);

    if ($request->filled('search')) {
        $search = $request->search;

        $query->where(function ($q) use ($search) {
            $q->where('judul', 'like', '%' . $search . '%')
              ->orWhere('isi_promosi', 'like', '%' . $search . '%')
              ->orWhereHas('siswa', function ($siswaQuery) use ($search) {
                  $siswaQuery->where('nama_siswa', 'like', '%' . $search . '%');
              });
        });
    }

    if ($request->filled('id_siswa')) {
        $query->where('id_siswa', $request->id_siswa);
    }

    if ($request->filled('kategori_umur')) {
        $umur = $this->extractUmurFromKategori($request->kategori_umur);

        if (!is_null($umur)) {
            $query->whereHas('siswa', function ($siswaQuery) use ($umur) {
                $siswaQuery->where('umur', $umur);
            });
        }
    }

    if ($request->filled('tanggal_publish')) {
        $query->whereDate('tanggal_promosi', $request->tanggal_publish);
    }

    $promosi = $query->orderBy('tanggal_promosi', 'desc')
        ->orderBy('id_promosi', 'desc')
        ->paginate(10)
        ->through(function ($item) {
            return $this->formatMediaPromosi($item);
        })
        ->withQueryString();

    $kategoriUmur = Siswa::select('umur')
        ->distinct()
        ->orderBy('umur')
        ->pluck('umur')
        ->map(fn ($umur) => 'U-' . $umur)
        ->values();

    $siswaQuery = Siswa::select('id_siswa', 'nama_siswa', 'umur', 'status')
        ->orderBy('nama_siswa');

    if ($request->filled('kategori_umur')) {
        $umur = $this->extractUmurFromKategori($request->kategori_umur);

        if (!is_null($umur)) {
            $siswaQuery->where('umur', $umur);
        }
    }

    $siswaOptions = $siswaQuery->get()->map(function ($siswa) {
        return [
            'id_siswa' => $siswa->id_siswa,
            'nama_siswa' => $siswa->nama_siswa,
            'kategori_umur' => 'U-' . $siswa->umur,
            'status' => $siswa->status,
        ];
    });

    return response()->json([
        'success' => true,
        'message' => 'Data media promosi berhasil diambil',
        'data' => $promosi,
        'filters' => [
            'search' => $request->search,
            'id_siswa' => $request->id_siswa,
            'kategori_umur' => $request->kategori_umur,
            'tanggal_publish' => $request->tanggal_publish,
        ],
        'options' => [
            'kategori_umur' => $kategoriUmur,
            'siswa' => $siswaOptions,
        ],
    ]);
}

public function DetailMediaPromosi($id)
{
    $promosi = Promosi::with(['siswa:id_siswa,nama_siswa,umur,status', 'dibuatOleh:id_admin,nama_admin'])
        ->findOrFail($id);

    return response()->json([
        'success' => true,
        'message' => 'Detail media promosi berhasil diambil',
        'data' => $this->formatMediaPromosi($promosi),
    ]);
}

public function TambahMediaPromosi(Request $request)
{
    $validated = $request->validate([
        'target_mode' => 'required|in:semua,kategori,siswa',
        'judul' => 'required|string|max:100',
        'isi_promosi' => 'required|string|max:500',
        'tanggal_promosi' => 'required|date',
        'kategori_umur' => 'nullable|array|min:1',
        'kategori_umur.*' => 'required|string',
        'id_siswa' => 'nullable|array|min:1',
        'id_siswa.*' => 'required|exists:siswa,id_siswa',
        'foto_promosi' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    ]);

    if ($validated['target_mode'] === 'kategori' && !$request->filled('kategori_umur')) {
        return response()->json([
            'success' => false,
            'message' => 'kategori_umur wajib diisi jika target_mode adalah kategori',
        ], 422);
    }

    if ($validated['target_mode'] === 'siswa' && empty($validated['id_siswa'])) {
        return response()->json([
            'success' => false,
            'message' => 'id_siswa wajib diisi jika target_mode adalah siswa',
        ], 422);
    }

    $siswaQuery = Siswa::query();

    if ($validated['target_mode'] === 'kategori') {
        $umurList = collect($validated['kategori_umur'])
            ->map(fn ($kategori) => $this->extractUmurFromKategori($kategori))
            ->filter(fn ($umur) => !is_null($umur))
            ->unique()
            ->values();

        if ($umurList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'kategori_umur tidak valid',
            ], 422);
        }

        $siswaQuery->whereIn('umur', $umurList->all());
    }

    if ($validated['target_mode'] === 'siswa') {
        $siswaQuery->whereIn('id_siswa', $validated['id_siswa']);
    }

    $siswaTargets = $siswaQuery
        ->select('id_siswa', 'nama_siswa', 'umur', 'status')
        ->orderBy('nama_siswa')
        ->get();

    if ($siswaTargets->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'Tidak ada siswa yang sesuai dengan target promosi',
        ], 404);
    }

    $admin = Admin::where('user_id', auth()->id())->first();

    if (!$admin) {
        return response()->json([
            'success' => false,
            'message' => 'Data admin tidak ditemukan'
        ], 404);
    }

    $fotoPath = null;
    if ($request->hasFile('foto_promosi')) {
        $fotoPath = $request->file('foto_promosi')->store('promosi');
    }

    $groupId = (string) Str::uuid();

    $promosi = $siswaTargets->map(function ($siswa) use ($validated, $fotoPath, $admin, $groupId) {
        return Promosi::create([
            'group_id' => $groupId,
            'judul' => $validated['judul'],
            'isi_promosi' => $validated['isi_promosi'],
            'id_siswa' => $siswa->id_siswa,
            'tanggal_promosi' => $validated['tanggal_promosi'],
            'dibuat_oleh' => $admin->id_admin,
            'foto_promosi' => $fotoPath,
            'kategori' => 'Berita',
        ])->load(['siswa:id_siswa,nama_siswa,umur,status', 'dibuatOleh:id_admin,nama_admin']);
    })->values();

    return response()->json([
        'success' => true,
        'message' => 'Media promosi berhasil ditambahkan',
        'target_mode' => $validated['target_mode'],
        'kategori_umur' => $validated['target_mode'] === 'kategori' ? array_values($validated['kategori_umur']) : null,
        'total_data' => $promosi->count(),
        'data' => $promosi->map(fn ($item) => $this->formatMediaPromosi($item))->values(),
    ], 201);
}

public function UpdateMediaPromosi(Request $request, $id)
{
    $promosiAwal = Promosi::findOrFail($id);

    $validated = $request->validate([
        'target_mode' => 'required|in:semua,kategori,siswa',
        'judul' => 'required|string|max:100',
        'isi_promosi' => 'required|string|max:500',
        'tanggal_promosi' => 'required|date',
        'kategori_umur' => 'nullable|array|min:1',
        'kategori_umur.*' => 'required|string',
        'id_siswa' => 'nullable|array|min:1',
        'id_siswa.*' => 'required|exists:siswa,id_siswa',
        'foto_promosi' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    ]);

    if ($validated['target_mode'] === 'kategori' && !$request->filled('kategori_umur')) {
        return response()->json([
            'success' => false,
            'message' => 'kategori_umur wajib diisi jika target_mode adalah kategori',
        ], 422);
    }

    if ($validated['target_mode'] === 'siswa' && empty($validated['id_siswa'])) {
        return response()->json([
            'success' => false,
            'message' => 'id_siswa wajib diisi jika target_mode adalah siswa',
        ], 422);
    }

    $siswaQuery = Siswa::query();

    if ($validated['target_mode'] === 'kategori') {
        $umurList = collect($validated['kategori_umur'])
            ->map(fn ($kategori) => $this->extractUmurFromKategori($kategori))
            ->filter(fn ($umur) => !is_null($umur))
            ->unique()
            ->values();

        if ($umurList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'kategori_umur tidak valid',
            ], 422);
        }

        $siswaQuery->whereIn('umur', $umurList->all());
    }

    if ($validated['target_mode'] === 'siswa') {
        $siswaQuery->whereIn('id_siswa', $validated['id_siswa']);
    }

    $siswaTargets = $siswaQuery
        ->select('id_siswa', 'nama_siswa', 'umur', 'status')
        ->orderBy('nama_siswa')
        ->get();

    if ($siswaTargets->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'Tidak ada siswa yang sesuai dengan target promosi',
        ], 404);
    }

    DB::beginTransaction();

    try {
        $fotoLama = $promosiAwal->foto_promosi;
        $fotoPath = $fotoLama;

        if ($request->hasFile('foto_promosi')) {
            $fotoPath = $request->file('foto_promosi')->store('promosi');
        }

        // Prioritaskan group_id untuk identifikasi batch.
        $batchLama = Promosi::query();
        if (!empty($promosiAwal->group_id)) {
            $batchLama->where('group_id', $promosiAwal->group_id);
        } else {
            // Fallback untuk data lama yang belum punya group_id.
            $batchLama
                ->where('dibuat_oleh', $promosiAwal->dibuat_oleh)
                ->whereDate('tanggal_promosi', $promosiAwal->tanggal_promosi)
                ->where('judul', $promosiAwal->judul)
                ->where('isi_promosi', $promosiAwal->isi_promosi)
                ->where(function ($query) use ($promosiAwal) {
                    if (is_null($promosiAwal->foto_promosi)) {
                        $query->whereNull('foto_promosi');
                    } else {
                        $query->where('foto_promosi', $promosiAwal->foto_promosi);
                    }
                });
        }

        $batchLamaIds = $batchLama->pluck('id_promosi');

        $groupIdBaru = (string) Str::uuid();

        if ($batchLamaIds->isNotEmpty()) {
            Promosi::whereIn('id_promosi', $batchLamaIds)->delete();
        }

        $promosiBaru = $siswaTargets->map(function ($siswa) use ($validated, $fotoPath, $promosiAwal, $groupIdBaru) {
            return Promosi::create([
                'group_id' => $groupIdBaru,
                'judul' => $validated['judul'],
                'isi_promosi' => $validated['isi_promosi'],
                'id_siswa' => $siswa->id_siswa,
                'tanggal_promosi' => $validated['tanggal_promosi'],
                'dibuat_oleh' => $promosiAwal->dibuat_oleh,
                'foto_promosi' => $fotoPath,
                'kategori' => 'Berita',
            ])->load(['siswa:id_siswa,nama_siswa,umur,status', 'dibuatOleh:id_admin,nama_admin']);
        })->values();

        if ($request->hasFile('foto_promosi') && $fotoLama && Storage::exists($fotoLama)) {
            Storage::delete($fotoLama);
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Media promosi berhasil diupdate',
            'target_mode' => $validated['target_mode'],
            'kategori_umur' => $validated['target_mode'] === 'kategori' ? array_values($validated['kategori_umur']) : null,
            'total_data' => $promosiBaru->count(),
            'data' => $promosiBaru->map(fn ($item) => $this->formatMediaPromosi($item))->values(),
        ]);
    } catch (\Throwable $e) {
        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => 'Gagal update media promosi',
            'error' => $e->getMessage(),
        ], 500);
    }
}

public function HapusMediaPromosi($id)
{
    $promosi = Promosi::findOrFail($id);

    if ($promosi->foto_promosi && Storage::exists($promosi->foto_promosi)) {
        Storage::delete($promosi->foto_promosi);
    }

    $promosi->delete();

    return response()->json([
        'success' => true,
        'message' => 'Media promosi berhasil dihapus',
    ]);
}

public function UpdateMediaPromosiByGroup(Request $request, $groupId)
{
    $promosi = Promosi::where('group_id', $groupId)->orderBy('id_promosi')->firstOrFail();
    return $this->UpdateMediaPromosi($request, $promosi->id_promosi);
}

public function HapusMediaPromosiByGroup($groupId)
{
    $promosiGroup = Promosi::where('group_id', $groupId)->get();

    if ($promosiGroup->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'Group media promosi tidak ditemukan',
        ], 404);
    }

    $fotoPath = $promosiGroup->first()->foto_promosi;

    Promosi::where('group_id', $groupId)->delete();

    if ($fotoPath && Storage::exists($fotoPath)) {
        Storage::delete($fotoPath);
    }

    return response()->json([
        'success' => true,
        'message' => 'Group media promosi berhasil dihapus',
    ]);
}

private function formatMediaPromosi(Promosi $promosi): array
{
    return [
        'id_promosi' => $promosi->id_promosi,
        'group_id' => $promosi->group_id,
        'judul' => $promosi->judul,
        'isi_promosi' => $promosi->isi_promosi,
        'tanggal_promosi' => $promosi->tanggal_promosi,
        'kategori' => $promosi->kategori,
        'foto_promosi' => $promosi->foto_promosi,
        'id_siswa' => $promosi->id_siswa,
        'nama_siswa' => $promosi->siswa->nama_siswa ?? null,
        'kategori_umur' => $promosi->siswa ? 'U-' . $promosi->siswa->umur : null,
        'status_siswa' => $promosi->siswa->status ?? null,
        'dibuat_oleh' => $promosi->dibuat_oleh,
        'nama_admin' => $promosi->dibuatOleh->nama_admin ?? null,
    ];
}

public function FormPrestasiAdmin(Request $request)
{
    $request->validate([
        'kategori_umur' => 'nullable|string',
        'search' => 'nullable|string|max:100',
        'status' => 'nullable|in:Active,Inactive',
        'per_page' => 'nullable|integer|min:1|max:100',
    ]);

    $kategoriUmur = Siswa::select('umur')
        ->distinct()
        ->orderBy('umur')
        ->pluck('umur')
        ->map(fn ($umur) => 'U-' . $umur)
        ->values();

    $siswaQuery = Siswa::select('id_siswa', 'nama_siswa', 'umur', 'status')
        ->orderBy('nama_siswa');

    if ($request->filled('kategori_umur')) {
        $umur = $this->extractUmurFromKategori($request->kategori_umur);

        if (is_null($umur)) {
            return response()->json([
                'success' => false,
                'message' => 'Format kategori_umur tidak valid. Gunakan format seperti U-10.',
            ], 422);
        }

        $siswaQuery->where('umur', $umur);
    }

    if ($request->filled('status')) {
        $siswaQuery->where('status', $request->status);
    }

    if ($request->filled('search')) {
        $siswaQuery->where('nama_siswa', 'like', '%' . $request->search . '%');
    }

    $perPage = (int) ($request->per_page ?? 10);
    $siswa = $siswaQuery->paginate($perPage)->through(function ($item) {
        return [
            'id_siswa' => $item->id_siswa,
            'nama_siswa' => $item->nama_siswa,
            'kategori_umur' => 'U-' . $item->umur,
            'status' => $item->status,
        ];
    })->withQueryString();

    return response()->json([
        'success' => true,
        'message' => 'Data form prestasi berhasil diambil',
        'filters' => [
            'kategori_umur' => $request->kategori_umur,
            'search' => $request->search,
            'status' => $request->status,
        ],
        'data' => [
            'kategori_umur' => $kategoriUmur,
            'siswa' => $siswa,
            'total_siswa' => $siswa->total(),
        ],
    ]);
}

public function StorePrestasiAdmin(Request $request)
{
    $validated = $request->validate([
        'id_siswa' => 'required|array',
        'id_siswa.*' => 'exists:siswa,id_siswa',
        'nama_prestasi' => 'required|string|max:255',
        'tanggal_diberikan' => 'nullable|date',
    ]);

    DB::beginTransaction();

    try {
        $prestasiIds = [];

        foreach ($validated['id_siswa'] as $id) {
            $prestasi = Pencapaian::create([
                'id_siswa' => $id,
                'id_badge' => null, // ðŸ”¥ nonaktifkan badge
                'nama_prestasi' => $validated['nama_prestasi'],
                'tanggal_diberikan' => $validated['tanggal_diberikan'] ?? now()->toDateString(),
            ]);

            $prestasiIds[] = $prestasi->id_pencapaian;
        }

        DB::commit();

        $data = Pencapaian::with([
            'siswa:id_siswa,nama_siswa,umur',
        ])->whereIn('id_pencapaian', $prestasiIds)->get();

        return response()->json([
            'success' => true,
            'message' => 'Prestasi berhasil disimpan (tanpa badge)',
            'data' => $data,
        ], 201);

    } catch (\Throwable $e) {
        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => 'Gagal menyimpan prestasi',
            'error' => $e->getMessage(),
        ], 500);
    }
}

public function HistoryPrestasiAdmin(Request $request)
{
    $query = Pencapaian::with([
        'siswa:id_siswa,nama_siswa,umur',
    ]);

    // ðŸ” Filter kategori umur
    if ($request->filled('kategori_umur')) {
        $umur = $this->extractUmurFromKategori($request->kategori_umur);

        if (!is_null($umur)) {
            $query->whereHas('siswa', function ($siswaQuery) use ($umur) {
                $siswaQuery->where('umur', $umur);
            });
        }
    }

    // ðŸ” Search (tanpa badge)
    if ($request->filled('search')) {
        $search = $request->search;

        $query->where(function ($q) use ($search) {
            $q->whereHas('siswa', function ($siswaQuery) use ($search) {
                $siswaQuery->where('nama_siswa', 'like', '%' . $search . '%');
            })
            ->orWhere('nama_prestasi', 'like', '%' . $search . '%'); // ðŸ”¥ langsung ke field
        });
    }

    $prestasi = $query->orderBy('tanggal_diberikan', 'desc')
        ->orderBy('id_pencapaian', 'desc')
        ->paginate(10)
        ->through(function ($item, $index) use ($request) {
            $page = (int) ($request->get('page', 1));
            $perPage = 10;
            $nomor = (($page - 1) * $perPage) + $index + 1;

            return [
                'no' => $nomor,
                'id_pencapaian' => $item->id_pencapaian,
                'id_siswa' => $item->id_siswa,
                'nama_siswa' => $item->siswa->nama_siswa ?? null,
                'kategori_umur' => isset($item->siswa->umur) ? 'U-' . $item->siswa->umur : null,
                'nama_prestasi' => $item->nama_prestasi, // ðŸ”¥ FIX
                'tanggal_diberikan' => $item->tanggal_diberikan,
            ];
        });

    // ðŸ”½ Filter dropdown kategori umur
    $kategoriUmur = Siswa::select('umur')
        ->distinct()
        ->orderBy('umur')
        ->pluck('umur')
        ->map(fn ($umur) => 'U-' . $umur)
        ->values();

    return response()->json([
        'success' => true,
        'message' => 'History prestasi berhasil diambil',
        'filters' => [
            'kategori_umur' => $request->kategori_umur,
            'search' => $request->search,
        ],
        'options' => [
            'kategori_umur' => $kategoriUmur,
        ],
        'data' => $prestasi,
    ]);
}

private function extractUmurFromKategori(?string $kategoriUmur): ?int
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
    $admin = Admin::where('user_id', auth()->id())->first();

    if (!$admin) {
        return response()->json([
            'success' => false,
            'message' => 'Data admin tidak ditemukan'
        ], 404);
    }

