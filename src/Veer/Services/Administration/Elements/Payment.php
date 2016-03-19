<?php

namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class Payment {

    use DeleteTrait;
    
    protected $entity;
    protected $type = 'payment';

    public function __construct()
    {
        \Eloquent::unguard();
    }

    public static function request()
    {
        $class = new static;

        !Input::has('deletePaymentMethod') ?: $class->delete(Input::get('deletePaymentMethod'));
        !Input::has('updatePaymentMethod') ?: $class->update(Input::get('updatePaymentMethod'))
                ->with(Input::get('payment.fill'));
        !Input::has('addPaymentMethod') ?: $class->add()->with(Input::get('payment.fill'));
    }

    public function delete($id)
    {
        \Veer\Models\Order::where('payment_method_id', '=', $id)
                ->update(['payment_method_id' => 0]);

        \Veer\Models\OrderBill::where('payment_method_id', '=', $id)
                ->update(['payment_method_id' => 0]);

        \Veer\Models\OrderPayment::destroy($id);

        event('veer.message.center', trans('veeradmin.payment.delete') .
                " " . $this->restore_link('OrderPayment', $id));        

        return $this;
    }

    public function update($id)
    {
        $p = \Veer\Models\OrderPayment::find($id);

        if(is_object($p)) {
            $this->entity = $p;
            event('veer.message.center', trans('veeradmin.payment.update'));
        } else {
            event('veer.message.center', trans('veeradmin.payment.error'));
        }

        return $this;
    }

    public function add()
    {
        $this->entity = new \Veer\Models\OrderPayment;

		event('veer.message.center', trans('veeradmin.payment.new'));

        return $this;
    }

    protected function with($data)
    {
        if(!is_object($this->entity)) { return $this; }
        
        $func_name = array_get($data, 'func_name');
        $classFullName = starts_with($func_name, "\\") ? $func_name : "\\Veer\\Components\\Ecommerce\\" . $func_name;

		if(!empty($func_name) && !class_exists($classFullName)) {
			event('veer.message.center', trans('veeradmin.payment.error'));
            return $this;
		}

		$data['commission'] = strtr(array_get($data, 'commission'), ["%" => ""]);
		$data['discount_price'] = strtr(array_get($data, 'discount_price'), ["%" => ""]);
		$data['enable'] = isset($data['enable']) ? true : false;
		$data['discount_enable'] = isset($data['discount_enable']) ? true : false;

		$this->entity->fill($data);
		$this->entity->save();

        return $this;
    }

}
