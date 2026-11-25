<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransportlidzeklaFoto extends Model
{
    protected $table = 'transportlidzekla_foto';
    protected $primaryKey = 'foto_id';
    protected $fillable = ['mime_type', 'attels'];
    protected $hidden = ['attels'];
}
