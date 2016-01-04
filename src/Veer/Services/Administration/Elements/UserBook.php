<?php

namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class UserBook {

    use DeleteTrait;
    
    protected $delete;
    protected $action;
    protected $data = [];
    protected $userbook;

    public function __construct()
    {
        \Eloquent::unguard();
        $this->delete = head(Input::get('deleteUserbook', []));
        $this->action = Input::get('action');
        $this->data = Input::all();
        $this->userbook = head(Input::get('userbook', []));
    }

    public function run()
    {
        if(!empty($this->delete)) {
            return $this->deleteBook();
        }

        if($this->action == 'addUserbook' || $this->action == 'updateUserBook') {
            $this->updateBook();
        }
    }

    /**
	 * update Books
	 */
	public function updateBook()
	{
        // TODO: move to command ?
		app('veershop')->updateOrNewBook($this->userbook);

        event('veer.message.center', trans('veeradmin.book.update'));
	}

	/**
	 * delete Book
	 * @param int $id
	 */
	protected function deleteBook()
	{
        \Veer\Models\UserBook::where('id', '=', $this->delete)->delete();

        event('veer.message.center', trans('veeradmin.book.delete') .
				" " .$this->restore_link('UserBook', $this->delete));		
	}
}
