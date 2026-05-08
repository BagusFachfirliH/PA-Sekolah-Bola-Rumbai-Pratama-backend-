<?php

namespace App\Http\Controllers;

use App\Models\Promosi;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class MenuUtamaController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->resolveUserFromToken($request);

        $berita = Promosi::query()
            ->where('kategori', 'Berita')
            ->orderByDesc('tanggal_promosi')
            ->orderByDesc('id_promosi')
            ->limit(10)
            ->get([
                'id_promosi',
                'judul',
                'isi_promosi',
                'tanggal_promosi',
                'foto_promosi',
                'kategori',
            ])
            ->map(function ($item) {
                return [
                    'id_promosi' => $item->id_promosi,
                    'judul' => $item->judul,
                    'isi' => $item->isi_promosi,
                    'tanggal' => $item->tanggal_promosi,
                    'foto' => $item->foto_promosi,
                    'foto_url' => $this->toStorageUrl($item->foto_promosi),
                    'kategori' => $item->kategori,
                ];
            })
            ->values();

        $galeri = Promosi::query()
            ->whereNotNull('foto_promosi')
            ->where('foto_promosi', '!=', '')
            ->whereIn('kategori', ['Akun Sosial', 'Berita'])
            ->orderByDesc('tanggal_promosi')
            ->orderByDesc('id_promosi')
            ->limit(20)
            ->get([
                'id_promosi',
                'judul',
                'foto_promosi',
                'tanggal_promosi',
                'kategori',
            ])
            ->map(function ($item) {
                return [
                    'id_promosi' => $item->id_promosi,
                    'judul' => $item->judul,
                    'tanggal' => $item->tanggal_promosi,
                    'foto' => $item->foto_promosi,
                    'foto_url' => $this->toStorageUrl($item->foto_promosi),
                    'kategori' => $item->kategori,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Data menu utama berhasil diambil',
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ] : null,
            'data' => [
                'berita' => $berita,
                'galeri' => $galeri,
                'instagram' => [
                    'source' => 'manual_link',
                    'profile_url' => 'https://www.instagram.com/ssbrumbaipratama/',
                ],
            ],
        ]);
    }

    public function instagram(Request $request)
    {
        $user = $this->resolveUserFromToken($request);

        $feed = Promosi::query()
            ->where('kategori', 'Akun Sosial')
            ->whereNotNull('foto_promosi')
            ->where('foto_promosi', '!=', '')
            ->orderByDesc('tanggal_promosi')
            ->orderByDesc('id_promosi')
            ->limit(12)
            ->get([
                'id_promosi',
                'judul',
                'isi_promosi',
                'tanggal_promosi',
                'foto_promosi',
                'kategori',
            ])
            ->map(function ($item) {
                return [
                    'id' => $item->id_promosi,
                    'caption' => $item->judul ?: $item->isi_promosi,
                    'media_url' => $this->toStorageUrl($item->foto_promosi),
                    'thumbnail_url' => $this->toStorageUrl($item->foto_promosi),
                    'permalink' => 'https://www.instagram.com/ssbrumbaipratama/',
                    'timestamp' => $item->tanggal_promosi,
                    'source' => 'promosi_akun_sosial',
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Data instagram berhasil diambil',
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ] : null,
            'instagram' => [
                'source' => 'fallback_promosi',
                'profile_url' => 'https://www.instagram.com/ssbrumbaipratama/',
                'items' => $feed,
            ],
            'notes' => [
                'Untuk feed live Instagram, integrasikan Meta Graph API dan ganti source menjadi graph_api.',
            ],
        ]);
    }

    private function resolveUserFromToken(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);
        return $accessToken ? $accessToken->tokenable : null;
    }

    private function toStorageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return url('/storage/' . ltrim($path, '/'));
    }
}
