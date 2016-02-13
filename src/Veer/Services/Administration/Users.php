<?php namespace Veer\Services\Administration;

use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Event;

class Users {

    use Elements\DeleteTrait;
    
    protected $action = null;

    public function __construct($t)
    {
        $this->action = 'update' . ucfirst($t);
        app('veer')->skipShow = false; // ?
    }

    public function handle()
    {
        return $this->{$this->action}();
    }
    
	/**
	 * update Roles
	 */
	public function updateRoles()
	{
		return Elements\Role::request();
	}

	/**
	 * update Communications
	 */
	public function updateCommunications()
	{
		return Elements\Communication::request();
	}

	/**
	 * update Comments
	 */
	public function updateComments()
	{
		return Elements\Comment::request();
	}

	/**
	 * update Searches
	 */
	public function updateSearches()
	{
		return Elements\Search::request();
	}

	/**
	 * update Lists
	 */
	public function updateLists()
	{
		return Elements\UserList::request();
	}	

	/**
	 * update Books
	 */
	public function updateBooks()
	{
		return Elements\UserBook::request();
	}

	/**
	 * update Users
	 */
	public function updateUsers()
	{
        return Elements\User::request();
	}
}
