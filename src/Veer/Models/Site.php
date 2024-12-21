<?php

namespace Veer\Models;

class Site extends \Eloquent {
    
    protected $table = "sites";
	
    use \Illuminate\Database\Eloquent\SoftDeletes; 	
	protected $dates = ['deleted_at'];
	
    protected $fillable = ["url"];
    
    // One Site -> Many
    
    public function subsites() {
        return $this->hasMany(\Veer\Models\Site::class,'parent_id','id');
    }
    
    public function categories() {
        return $this->hasMany(\Veer\Models\Category::class, 'sites_id', 'id');
        //return $this->hasManyThrough('\Veer\Models\Category', '\Veer\Models\Site', 'parent_id', 'sites_id');
    }
    
    public function components() {
       return $this->hasMany(\Veer\Models\Component::class, 'sites_id', 'id'); 
    }
    
    public function configuration() {
       return $this->hasMany(\Veer\Models\Configuration::class, 'sites_id', 'id'); 
    }

    public function users() {
       return $this->hasMany(\Veer\Models\User::class, 'sites_id', 'id'); 
    }
    
    public function discounts() {
       return $this->hasMany(\Veer\Models\UserDiscount::class, 'sites_id', 'id'); 
    }
    
    public function userlists() {
       return $this->hasMany(\Veer\Models\UserList::class, 'sites_id', 'id'); 
    }
    
    public function orders() {
       return $this->hasMany(\Veer\Models\Order::class, 'sites_id', 'id'); 
    }
    
    public function delivery() {
       return $this->hasMany(\Veer\Models\OrderShipping::class, 'sites_id', 'id'); 
    }    
 
    public function payment() {
       return $this->hasMany(\Veer\Models\OrderPayment::class, 'sites_id', 'id'); 
    }  
    
    public function communications() {
       return $this->hasMany(\Veer\Models\Communication::class, 'sites_id');
    }
    
    public function roles() {
       return $this->hasMany(\Veer\Models\UserRole::class, 'sites_id', 'id'); 
    }
    
    public function elements() {
        return $this->hasManyThrough(\Veer\Models\CategoryConnect::class, \Veer\Models\Category::class, 'sites_id', 'categories_id');
    }
    
    // Many Sites <- One
    
    public function parentsite() {
        return $this->belongsTo(\Veer\Models\Site::class,'parent_id','id');        
    }
    
}