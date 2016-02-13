<?php

namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class UserList {

    use DeleteTrait;
    
    protected $action;
    protected $type = 'userlist';

    public function __construct()
    {
        \Eloquent::unguard();        
    }

    public static function request()
    {
        $class = new static;
        $class->action = Input::get('action');

        !Input::has('deleteList') ?: $class->delete(Input::get('deleteList'));
        $class->action != 'addList' ?: $class->addList(Input::all());
    }

    public function delete($id)
    {
        $id = is_array($id) ? head($id) : $id;
        if(!empty($id) && $this->deleteList($id)) {
            event('veer.message.center', trans('veeradmin.book.delete') .
				" " .$this->restore_link('UserBook', $id));
        }

        return $this;
    }

    public function add($id, $qty = 1, $to = 'product', $list = '[basket]', $params = [], $returnId = true)
    {
        $params += [
            'sites_id' => app('veer')->siteId,
            'users_id' => \Auth::id(),
            'session_id' => \Session::getId(),
            'attrs' => '',
            'name' => $list,
            'quantity' => $qty
        ];

        $model = '\\' . elements($to);
        $o = new \Veer\Models\UserList;
        $o->fill(array_except($params, 'attrs'));

        if(is_array($params['attrs']) && !empty($params['attrs'])) {
            $o->attributes = json_encode($params['attrs']);
        }

		$o->save();

        $item = $model::find(trim($id));
        if(is_object($item)) { $item->userlists()->save($o); } 

        return $returnId ? $o->id : $this;
    }

    public function addList($data)
    {
        $products = trim(array_get($data, 'products'));
        $pages = trim(array_get($data, 'pages'));

        $fill = array_get($data, 'fill', []);
        $fill += [
            'users_id' => \Auth::id(),
            'session_id' => \Session::getId(),
            'name' => '[basket]'
        ];
        if(!empty($data['checkboxes']['basket'])) {
            $fill['name'] = '[basket]';
        }

        preg_match_all("/^(.*)$/m", trim($products), $p);
        if(isset($p[1]) && is_array($p[1])) { $this->saveAndAttachLists($p[1], 'product', $fill); }

        preg_match_all("/^(.*)$/m", trim($pages), $pg);
        if(isset($pg[1]) && is_array($pg[1])) { $this->saveAndAttachLists($pg[1], 'page', $fill); }

        event('veer.message.center', trans('veeradmin.list.new'));
        return $this;
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

            $this->add($id, !empty($qty) ? $qty : 1, $model, $fill['name'], $fill + ['attrs' => $attrs]);
		}
	}	

}
