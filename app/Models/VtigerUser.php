<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VtigerUser extends Model
{
    protected $connection = 'vtiger'; // Usa la conexión vtiger
    protected $table = 'vtiger_users'; // Nombre exacto de la tabla
    protected $primaryKey = 'id';      // En Vtiger 8.1.0, es 'id'
    public $timestamps = false;        // Vtiger no usa created_at/updated_at

    // Campos que quieres permitir acceder (solo lectura por ahora)
    protected $fillable = [];
    protected $guarded = []; // O define explícitamente los campos si prefieres
    protected $visible = ['id', 'user_name', 'first_name', 'last_name', 'email1', 'salt', 'user_password', 'status'];

    
}
