<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfilSiswa extends Model
{
    protected $table = 'profil_siswa';
    protected $primaryKey = 'id_siswa';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_siswa',
        'id_ortu',
        'nik',
        'no_kk',
        'nisn',
        'tempat_lahir',
        'tanggal_lahir',
        'alamat',
        'foto',
        'tinggi_badan',
        'berat_badan',
    ];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'id_siswa');
    }

    public function orangtua()
    {
        return $this->belongsTo(OrangTua::class, 'id_ortu', 'id_ortu');
    }
}
