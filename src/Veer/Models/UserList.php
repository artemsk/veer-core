<?php

namespace Veer\Models;

class UserList extends \Eloquent {

    protected $table = "users_lists";

    protected $softDelete = false;

	protected $fillable = ['session_id'];

    // Many Lists <- One

    public function site() {
        return $this->belongsTo(\Veer\Models\Site::class,'sites_id','id');
    }

    public function user() {
        return $this->belongsTo(\Veer\Models\User::class,'users_id','id');
    }

    public function elements() {
        return $this->morphTo();
    }
}

// @todo взаимосвязь с order?