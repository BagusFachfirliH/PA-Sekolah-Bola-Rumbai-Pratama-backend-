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


Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/verify-email', [AuthController::class, 'verifyEmail']);



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
    Route::get('/siswa/kehadiran', [SiswaController::class, 'kehadiranSiswa']);
    Route::get('/siswa/performa', [SiswaController::class, 'performaSiswa']);
    Route::get('/siswa/prestasi', [SiswaController::class, 'prestasiSiswa']);
    Route::get('/siswa/catatan-pelatih', [SiswaController::class, 'catatanPelatihSiswa']);
    Route::get('/siswa/history-pembayaran', [SiswaController::class, 'historyPembayaranSiswa']);


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
    Route::get('/siswa/revisi-pendaftaran/{id_siswa}', [SiswaController::class, 'revisi_pendaftaran']);
    Route::post('/siswa/update-pendaftaran/{id_siswa}', [SiswaController::class, 'update_pendaftaran']);
    

    // Notfikasi
     Route::get('/notifikasi', [NotifikasiController::class, 'getNotifikasi']);
     Route::post('/notifikasi/kirim', [NotifikasiController::class, 'kirimNotif']);
    Route::post('/notifikasi/baca/{id}', [NotifikasiController::class, 'tandaiBaca']);


    //Pembayaran siswa (Admin)
    Route::get('/admin/pembayaran-admin', [AdminController::class, 'pembayaran_admin']);
    Route::get('/admin/bukti-pembayaran-admin/{id_siswa}', [AdminController::class, 'buktipembayaran_admin']);
    Route::get('/admin/lihat-bukti/{folder}/{file}', [AdminController::class, 'lihatBukti_pembayaran_admin']);
    Route::post('/admin/bukti/approve/{id}', [AdminController::class, 'Bukti_Diterima']);
    Route::post('/admin/bukti/reject/{id}', [AdminController::class, 'Bukti_Ditolak']);
    Route::get('/admin/history-pembayaran', [AdminController::class, 'history_pembayaran']);


    //Pembayaran siswa (Siswa)
    Route::get('/admin/upload-bukti/{id_pembayaran}/{id_siswa}', [SiswaController::class, 'Upload_Bukti_Pembayaran']);
    Route::post('/admin/upload-bukti/{id_pembayaran}', [SiswaController::class, 'Store_Bukti_Pembayaran']);

    //Data Siswa (Admin)
    Route::get('/admin/data-siswa', [AdminController::class, 'Data_Siswa']);
    Route::get('/admin/performa-siswa/{id_siswa}', [AdminController::class, 'performaperSiswa']);
    Route::get('/admin/kehadiran-siswa/{id_siswa}', [AdminController::class, 'Rekap_Absensi_PerSiswa']);
    

    //Data Pelatih (Admin)
    Route::get('/admin/data-pelatih', [AdminController::class, 'Data_Pelatih']);
    Route::post('/admin/tambah-pelatih', [AdminController::class, 'Tambah_Pelatih']);
    Route::put('/admin/edit-pelatih/{id}', [AdminController::class, 'Update_Pelatih']);
    Route::delete('/admin/hapus-pelatih/{id}', [AdminController::class, 'Hapus_Pelatih']);

    // Jadwal Latihan (Admin)
    Route::get('/admin/jadwal-latihan', [AdminController::class, 'Jadwallatihan_Siswa']);
    Route::get('/admin/jadwal-latihan/{id}', [AdminController::class, 'JadwalperPelatih']);
    Route::post('/admin/tambah-jadwal', [AdminController::class, 'Tambah_Jadwal']);
    Route::put('/admin/jadwal-latihan/{id}', [AdminController::class, 'Update_Jadwal']);
    Route::delete('/admin/jadwal-latihan/{id}', [AdminController::class, 'Hapus_Jadwal']);

    // Media Promosi (Admin)
    Route::get('/admin/media-promosi', [AdminController::class, 'MediaPromosiAdmin']);
    Route::get('/admin/media-promosi/{id}', [AdminController::class, 'DetailMediaPromosi']);
    Route::post('/admin/tambah_media-promosi', [AdminController::class, 'TambahMediaPromosi']);
    Route::post('/admin/media-promosi/{id}', [AdminController::class, 'UpdateMediaPromosi']);
    Route::delete('/admin/media-promosi/{id}', [AdminController::class, 'HapusMediaPromosi']);
    Route::post('/admin/media-promosi/group/{group_id}', [AdminController::class, 'UpdateMediaPromosiByGroup']);
    Route::delete('/admin/media-promosi/group/{group_id}', [AdminController::class, 'HapusMediaPromosiByGroup']);

    //Presensi Pelatih
    Route::get('/pelatih/presensi', [PelatihController::class, 'kehadiran']);
    Route::post('/pelatih/presensi/input', [PelatihController::class, 'Input_Presensi']);
    Route::get('/pelatih/presensi/rekap', [PelatihController::class, 'Rekap_Absensi']);
    Route::delete('/admin/presensi/{id_jadwal}', [AdminController::class, 'hapus_presensi']);


    //Performa Siswa Pelatih
    Route::get('/pelatih/performa-siswa/{id}', [PelatihController::class, 'Performa_Siswa']);
    Route::post('/pelatih/performa-siswa/input/{id}', [PelatihController::class, 'Input_Performa_Siswa']);
    Route::put('/pelatih/performa-siswa/update/{id}', [PelatihController::class, 'Update_Performa_Siswa']);


    //Catatan Pelatih
    Route::get('/pelatih/catatan-pelatih', [PelatihController::class, 'Catatan_Pelatih']);
    Route::get('/pelatih/catatan-pelatih/{id}', [PelatihController::class, 'Catatan_perPelatih']);
    Route::post('/pelatih/catatan-pelatih/tambah', [PelatihController::class, 'Tambah_Catatan_Pelatih']);
    Route::put('/pelatih/catatan-pelatih/update/{id}', [PelatihController::class, 'Update_Catatan_Pelatih']);
    Route::delete('/pelatih/catatan-pelatih/hapus/{id}', [PelatihController::class, 'Hapus_Catatan_Pelatih']);

    // Bukti Pembayaran Pelatih
    Route::get('/pelatih/bukti-pembayaran/form', [PelatihController::class, 'FormUploadBuktiPembayaran']);
    Route::post('/pelatih/bukti-pembayaran', [PelatihController::class, 'Store_Bukti_Pembayaran_Pelatih']);
    Route::get('/pelatih/bukti-pembayaran/history', [PelatihController::class, 'History_Bukti_Pembayaran_Pelatih']);
    Route::delete('/pelatih/bukti-pembayaran/{id}', [PelatihController::class, 'Hapus_Bukti_Pembayaran_Pelatih']);

    // Prestasi (Admin)
    Route::get('/admin/prestasi/form', [AdminController::class, 'FormPrestasiAdmin']);
    Route::post('/admin/prestasi/tambah-prestasi', [AdminController::class, 'StorePrestasiAdmin']);
    Route::get('/admin/prestasi/history', [AdminController::class, 'HistoryPrestasiAdmin']);

});

   
    
