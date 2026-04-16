<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Models\OrangTua;
use App\Models\Siswa;
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

        DB::commit();

        return response()->json([
            'status' => true,
            'message' => 'Pendaftaran siswa berhasil',
            'data' => [
                'siswa' => $siswa,
                'pendaftaran' => $pendaftaran
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

    // ambil data siswa dari database
    $siswa = \App\Models\Siswa::where('id_siswa', $request->id_siswa)
        ->first(['id_siswa', 'nama_siswa']);

    // simpan ke session
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
        ->firstOrFail();

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
    if ($pendaftaran->val_pasfoto == 'tidak_valid') $invalidUploadFields[] = 'pas_photo_3x4';

    return response()->json([
        'success' => true,
        'data' => [
            'siswa' => $pendaftaran->siswa,
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
        $pendaftaran = Pendaftaran_Siswa::where('id_siswa', $id_siswa)->firstOrFail();

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

        // =========================
        // SAVE
        // =========================
        $siswa->save();

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Revisi pendaftaran berhasil diperbarui',
            'data' => $siswa
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



}
