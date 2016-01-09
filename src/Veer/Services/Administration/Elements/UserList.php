<?php

namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class UserList {

    protected $delete;
    protected $action;
    protected $products;
    protected $pages;
    protected $data = [];

    public function __construct()
    {
        \Eloquent::unguard();
        $this->delete = head(Input::get('deleteList', []));
        $this->action = Input::get('action');
        $this->products = trim(Input::get('products'));
        $this->pages = trim(Input::get('pages'));
        $this->data = Input::all();
    }

    public function run()
    {
        if(!empty($this->delete)) {
            return $this->deleteList();
        }

        if($this->action == 'addList' && (!empty($this->products) || !empty($this->pages))) {
            $this->addList();
        }
    }

    public function addList()
    {
        $fill = array_get($this->data, 'fill', []);

        $fill += [
            'users_id' => \Auth::id(),
            'session_id' => \Session::getId(),
            'name' => '[basket]'
        ];

        if(!empty($this->data['checkboxes']['basket'])) {
            $fill['name'] = '[basket]';
        }

        $p = preg_split('/[\n\r]+/', $this->products); // @todo redo
        if(is_array($p)) { $this->saveAndAttachLists($p, '\\'.elements('product'), $fill); }

        $pg = preg_split('/[\n\r]+/', $this->pages); // @todo redo
        if(is_array($pg)) { $this->saveAndAttachLists($pg, '\\'.elements('page'), $fill); }

        event('veer.message.center', trans('veeradmin.list.new'));
    }

    /**
	 * Save and Attach Lists
	 * @param type $p
	 * @param type $model
	 * @param type $fill
	 */
	protected function saveAndAttachLists($p, $model, $fill)
	{
		foreach($p as $element) {
            
			$parseElements = explode(":", $element);

			$id = array_get($parseElements, 0);
			$qty = array_get($parseElements, 1, 1);
			$attrStr = array_get($parseElements, 2);

			$attrs = explode(",", $attrStr);

			$item = $model::find(trim($id));

			if(is_object($item) && $id > 0) {
				$cart = new \Veer\Models\UserList;
				$cart->fill($fill);
				$cart->quantity = !empty($qty) ? $qty : 1;

				if(is_array($attrs) && !empty($attrs)) {
					$cart->attributes = json_encode($attrs);
				}

				$cart->save();
				$item->userlists()->save($cart);
			}
		}
	}

	/**
	 * delete List
	 */
	protected function deleteList()
	{
		\Veer\Models\UserList::where('id', '=', $this->delete)->delete();
        event('veer.message.center', trans('veeradmin.list.delete'));
	}

}