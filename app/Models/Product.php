<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use softDeletes;

    protected $guarded = [
        'created_at', 'updated_at', 'deleted_at'
    ];
    
    public $incrementing = false;

    public function user() {
        return $this->belongsTo('App\User', 'user_id');
    }

    public function productImage() {
        return $this->hasOne('App\Models\ProductImage', 'product_id');
    }

    public function orderProducts() {
        return $this->hasMany('App\Models\OrderProduct', 'product_id');
    }
}
