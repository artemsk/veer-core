<?php

namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class Shipping {

    use DeleteTrait;
    
    protected $entity;
    protected $type = 'shipping';

    public function __construct()
    {
        \Eloquent::unguard();
    }

    public static function request()
    {
        $class = new static;

        !Input::has('deleteShippingMethod') ?: $class->delete(Input::get('deleteShippingMethod'));
        !Input::has('updateShippingMethod') ?: $class->update(Input::get('updateShippingMethod'))
                ->with(Input::get('shipping.fill'));
        !Input::has('addShippingMethod') ?: $class->add()->with(Input::get('shipping.fill'));
    }

    public function delete($id)
    {
        \Veer\Models\Order::where('delivery_method_id','=',$id)
			->update(['delivery_method_id' => 0]);

		\Veer\Models\OrderShipping::destroy($id);

        event('veer.message.center', trans('veeradmin.shipping.delete') .
				" " . $this->restore_link('OrderShipping', $id));

        return $this;
    }

    public function update($id)
    {
        $p = \Veer\Models\OrderShipping::find($id);

        if(is_object($p)) {
            $this->entity = $p;
            event('veer.message.center', trans('veeradmin.shipping.update'));
        } else {
            event('veer.message.center', trans('veeradmin.shipping.error'));
        }

        return $this;
    }

    public function add()
    {
        $this->entity = new \Veer\Models\OrderShipping;

		event('veer.message.center', trans('veeradmin.shipping.new'));

        return $this;
    }

    protected function with($data)
    {
        if(!is_object($this->entity)) { return $this; }

        $func_name = array_get($data, 'func_name');
        $classFullName = starts_with($func_name, "\\") ? $func_name : "\\Veer\\Components\\Ecommerce\\" . $func_name;

		if(!empty($func_name) && !class_exists($classFullName)) {
			event('veer.message.center', trans('veeradmin.shipping.error'));
            return $this;
		}

		$data['discount_price'] = strtr(array_get($data, 'discount_price'), ["%" => ""]);
		$data['enable'] = isset($data['enable']) ? true : false;
		$data['discount_enable'] = isset($data['discount_enable']) ? true : false;

		if(array_has($data, 'address')) {
			$addresses = preg_split('/[\n\r]+/', array_get($data, 'address')); // @todo redo
			foreach($addresses as $k => $address) {
				$parts = explode("|", $address);
				$parts = array_filter($parts, function($value) { if(!empty($value)) return $value; });
				$addresses[$k] = $parts;
			}

			$data['address'] = json_encode($addresses);
		}

		$this->entity->fill($data);
		$this->entity->save();

        return $this;
    }
    
}