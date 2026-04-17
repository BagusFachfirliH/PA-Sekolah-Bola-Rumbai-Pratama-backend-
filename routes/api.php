<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SiswaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\PelatihController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// ================= AUTH =================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// ================= PROTECTED ROUTES =================
Route::middleware('auth:sanctum')->group(function () { 

    Route::get('/user', function (Request $request) {  //belum masuk
        return $request->user();
    });

    Route::get('/landing', [AuthController::class, 'landingPage']);  //belum masuk

    Route::get('/registrasi-siswa', [SiswaController::class, 'registrasi_siswa']);
    Route::post('/daftar-siswa', [SiswaController::class, 'daftar_siswa']); 

    Route::get('/anak', [SiswaController::class, 'getanak']);
    Route::post('/anak/pilih', [SiswaController::class, 'setAnak']);


     //  DASHBOARD
    Route::get('/siswa/dashboard', [DashboardController::class, 'siswaDashboard']);
    Route::get('/admin/dashboard', [DashboardController::class, 'adminDashboard']);
    Route::get('/pelatih/dashboard', [DashboardController::class, 'pelatihDashboard']);


    // Admin Pendaftaran-Siswa
    Route::get('/admin/pendaftaran-siswa', [AdminController::class, 'Admin_Pendaftaran_siswa']);
    Route::get('/admin/pendaftaran/{id}', [AdminController::class, 'Admin_validasi_Pendaftaran_siswa']);
    Route::post('/admin/pendaftaran/{id}/validasi', [AdminController::class, 'submitValidasi']);
    Route::get('/file-pendaftaran-siswa/{folder}/{filename}', [AdminController::class, 'lihatfilePendaftaran']);

    //SIswa Pendaftaran revisi
    Route::get('/revisi-pendaftaran/{id_siswa}', [SiswaController::class, 'revisi_pendaftaran']);
    Route::post('/update-pendaftaran/{id_siswa}', [SiswaController::class, 'update_pendaftaran']);
    

    // Notfikasi
     Route::get('/notifikasi', [NotifikasiController::class, 'getNotifikasi']);
     Route::post('/notifikasi/kirim', [NotifikasiController::class, 'kirimNotif']);
    Route::post('/notifikasi/baca/{id}', [NotifikasiController::class, 'tandaiBaca']);


    //Pembayaran siswa (Admin)
    Route::get('/pembayaran-admin', [AdminController::class, 'pembayaran_admin']);
    Route::get('/bukti-pembayaran-admin/{id_siswa}', [AdminController::class, 'buktipembayaran_admin']);
    Route::get('/lihat-bukti/{folder}/{file}', [AdminController::class, 'lihatBukti_pembayaran_admin']);
    Route::post('/bukti/approve/{id}', [AdminController::class, 'Bukti_Diterima']);
    Route::post('/bukti/reject/{id}', [AdminController::class, 'Bukti_Ditolak']);
    Route::get('/history-pembayaran', [AdminController::class, 'history_pembayaran']);


    //Pembayaran siswa (Siswa)
    Route::get('/upload-bukti/{id_pembayaran}/{id_siswa}', [SiswaController::class, 'Upload_Bukti_Pembayaran']);
    Route::post('/upload-bukti/{id_pembayaran}', [SiswaController::class, 'Store_Bukti_Pembayaran']);

    //Data Siswa (Admin)
    Route::get('/data-siswa', [AdminController::class, 'Data_Siswa']);
    Route::get('/performa-siswa/{id_siswa}', [AdminController::class, 'performaperSiswa']);
    Route::get('/kehadiran-siswa/{id_siswa}', [AdminController::class, 'Rekap_Absensi_PerSiswa']);
    

    //Data Pelatih (Admin)
    Route::get('/data-pelatih', [AdminController::class, 'Data_Pelatih']);
    Route::post('/tambah-pelatih', [AdminController::class, 'Tambah_Pelatih']);
    Route::put('/edit-pelatih/{id}', [AdminController::class, 'Update_Pelatih']);
    Route::delete('/hapus-pelatih/{id}', [AdminController::class, 'Hapus_Pelatih']);

    // Jadwal Latihan (Admin)
    Route::get('/jadwal-latihan', [AdminController::class, 'Jadwallatihan_Siswa']);
    Route::get('/jadwal-latihan/{id}', [AdminController::class, 'JadwalperPelatih']);
    Route::post('/tambah-jadwal', [AdminController::class, 'Tambah_Jadwal']);
    Route::put('/jadwal-latihan/{id}', [AdminController::class, 'Update_Jadwal']);
    Route::delete('/jadwal-latihan/{id}', [AdminController::class, 'Hapus_Jadwal']);


    //Presensi Pelatih
    Route::get('/presensi', [PelatihController::class, 'kehadiran']);
    Route::post('/presensi/input', [PelatihController::class, 'Input_Presensi']);
    Route::get('/presensi/rekap', [PelatihController::class, 'Rekap_Absensi']);
    Route::delete('/presensi/{id_jadwal}', [AdminController::class, 'hapus_presensi']);


    //Performa Siswa Pelatih
    Route::get('/performa-siswa/{id}', [PelatihController::class, 'Performa_Siswa']);
    Route::post('/performa-siswa/input/{id}', [PelatihController::class, 'Input_Performa_Siswa']);
    Route::put('/performa-siswa/update/{id}', [PelatihController::class, 'Update_Performa_Siswa']);


    //Catatan Pelatih
    Route::get('/catatan-pelatih', [PelatihController::class, 'Catatan_Pelatih']);
    Route::get('/catatan-pelatih/{id}', [PelatihController::class, 'Catatan_perPelatih']);
    Route::post('/catatan-pelatih/tambah', [PelatihController::class, 'Tambah_Catatan_Pelatih']);
    Route::put('/catatan-pelatih/update/{id}', [PelatihController::class, 'Update_Catatan_Pelatih']);
    Route::delete('/catatan-pelatih/hapus/{id}', [PelatihController::class, 'Hapus_Catatan_Pelatih']);

});

    //Upload Bukti Pemnbayaran Perlatihan
    
