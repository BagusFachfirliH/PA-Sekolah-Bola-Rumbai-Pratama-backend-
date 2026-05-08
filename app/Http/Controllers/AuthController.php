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
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password as PasswordRule;
use App\Mail\SendPasswordMail;
use App\Mail\ForgotPasswordMail;
use App\Mail\VerifyEmailMail;


class AuthController extends Controller
{

    // ================= REGISTER =================
 public function register(Request $request)
{
    Log::info('REGISTER REQUEST', [
        'email' => $request->email,
        'ip'    => $request->ip(),
    ]);

    $validated = $request->validate([
        'nama' => 'required|string|max:100',
        'email' => 'required|email|unique:users,email',
        'no_hp' => 'required|numeric|unique:orang_tua,no_hp',
        'password' => [
            'required',
            'confirmed',
            PasswordRule::min(8)->letters()->numbers()->symbols(),
        ],
    ]);

    DB::beginTransaction();

    try {

        $token = Str::random(64);

        $user = User::create([
            'name' => $validated['nama'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'orang_tua',
            'verification_token' => $token,
            'email_verified_at' => null
        ]);

        // 🔥 DEBUG DI SINI (INI YANG BENAR)
        Log::info('USER CREATED', [
            'user_id' => $user->id,
            'token_generated' => $token,
            'token_saved' => $user->verification_token,
        ]);

        OrangTua::create([
            'nama_ortu' => $validated['nama'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'no_hp' => $validated['no_hp'],
            'user_id' => $user->id,
        ]);

        DB::commit();

        $link = url("/api/verify-email?token=$token&email=" . urlencode($user->email));

        Mail::to($user->email)->send(
            new VerifyEmailMail($user->name, $link)
        );

        return response()->json([
            'status' => true,
            'message' => 'Registrasi berhasil, cek email untuk verifikasi'
        ], 201);

    } catch (\Exception $e) {

        DB::rollBack();

        return response()->json([
            'status' => false,
            'message' => 'Registrasi gagal',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function verifyEmail(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'token' => 'required'
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user) {
        return response()->json([
            'status' => false,
            'message' => 'User tidak ditemukan'
        ]);
    }

    if ($user->verification_token !== $request->token) {
        return response()->json([
            'status' => false,
            'message' => 'Token tidak cocok',
            'debug' => [
                'db' => $user->verification_token,
                'req' => $request->token
            ]
        ]);
    }

    $user->update([
        'email_verified_at' => now(),
        'verification_token' => null
    ]);

    return response()->json([
        'status' => true,
        'message' => 'Email berhasil diverifikasi'
    ]);
}
    // ================= LOGIN =================
public function login(Request $request)
{
    Log::info('LOGIN REQUEST', [
        'email' => $request->email,
        'role' => $request->role
    ]);

    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
        'role' => 'required'
    ]);

    // 🔥 FIX: AMBIL USER (CASE INSENSITIVE)
    $user = User::whereRaw('LOWER(email) = ?', [strtolower(trim($request->email))])->first();

    // ❌ USER TIDAK ADA
    if (!$user) {
        return response()->json([
            'status' => false,
            'message' => 'Email atau password salah'
        ], 401);
    }

    // ❌ PASSWORD SALAH
    if (!Hash::check($request->password, $user->password)) {
        return response()->json([
            'status' => false,
            'message' => 'Email atau password salah'
        ], 401);
    }

    // ❌ ROLE TIDAK SESUAI
    if ($user->role !== $request->role) {
        return response()->json([
            'status' => false,
            'message' => 'Role tidak sesuai'
        ], 403);
    }

    // ❌ EMAIL VERIFIKASI (HANYA ORANG TUA)
    if ($user->role === 'orang_tua' && is_null($user->email_verified_at)) {
        return response()->json([
            'status' => false,
            'message' => 'Email belum diverifikasi'
        ], 403);
    }

    // 🔥 LOGIN
    Auth::login($user);

    Log::info('LOGIN SUCCESS', [
        'user_id' => $user->id,
        'role' => $user->role,
    ]);

    // 🔥 HAPUS TOKEN LAMA
    $user->tokens()->delete();

    // 🔥 TOKEN BARU
    $token = $user->createToken('auth_token')->plainTextToken;

    // ================= ORANG TUA =================
    if ($user->role === 'orang_tua') {

        $ortu = $user->orangTua;

        if (!$ortu) {
            return response()->json([
                'status' => false,
                'message' => 'Data orang tua tidak ditemukan'
            ], 404);
        }

        $anak = $ortu->siswa;

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

    return response()->json([
        'status' => false,
        'message' => 'Role tidak valid'
    ], 403);
}
public function forgotPassword(Request $request)
{
    $request->validate([
        'email' => 'required|email|exists:users,email'
    ]);

    $token = Str::random(64);

    // ✅ SIMPAN TOKEN ASLI (TANPA HASH)
    DB::table('password_reset_tokens')->updateOrInsert(
        ['email' => $request->email],
        [
            'token' => $token,
            'created_at' => now()
        ]
    );

    // 🔥 LINK RESET
    $link = url("/reset-password?token=$token&email=" . urlencode($request->email));

    // 🔥 KIRIM EMAIL
    Mail::to($request->email)->send(
        new ForgotPasswordMail($link)
    );

    return response()->json([
        'status' => true,
        'message' => 'Link reset password dikirim ke email'
    ]);
}

public function resetPassword(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'token' => 'required',
        'password' => 'required|min:6|confirmed',
    ]);

    $record = DB::table('password_reset_tokens')
        ->where('email', $request->email)
        ->first();

    // ✅ FIX: bandingkan token langsung
    if (!$record || $request->token !== $record->token) {
        return response()->json([
            'status' => false,
            'message' => 'Token tidak valid'
        ], 400);
    }

    $user = User::where('email', $request->email)->first();

    if (!$user) {
        return response()->json([
            'status' => false,
            'message' => 'User tidak ditemukan'
        ], 404);
    }

    $user->update([
        'password' => Hash::make($request->password),
    ]);

    DB::table('password_reset_tokens')
        ->where('email', $request->email)
        ->delete();

    return response()->json([
        'status' => true,
        'message' => 'Password berhasil diubah'
    ]);
}

   
}
