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
use App\Models\BuktiPembayaran;
use App\Models\Jadwal_Latihan;
use App\Models\Promosi;
use App\Models\Pencapaian;
use App\Models\Presensi;
use App\Models\Catatan_Pelatih;
use App\Models\Performa_Siswa;
use App\Models\MasterBadge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Pendaftaran_Siswa;
use App\Services\SiswaPaymentStatusService;
use Carbon\Carbon;



class DashboardController extends Controller
{
   
public function siswaDashboard(Request $request)
{
    $user = Auth::user();

    // 🔐 VALIDASI ROLE
    if ($user->role !== 'orang_tua') {
        return response()->json([
            'status' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    // 🔎 AMBIL SISWA BERDASARKAN ANAK YANG DIPILIH DI setAnak
    $selectedChildId = $request->route('id_siswa') ?? $request->id_siswa ?? session('id_siswa');
    $orangTua = OrangTua::where('user_id', $user->id)->first();

    if (!$orangTua) {
        return response()->json([
            'status' => false,
            'message' => 'Data orang tua tidak ditemukan'
        ], 404);
    }

    $siswaQuery = Siswa::where('id_ortu', $orangTua->id_ortu);

    if ($selectedChildId) {
        $siswaQuery->where('id_siswa', $selectedChildId);
    }

    $siswa = $siswaQuery->first();

    if (!$siswa) {
        return response()->json([
            'status' => false,
            'message' => $selectedChildId
                ? 'Anak yang dipilih tidak ditemukan atau tidak terkait dengan akun orang tua ini'
                : 'Silakan pilih anak terlebih dahulu'
        ], 404);
    }

    session(['id_siswa' => $siswa->id_siswa]);

    $paymentStatusService = app(SiswaPaymentStatusService::class);
    $siswa = $paymentStatusService->syncMonthlyStatus($siswa->loadMissing('pendaftaran'));

    // 💰 PEMBAYARAN
    $pembayaranBelum = $siswa->pembayaran()
        ->whereIn('status', ['Belum', 'Lunas'])
        ->whereIn('jenis', ['Pendaftaran', 'Bulanan', 'Harian'])
        ->get();

    $periodeBulanIni = now()->format('Y-m');
    $periodeBulanSebelumnya = now()->subMonthNoOverflow()->format('Y-m');

    $pembayaranBulanan = $paymentStatusService->monthlySummary($siswa, $periodeBulanIni);
    $pembayaranBulanSebelumnya = $paymentStatusService->monthlySummary($siswa, $periodeBulanSebelumnya);

    // 📊 KEHADIRAN (TAHUN INI)
    $presensi = $siswa->presensi()
        ->whereYear('created_at', now()->year)
        ->get();

    $total = $presensi->count();

    $hadir = $presensi->where('status_kehadiran', 'Hadir')->count();
    $sakit = $presensi->where('status_kehadiran', 'Sakit')->count();
    $izin  = $presensi->where('status_kehadiran', 'Izin')->count();

    $kehadiran = [
        'hadir' => $total ? round(($hadir / $total) * 100) : 0,
        'sakit' => $total ? round(($sakit / $total) * 100) : 0,
        'izin'  => $total ? round(($izin / $total) * 100) : 0,
    ];

    // 📅 JADWAL LATIHAN
    $jadwal = $siswa->jadwal()->get();

    // 📈 PERFORMA (12 BULAN)
    \Carbon\Carbon::setLocale('id'); // 🔥 BAHASA INDONESIA

    $performaRaw = $siswa->performa()
        ->whereYear('tanggal_penilaian', now()->year)
        ->get()
        ->groupBy(function ($item) {
            return \Carbon\Carbon::parse($item->tanggal_penilaian)->format('m');
        });

    $performa = [];

    for ($i = 1; $i <= 12; $i++) {
        $bulanKey = str_pad($i, 2, '0', STR_PAD_LEFT);
        $namaBulan = \Carbon\Carbon::create()->month($i)->translatedFormat('F');

        if (isset($performaRaw[$bulanKey])) {
            $data = $performaRaw[$bulanKey];

            $performa[] = [
                'bulan' => $namaBulan,
                'dribbling' => round($data->avg('dribbling')),
                'passing'   => round($data->avg('passing')),
                'shooting'  => round($data->avg('shooting')),
            ];
        } else {
            $performa[] = [
                'bulan' => $namaBulan,
                'dribbling' => 0,
                'passing'   => 0,
                'shooting'  => 0,
            ];
        }
    }

    // 🏆 PRESTASI
    $prestasi = $siswa->pencapaian;

    // 📝 CATATAN PELATIH
    $catatan = $siswa->catatan()->latest()->get();

    // 🚀 RESPONSE FINAL
    return response()->json([
        'status' => true,
        'message' => 'Dashboard siswa',
        'data' => [
            'nama_siswa' => $siswa->nama_siswa,
            'id_siswa' => $siswa->id_siswa,
            'selected_child_id' => $siswa->id_siswa,
            'userName' => $user->name,
            'status_siswa' => $siswa->status,

            'pembayaranBelum' => $pembayaranBelum,
            'pembayaran_bulanan' => $pembayaranBulanan,
            'pembayaran_bulan_sebelumnya' => $pembayaranBulanSebelumnya,
            'kehadiran' => $kehadiran,
            'jadwal' => $jadwal,
            'performa' => $performa,
            'prestasi' => $prestasi,
            'catatan' => $catatan,
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

    // 🔥 CARD
    $totalSiswa = Siswa::count();
    $totalPelatih = Pelatih::count();

    $kategoriUmur = Siswa::selectRaw('umur, COUNT(*) as total')
        ->groupBy('umur')
        ->orderBy('umur')
        ->get()
        ->mapWithKeys(function ($item) {
            return [
                'U-' . $item->umur => (int) $item->total
            ];
        });

    // 🔥 PRESTASI
    $prestasiRaw = Pencapaian::selectRaw('MONTH(tanggal_diberikan) as bulan, COUNT(*) as total')
        ->groupBy('bulan')
        ->pluck('total', 'bulan');

    $bulanList = [
        1 => 'januari', 2 => 'februari', 3 => 'maret',
        4 => 'april', 5 => 'mei', 6 => 'juni',
        7 => 'juli', 8 => 'agustus', 9 => 'september',
        10 => 'oktober', 11 => 'november', 12 => 'desember'
    ];

    $prestasi = [];
    foreach ($bulanList as $key => $nama) {
        $prestasi[$nama] = (int) ($prestasiRaw[$key] ?? 0);
    }

    // 🔥 KEHADIRAN
    $kehadiranRaw = Presensi::selectRaw('status_kehadiran, COUNT(*) as total')
        ->groupBy('status_kehadiran')
        ->pluck('total', 'status_kehadiran');

    $kehadiran = [
        'hadir' => (int) ($kehadiranRaw['Hadir'] ?? 0),
        'sakit' => (int) ($kehadiranRaw['Sakit'] ?? 0),
        'izin'  => (int) ($kehadiranRaw['Izin'] ?? 0),
    ];

    //  HISTORY PEMBAYARAN
    $history = Pembayaran::with('siswa:id_siswa,nama_siswa')
        ->orderBy('tanggal_bayar', 'desc')
        ->limit(5)
        ->get()
        ->map(function ($item) {
            return [
                'nama' => $item->siswa->nama_siswa ?? '-',
                'jenis' => $item->jenis,
                'tanggal' => $item->tanggal_bayar,
                'nominal' => (int) $item->jumlah,
                'status' => $item->status
            ];
        });

    //  VALIDASI PEMBAYARAN
    $validasiPembayaran = BuktiPembayaran::with('siswa:id_siswa,nama_siswa')
        ->where('status', 'Menunggu validasi')
        ->get()
        ->map(function ($item) {
            return [
                'nama' => $item->siswa->nama_siswa ?? '-',
                'tanggal' => $item->tanggal_bukti_bayar,
                'status' => $item->status
            ];
        });

    // 🔥 PELATIH
    $listPelatih = Pelatih::pluck('nama_pelatih');

    // 🔥 VALIDASI BERKAS
$validasiBerkas = Pendaftaran_Siswa::with([
    'siswa.user:id,email',
    'siswa.orangtua:id_ortu,no_hp' // ✅ FIX DI SINI
])
->get()
->map(function ($item) {
    return [
        'nama' => optional($item->siswa)->nama_siswa ?? '-',
        'email' => optional($item->siswa->user)->email ?? '-',
        'no_hp' => optional($item->siswa->orangtua)->no_hp ?? '-',
        'status' => $item->status_approval
    ];
});

    return response()->json([
        'status' => true,
        'message' => 'Dashboard admin',
        'data' => [
            'card' => [
                'total_siswa' => $totalSiswa,
                'kategori_umur' => $kategoriUmur, // ✅ INI YANG BENAR
                'pelatih' => $totalPelatih
            ],
            'prestasi' => $prestasi,
            'kehadiran' => $kehadiran,
            'history_pembayaran' => $history,
            'validasi_pembayaran' => $validasiPembayaran,
            'pelatih' => $listPelatih,
            'validasi_berkas' => $validasiBerkas
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

    $user = auth()->user();

    // 🔥 PROFIL
    $pelatih = Pelatih::where('user_id', $user->id)->first();

    // 🔥 KEHADIRAN MINGGU INI
    $kehadiranRaw = Presensi::whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ])
        ->selectRaw('status_kehadiran, COUNT(*) as total')
        ->groupBy('status_kehadiran')
        ->pluck('total', 'status_kehadiran');

    $kehadiran = [
        'hadir' => (int) ($kehadiranRaw['Hadir'] ?? 0),
        'sakit' => (int) ($kehadiranRaw['Sakit'] ?? 0),
        'izin'  => (int) ($kehadiranRaw['Izin'] ?? 0),
        'total' => (int) $kehadiranRaw->sum()
    ];

    // 🔥 RATA-RATA PERFORMA BULAN INI (FIX: tambah year)
    $avg = Performa_Siswa::whereMonth('tanggal_penilaian', now()->month)
        ->whereYear('tanggal_penilaian', now()->year)
        ->selectRaw('
            AVG(dribbling) as dribbling,
            AVG(passing) as passing,
            AVG(shooting) as shooting
        ')
        ->first();

    $rataPerforma = [
        'dribbling' => (int) ($avg->dribbling ?? 0),
        'passing'   => (int) ($avg->passing ?? 0),
        'shooting'  => (int) ($avg->shooting ?? 0),
    ];

    // 🔥 PERFORMA 12 BULAN (UNTUK CHART)
    $performaBulananRaw = Performa_Siswa::whereYear('tanggal_penilaian', now()->year)
        ->selectRaw('
            MONTH(tanggal_penilaian) as bulan,
            AVG(dribbling) as dribbling,
            AVG(passing) as passing,
            AVG(shooting) as shooting
        ')
        ->groupBy('bulan')
        ->orderBy('bulan')
        ->get();

    $performa12Bulan = collect(range(1,12))->map(function ($bulan) use ($performaBulananRaw) {
        $data = $performaBulananRaw->firstWhere('bulan', $bulan);

        return [
            'bulan' => $bulan,
            'dribbling' => (int) ($data->dribbling ?? 0),
            'passing'   => (int) ($data->passing ?? 0),
            'shooting'  => (int) ($data->shooting ?? 0),
        ];
    });

    // 🔥 CATATAN PELATIH
    $catatan = Catatan_Pelatih::with('siswa:id_siswa,nama_siswa,umur')
        ->latest()
        ->limit(5)
        ->get()
        ->map(function ($item) {
            return [
                'nama' => $item->siswa->nama_siswa ?? '-',
                'kategori' => 'U-' . ($item->siswa->umur ?? '-'),
                'catatan' => $item->catatan
            ];
        });

    // 🔥 JADWAL LATIHAN
    $jadwal = Jadwal_Latihan::orderBy('tanggal', 'desc')
        ->limit(5)
        ->get()
        ->map(function ($item) {
            return [
                'tanggal' => Carbon::parse($item->tanggal)->translatedFormat('l, d-m-Y'),
                'jam' => $item->jam_mulai . ' - ' . $item->jam_selesai,
                'lokasi' => $item->lokasi
            ];
        });

    // 🔥 PERFORMA TERAKHIR
    $performaTerakhir = Performa_Siswa::with('siswa:id_siswa,nama_siswa,umur')
        ->orderBy('tanggal_penilaian', 'desc')
        ->limit(5)
        ->get()
        ->map(function ($item) {
            return [
                'nama' => $item->siswa->nama_siswa ?? '-',
                'kategori' => 'U-' . ($item->siswa->umur ?? '-'),
                'tanggal' => Carbon::parse($item->tanggal_penilaian)->format('d-m-Y'),
                'dribbling' => (int) $item->dribbling,
                'passing'   => (int) $item->passing,
                'shooting'  => (int) $item->shooting,
            ];
        });

    return response()->json([
        'status' => true,
        'message' => 'Dashboard pelatih',
        'data' => [
            'profil' => [
                'nama' => $pelatih->nama_pelatih ?? '-',
                'role' => 'Coach'
            ],
            'kehadiran_mingguan' => $kehadiran,
            'performa_bulan_ini' => $rataPerforma,
            'performa_12_bulan' => $performa12Bulan,
            'catatan_terbaru' => $catatan,
            'jadwal_latihan' => $jadwal,
            'performa_terakhir' => $performaTerakhir
        ]
    ]);
}

}
