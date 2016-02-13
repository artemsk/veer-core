<?php

namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class UserBook {

    use DeleteTrait;
    
    protected $type = 'userbook';
    protected $action;

    public function __construct()
    {
        \Eloquent::unguard();
    }

    public static function request()
    {
        $class = new static;
        $class->action = Input::get('action');

        !Input::has('deleteUserbook') ?: $class->delete(Input::get('deleteUserbook'));
        if($class->action == 'addUserbook' || $class->action == 'updateUserbook') {
            $class->update(head(Input::get('userbook', [])));
        }
    }

    public function delete($id)
    {
        $id = is_array($id) ? head($id) : $id;
        if(!empty($id) && $this->deleteUserBook($id)) {
            event('veer.message.center', trans('veeradmin.book.delete') .
				" " .$this->restore_link('UserBook', $id));
        }

        return $this;
    }

    public function add($userbook)
    {
        return $this->update($userbook);
    }

	public function update($userbook)
	{
        // @todo move to command or here ?
		app('veershop')->updateOrNewBook($userbook);
        event('veer.message.center', trans('veeradmin.book.update'));

        return $this;
	}
    
}
