<?php

namespace Veer\Models;

class UserRole extends \Eloquent {
    
    protected $table = "users_roles";
	
    use \Illuminate\Database\Eloquent\SoftDeletes; 	
	protected $dates = ['deleted_at'];
    
    // Many Roles <- One
    
    public function site() {
        return $this->belongsTo(\Veer\Models\Site::class,'sites_id','id');
    }
    
    // One Role -> Many
    
    public function users() {
       return $this->hasMany(\Veer\Models\User::class, 'roles_id', 'id'); 
    }
    
}