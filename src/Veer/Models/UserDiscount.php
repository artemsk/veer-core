<?php

namespace Veer\Models;

class UserDiscount extends \Eloquent {
    
    protected $table = "users_discounts";
	
    use \Illuminate\Database\Eloquent\SoftDeletes; 	
	protected $dates = ['deleted_at'];
	
    protected $fillable = ['status'];
    
    // Many Discounts <- One
    
    public function site() {
        return $this->belongsTo(\Veer\Models\Site::class,'sites_id','id');
    }
        
    public function user() {
        return $this->belongsTo(\Veer\Models\User::class,'users_id','id');
    }
    
    // One Discount -> Many
    
    public function orders() {
       return $this->hasMany(\Veer\Models\Order::class, 'userdiscount_id', 'id'); 
    }
    
}
