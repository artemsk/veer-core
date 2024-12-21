<?php

namespace Veer\Models;

class Order extends \Eloquent {
    
    protected $table = "orders";
	
    use \Illuminate\Database\Eloquent\SoftDeletes; 	
	protected $dates = ['deleted_at'];
    
    // Many Orders <- One
    
    public function site() {
        return $this->belongsTo(\Veer\Models\Site::class,'sites_id','id');
    }
        
    public function user() {
        return $this->belongsTo(\Veer\Models\User::class,'users_id','id');
    }
    
    public function userbook() {
        return $this->belongsTo(\Veer\Models\UserBook::class,'userbook_id','id');
    }
    
    public function userdiscount() {
        return $this->belongsTo(\Veer\Models\UserDiscount::class,'userdiscount_id','id');
    }
    
    // latest status
    public function status() {
        return $this->belongsTo(\Veer\Models\OrderStatus::class,'status_id','id');
    }
    
    public function delivery() {
        return $this->belongsTo(\Veer\Models\OrderShipping::class,'delivery_method_id','id');
    }
    
    public function payment() {
        return $this->belongsTo(\Veer\Models\OrderPayment::class,'payment_method_id','id');
    }
    
    // Many Orders <-> Many
    
    public function status_history() {
        return $this->belongsToMany(\Veer\Models\OrderStatus::class,'orders_history', 'orders_id', 'status_id')
		->withPivot('id', 'name', 'comments', 'to_customer', 'order_cache')
		->withTimestamps();        
    }
    
    public function products() {
        return $this->belongsToMany(\Veer\Models\Product::class,'orders_products', 'orders_id', 'products_id')
		->withPivot('id');      
    }
    
    public function downloads() {
        return $this->hasManyThrough(\Veer\Models\Download::class, \Veer\Models\OrderProduct::class, 'orders_id', 'elements_id');
		// experimental - later we should skip everything except products
    }
	
    // One Order -> Many
    
    public function bills() {
       return $this->hasMany(\Veer\Models\OrderBill::class, 'orders_id', 'id'); 
    }
    
	public function secrets() {
        return $this->morphMany(\Veer\Models\Secret::class, 'elements');
    }   
	
    public function communications() {
        return $this->morphMany(\Veer\Models\Communication::class, 'elements');
    }     	
	
	// order content (not only products)
	public function orderContent() {
		return $this->hasMany(\Veer\Models\OrderProduct::class, 'orders_id', 'id');
	}
	
	public function scopeNotProducts($query) {
		return $query->with(['orderContent' => function($q) 
		{
			$q->where('product', '!=', true);
		}]);
	}
	
}