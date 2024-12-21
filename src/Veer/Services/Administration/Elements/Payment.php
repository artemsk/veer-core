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

        !\Illuminate\Support\Facades\Request::has('deletePaymentMethod') ?: $class->delete(\Illuminate\Support\Facades\Request::input('deletePaymentMethod'));
        !\Illuminate\Support\Facades\Request::has('updatePaymentMethod') ?: $class->update(\Illuminate\Support\Facades\Request::input('updatePaymentMethod'))
                ->with(\Illuminate\Support\Facades\Request::input('payment.fill'));
        !\Illuminate\Support\Facades\Request::has('addPaymentMethod') ?: $class->add()->with(\Illuminate\Support\Facades\Request::input('payment.fill'));
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
        
        $func_name = \Illuminate\Support\Arr::get($data, 'func_name');
        $classFullName = \Illuminate\Support\Str::startsWith($func_name, "\\") ? $func_name : "\\Veer\\Components\\Ecommerce\\" . $func_name;

		if(!empty($func_name) && !class_exists($classFullName)) {
			event('veer.message.center', trans('veeradmin.payment.error'));
            return $this;
		}

		$data['commission'] = strtr(\Illuminate\Support\Arr::get($data, 'commission'), ["%" => ""]);
		$data['discount_price'] = strtr(\Illuminate\Support\Arr::get($data, 'discount_price'), ["%" => ""]);
		$data['enable'] = isset($data['enable']) ? true : false;
		$data['discount_enable'] = isset($data['discount_enable']) ? true : false;

		$this->entity->fill($data);
		$this->entity->save();

        return $this;
    }

}
