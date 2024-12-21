<?php

namespace Veer\Models;

class Search extends \Eloquent {
    
    protected $table = "searches";
	
    use \Illuminate\Database\Eloquent\SoftDeletes; 	
	protected $dates = ['deleted_at'];
	
    protected $fillable = ["q"];
    
    // Many Searches <-> Many Users
    
    public function users() {
        return $this->belongsToMany(\Veer\Models\User::class, 'searches_connect', 'searches_id', 'users_id');
    }
    
}