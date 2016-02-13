<?php

namespace Veer\Models;

class Download extends \Eloquent {
    
    protected $table = "downloads";
	
    use \Illuminate\Database\Eloquent\SoftDeletes; 	
	protected $dates = ['deleted_at'];

    // Many Downloads <- One
    
    public function elements() {
        return $this->morphTo();
    }
    
    
}
