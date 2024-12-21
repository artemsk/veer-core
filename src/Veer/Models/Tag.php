<?php

namespace Veer\Models;

class Tag extends \Eloquent {
    
    protected $table = "tags";
	
    use \Illuminate\Database\Eloquent\SoftDeletes; 	
	protected $dates = ['deleted_at'];
	
    protected $fillable = ["name"];
    
    // Many Tags <-> Many
    
    public function pages() {
        return $this->morphedByMany(\Veer\Models\Page::class, 'elements', 'tags_connect', 'tags_id', 'elements_id');
    }

    public function products() {
        return $this->morphedByMany(\Veer\Models\Product::class, 'elements', 'tags_connect', 'tags_id', 'elements_id');
    }
    
}