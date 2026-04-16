<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\OrangTua;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{

    // ================= REGISTER =================
   public function register(Request $request)
{
    Log::info('REGISTER REQUEST', [
        'email' => $request->email,
        'ip'    => $request->ip(),
    ]);

    // VALIDASI
    $validated = $request->validate([
        'nama' => 'required|string|max:100',
        'email' => 'required|email|unique:users,email',
        'no_hp' => 'required|numeric|unique:orang_tua,no_hp',
        'password' => 'required|min:6',
    ]);

    DB::beginTransaction();

    try {
        // 🔥 CREATE USER
        $user = User::create([
            'name' => $validated['nama'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'orang_tua',
        ]);

        // 🔥 CREATE ORANG TUA
        $ortu = OrangTua::create([
            'nama_ortu' => $validated['nama'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'no_hp' => $validated['no_hp'],
            'user_id' => $user->id,
        ]);

        DB::commit();

        Log::info('REGISTER SUCCESS', [
            'user_id' => $user->id,
            'ortu_id' => $ortu->id_ortu
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Registrasi berhasil',
            'data' => [
                'user' => $user,
                'orang_tua' => $ortu
            ]
        ], 201);

    } catch (\Exception $e) {

        DB::rollBack();

        Log::error('REGISTER FAILED', [
            'message' => $e->getMessage()
        ]);

        return response()->json([
            'status' => false,
            'message' => 'Registrasi gagal',
        ], 500);
    }
}


    // ================= LOGIN =================
public function login(Request $request)
{
    Log::info('LOGIN REQUEST', [
        'email' => $request->email,
    ]);

    // VALIDASI
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
        'role' => 'required'
    ]);

    // LOGIN
    if (!Auth::attempt($request->only('email', 'password', 'role'))) {
        return response()->json([
            'status' => false,
            'message' => 'Email atau password salah'
        ], 401);
    }

    $user = Auth::user();

    Log::info('LOGIN SUCCESS', [
        'user_id' => $user->id,
        'role' => $user->role,
    ]);

    // 🔥 HAPUS TOKEN LAMA
    $user->tokens()->delete();

    // 🔥 BUAT TOKEN SEKALI SAJA
    $token = $user->createToken('auth_token')->plainTextToken;

    // ================= ORANG TUA =================
    if ($user->role === 'orang_tua') {

        $ortu = $user->orangTua;

        if (!$ortu) {
            return response()->json([
                'status' => false,
                'message' => 'Data orang tua tidak ditemukan',
                'debug_user_id' => $user->id
            ], 404);
        }

        $anak = $ortu->siswa;

        Log::info('DEBUG RELASI', [
            'user_id' => $user->id,
            'ortu_id' => $ortu->id_ortu,
            'jumlah_anak' => $anak->count()
        ]);

        return response()->json([
            'status' => true,
            'role' => 'orang_tua',
            'action' => $anak->count() > 1 ? 'pilih_anak' 
                     : ($anak->count() == 1 ? 'direct_login' : 'belum_ada_anak'),
            'message' => $anak->count() == 0 ? 'Belum memiliki data siswa' : null,
            'data' => $anak,
            'token' => $token
        ]);
    }

    // ================= ADMIN =================
    if ($user->role === 'admin') {
        return response()->json([
            'status' => true,
            'role' => 'admin',
            'message' => 'Login admin berhasil',
            'token' => $token
        ]);
    }

    // ================= PELATIH =================
    if ($user->role === 'pelatih') {
        return response()->json([
            'status' => true,
            'role' => 'pelatih',
            'message' => 'Login pelatih berhasil',
            'token' => $token
        ]);
    }

    // ROLE TIDAK VALID
    Auth::logout();

    return response()->json([
        'status' => false,
        'message' => 'Role tidak valid'
    ], 403);
}

    // ================= LANDING =================
    public function landingPage()
    {
        if (!Auth::check()) {
            return response()->json([
                'status' => false,
                'message' => 'Belum login'
            ], 401);
        }

        $user = Auth::user();

        if ($user->role !== 'orang_tua') {
            return response()->json([
                'status' => false,
                'message' => 'Bukan orang tua'
            ], 403);
        }

        $anak = Siswa::where('id_ortu', $user->id)->get();

        return response()->json([
            'status' => true,
            'jumlah_anak' => $anak->count(),
            'data' => $anak
        ]);
    }


   
}