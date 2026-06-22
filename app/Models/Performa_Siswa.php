<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Performa_Siswa extends Model
{
    protected $table = 'performa_siswa';
    protected $primaryKey = 'id_performa';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'id_jadwal',
        'id_pelatih',
        'id_siswa',
        'tanggal_penilaian',
        'dribbling',
        'passing',
        'shooting'
    ];

    public $timestamps = false;

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'id_siswa');
    }

    public function jadwal()
    {
        return $this->belongsTo(Jadwal_Latihan::class, 'id_jadwal', 'id_jadwal');
    }

    public function pelatih()
    {
        return $this->belongsTo(Pelatih::class, 'id_pelatih', 'id_pelatih');
    }
}
