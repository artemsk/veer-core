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
		return (new Elements\Role)->run();
	}

	/**
	 * update Communications
	 */
	public function updateCommunications()
	{
		return (new Elements\Communication)->run();
	}

	/**
	 * update Comments
	 */
	public function updateComments()
	{
		return (new Elements\Comment)->run();
	}

	/**
	 * update Searches
	 */
	public function updateSearches()
	{
		return (new Elements\Search)->run();
	}

	/**
	 * update Lists
	 */
	public function updateLists()
	{
		return (new Elements\UserList)->run();
	}	

	/**
	 * update Books
	 */
	public function updateBooks()
	{
		return (new Elements\UserBook)->run();
	}

	/**
	 * update Users
	 */
	public function updateUsers()
	{
		return (new Elements\User)->run();
	}
}
