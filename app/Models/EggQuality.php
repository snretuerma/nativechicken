<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EggQuality extends Model
{
    public $timestamps = false;
	/**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'egg_qualitities';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'date_collected', 'egg_quality_at', 'weight', 'color', 
        'shape', 'length', 'width', 'albumen_height', 'albumen_weight',
        'yolk_weight', 'yolk_color', 'shell_weight', 'thickness_top',
        'thickness_mid', 'thickness_bot' 
    ];

    public function breeders()
    {
        return $this->belongsTo(Breeder::class);
    }
}