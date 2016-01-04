<?php

namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class User {

    use DeleteTrait;
    
    protected $restrictions = [];
    protected $ban = [];
    protected $delete = [];
    protected $id;
    protected $action;
    protected $data = [];

    public function __construct()
    {
        \Eloquent::unguard();
        //Event::fire('router.filter: csrf');
        $this->restrictions = Input::get('changeRestrictUser', []);
		$this->ban = Input::get('changeStatusUser', []);
		$this->delete = Input::get('deleteUser', []);
        $this->id = Input::get('id');
        $this->action = Input::get('action');
        $this->data = Input::all();
    }

    public function run()
    {
        if(!empty($this->restrictions) && is_array($this->restrictions)) {
            return $this->updateRestrictions();
        }

        if(!empty($this->ban) && is_array($this->ban)) {
            return $this->updateBan();
        }

        if(!empty($this->delete) && is_array($this->delete)) {
            return $this->deleteUser();
        }

        if(!empty($this->id)) {
            return $this->updateOne();
        }

        if($this->action == 'Add') {
            return $this->addUser();
        }
    }

    protected function updateRestrictions()
    {
        \Veer\Models\User::where('id', '=', key($this->restrictions))
				->update(['restrict_orders' => head($this->restrictions)]);
        
        event('veer.message.center', trans('veeradmin.user.update'));
    }

    protected function updateBan()
    {
        if(key($this->ban) != \Auth::id()) {
            
			\Veer\Models\User::where('id', '=', key($this->ban))
				->update(['banned' => head($this->ban)]);

			if(head($this->ban) == true) {
				\Veer\Models\UserAdmin::where('users_id', '=', key($this->ban))
				->update(['banned' => head($this->ban)]);
			}

			event('veer.message.center', trans('veeradmin.user.ban'));
		}
    }

    protected function deleteUser()
    {
        $id = is_array($this->delete) ? key($this->delete) : $this->delete;

        if($id == \Auth::id()) {
			return null;
		}

        $u = \Veer\Models\User::find($id);

		if(is_object($u)) {
			$u->discounts()->update(["status" => "canceled"]);
			$u->userlists()->update(["users_id" => false]);
			$u->books()->update(["users_id" => false]);
			$u->images()->detach();
			$u->searches()->detach();
			$u->administrator()->delete();
			// don't update: orders, bills, pages, comments, communications
			// do not need: site, role
			$u->delete();
		}

     	event('veer.message.center', trans('veeradmin.user.delete') .
				" " . $this->restore_link("user", $id));
    }

    protected function addUser()
    {
        if(!empty($this->data['freeForm'])) {
            preg_match_all("/^(.*)$/m", trim($this->data['freeForm']), $parseForm);
        }

        $freeFormKeys = [
            'username', 'phone', 'firstname', 'lastname', 'birth', 'gender', 'roles_id',
            'newsletter', 'restrict_orders', 'banned'
        ];

        if(empty($this->data['siteId'])) {
            $this->data['siteId'] = app('veer')->siteId;
        }
        
        $rules = [
            'email' => 'required|email|unique:users,email,NULL,id,deleted_at,NULL,sites_id,' . $this->data['siteId'],
            'password' => 'required|min:6',
        ];

        $validator = \Validator::make($this->data, $rules);

        if(!$validator->fails()) {

            $user = new \Veer\Models\User;
            $user->email = array_get($this->data, 'email');
            $user->password = array_get($this->data, 'password');
            $user->sites_id = $this->data['siteId'];

            foreach(!empty($parseForm[1]) ? $parseForm[1] : [] as $key => $value) {                
                if(!empty($value)) { 
                    $user->{$freeFormKeys[$key]} = $value;
                }
            }

            $user->save();
            event('veer.message.center', trans('veeradmin.user.new'));
        }		
    }

    protected function updateOne()
    {
		$fill = array_get($this->data, 'fill', []);
        
		if(empty($fill['sites_ids'])) {
            $fill['sites_id'] = app('veer')->siteId;
        }
        
		if(isset($fill['password']) && empty(trim($fill['password']))) {
            unset($fill['password']);
        }

        $fill['restrict_orders'] = isset($fill['restrict_orders']) ? true : false;
		$fill['newsletter'] = isset($fill['newsletter']) ? true : false;
		$fill['birth'] = parse_form_date(array_get($fill, 'birth', 0));
        
		if($this->action == "add") { // TODO: test
			$rules = [
				'email' => 'required|email|unique:users,email,NULL,id,deleted_at,NULL,sites_id,' . $fill['sites_id'],
				'password' => 'required|min:6',
			];

			$validator = \Validator::make($fill, $rules);

			if($validator->fails()) {
				event('veer.message.center', trans('veeradmin.user.new.error'));
				return false;
			}

			$user = new \Veer\Models\User;
			event('veer.message.center', trans('veeradmin.user.new'));
		} else {
			$user = \Veer\Models\User::find($this->id);
		}		

		$user->fill($fill);
		$user->save();
        
        $this->id = $id = $user->id;

        // TODO: not working, not done:
        
		if(Input::has('addAsAdministrator'))
		{
			$admin = \Veer\Models\UserAdmin::withTrashed()->where('users_id','=',$id)->first();
			if(!is_object($admin))
			{
				\Veer\Models\UserAdmin::create(array('users_id' => $id));
				Event::fire('veer.message.center', \Lang::get('veeradmin.user.admin'));

			}

			else { $admin->restore(); }
		}

		if(Input::has('administrator')) $this->updateOneAdministrator(Input::get('administrator'), $id);

		// images
		if(Input::hasFile('uploadImage'))
		{
			$this->upload('image', 'uploadImage', $id, 'users', 'usr', null);
		}

		$this->attachElements(Input::get('attachImages'), $user, 'images', null);

		$this->detachElements($action, 'removeImage', $user, 'images', null);

                $this->detachElements($action, 'removeAllImages', $user, 'images', null, true);

		// pages
		if(Input::has('attachPages'))
		{
			$pages = $this->parseIds(Input::get('attachPages'));
			$this->associate("pages", $pages, $id, "users_id");
			Event::fire('veer.message.center', \Lang::get('veeradmin.user.page.attach'));

		}

		if(starts_with($action, 'removePage'))
		{
			$p = explode(".", $action);
			$this->associate("pages", array($p[1]), 0, "users_id");
			Event::fire('veer.message.center', \Lang::get('veeradmin.user.page.detach'));

		}

		if(starts_with($action, "deletePage"))
		{
			$p = explode(".", $action);
			$this->deletePage($p[1]);
			Event::fire('veer.message.center', \Lang::get('veeradmin.page.delete'));

			return null;
		}

		// books
		if($action == "addUserbook" || $action == "updateUserbook" )
		{
			foreach(Input::get('userbook', array()) as $book)
			{
				app('veershop')->updateOrNewBook($book);
			}
			Event::fire('veer.message.center', \Lang::get('veeradmin.book.update'));

		}

		if(Input::has('deleteUserbook'))
		{
			$this->deleteBook(head(Input::get('deleteUserbook')));
			Event::fire('veer.message.center', \Lang::get('veeradmin.book.delete'));

			return null;
		}

		// discounts
		if(Input::has('cancelDiscount'))
		{
			\Veer\Models\UserDiscount::where('id','=', head(Input::get('cancelDiscount')))
				->update(array('status' => 'canceled'));
			Event::fire('veer.message.center', \Lang::get('veeradmin.discount.cancel'));

		}

		if(Input::has('attachDiscounts'))
		{
			$discounts = $this->parseIds(Input::get('attachDiscounts'));
			$this->associate("UserDiscount", $discounts, $id, "users_id", "id", "users_id = 0 and status = 'wait'");
			Event::fire('veer.message.center', \Lang::get('veeradmin.discount.attach'));

		}

		// orders & bills
		$this->shopActions();

		// communications
		if(Input::has('sendMessageToUser'))
		{
			(new \Veer\Commands\CommunicationSendCommand(Input::get('communication')))->handle();
			Event::fire('veer.message.center', \Lang::get('veeradmin.user.page.sendmessage'));

		}

		if($action == "add") {
			app('veer')->skipShow = true;
			Input::replace(array('id' => $id));
			return \Redirect::route('admin.show', array('users', 'id' => $id));
		}
    }

    /**
	 * update Administrator
	 * TODO: use ranks to determine who can update whom
	 */
	protected function updateOneAdministrator($administrator, $id)
	{
		$administrator['banned'] = array_get($administrator, 'banned', false) ? true : false;

		if($id == \Auth::id()) array_forget($administrator, 'banned');

		\Veer\Models\UserAdmin::where('users_id','=',$id)
			->update($administrator);
	}
}
