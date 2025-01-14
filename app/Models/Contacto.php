<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Contacto
 * 
 * @property int $id_persona
 * @property int $id_empresa
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Persona $persona
 * @property Empresa $empresa
 *
 * @package App\Models
 */
class Contacto extends Model
{
	// Indicate that the primary key is a composite key
	protected $primaryKey = ['id_persona', 'id_empresa'];

	protected $table = 'contactos';
	public $incrementing = false;

	protected $casts = [
		'id_persona' => 'int',
		'id_empresa' => 'int'
	];

	protected $fillable = [
		'id_persona',
		'id_empresa'
	];

	public function persona()
	{
		return $this->belongsTo(Persona::class, 'id_persona');
	}

	public function empresa()
	{
		return $this->belongsTo(Empresa::class, 'id_empresa');
	}
}
