<?php namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class Search {

    protected $action;
    protected $type = 'search';
    
    public function __construct()
    {
        \Eloquent::unguard();
    }

    public static function request()
    {
        $class = new static;
        $class->acton = Input::get('action');

        !Input::has('deleteSearch') ?: $class->delete(Input::get('deleteSearch'));
        $class->action != 'addSearch' ?: $class->add(Input::get('search'), Input::get('users'));
    }

    public function delete($id)
    {
        $id = is_array($id) ? head($id) : $id;        
        if(!empty($id) && $this->deleteSearch($id)) {
            event('veer.message.center', trans('veeradmin.search.delete'));
        }

        return $this;
    }

    public function add($q, $users_id = null, $returnId = false)
    {
        $search = \Veer\Models\Search::firstOrCreate(["q" => $q]);
        $search->increment('times');
        $search->save();

        if(!empty($users_id)) {
            if(!is_array($users_id)) {
                $users_id = explode(',', trim($users_id, ': '));
            }

            if($users_id) {
                $search->users()->attach($users_id);
                event('veer.message.center', trans('veeradmin.search.new'));
            }
        }

        return $returnId ? $search->id : $this;
    }

    // @todo detach users id from search
    public function attach($users_id, $id)
    {
        if(!empty($users_id) && !empty($id)) {
            $search = \Veer\Models\Search::find($id);
            if(is_object($search)) {
                $search->users()->attach($users_id);
                event('veer.message.center', trans('veeradmin.search.new'));
            }
        }

        return $this;
    }
}