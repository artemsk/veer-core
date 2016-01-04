<?php namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class Search {

    protected $action;
    protected $delete;
    protected $search;
    protected $users;
    
    public function __construct()
    {
        \Eloquent::unguard();
        $this->action = Input::get('action');
        $this->search = trim(Input::get('search'));
        $this->delete = head(Input::get('deleteSearch', []));
        $this->users = Input::get('users');
    }

    public function run()
    {
        if(!empty($this->delete)) {
            return $this->deleteSearch();
        }

        if($this->action == 'addSearch' && !empty($this->search)) {
            $this->addSearch();
        }
    }

    protected function addSearch()
    {
        $search = \Veer\Models\Search::firstOrCreate(["q" => $this->search]);
        $search->increment('times');
        $search->save();

        if(starts_with($this->users, ':')) {

            $users = substr($this->users, 1);

            if(!empty($this->users)) {
                $users = explode(",", trim($users));

                if(count($users) > 0) $search->users()->attach($users);
            }
        }

        event('veer.message.center', trans('veeradmin.search.new'));
    }

    /**
	 * delete Search
	 * @param int $id
	 */
	protected function deleteSearch()
	{
        $s = \Veer\Models\Search::find($this->delete);

		if(is_object($s)) {
			$s->users()->detach();
			$s->delete();
		}

        event('veer.message.center', trans('veeradmin.search.delete'));
	}

}