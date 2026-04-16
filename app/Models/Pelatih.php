<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pelatih extends Model
{
    use HasFactory;

    protected $table = 'pelatih';

    protected $primaryKey = 'id_pelatih';

    protected $fillable = [
        'user_id',
        'nama_pelatih',
        'email',
        'no_hp',
    ];

    public $timestamps = False;

    // Relasi ke tabel users
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function jadwal()
{
    return $this->hasMany(Jadwal_Latihan::class, 'id_pelatih');
}

public function catatan()
{
    return $this->hasMany(Catatan_Pelatih::class, 'id_pelatih');
}


}