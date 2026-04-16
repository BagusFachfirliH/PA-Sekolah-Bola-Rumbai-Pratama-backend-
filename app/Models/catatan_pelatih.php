<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Catatan_Pelatih extends Model
{
    protected $table = 'catatan_pelatih';
    protected $primaryKey = 'id_catatan';

    protected $fillable = [
        'id_siswa',
        'id_pelatih',
        'catatan',
        'tanggal_catatan'
    ];

    public $timestamps = false;


    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'id_siswa');
    }


    public function pelatih()
    {
        return $this->belongsTo(Pelatih::class, 'id_pelatih');
    }
}
