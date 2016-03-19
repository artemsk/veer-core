<?php

namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class Discount {

    use DeleteTrait;
    
    protected $type = 'discount';
    protected $secret_code;

    public function __construct()
    {
        \Eloquent::unguard();
    }

    public static function request()
    {
        $class = new static;

        !Input::has('updateGlobalDiscounts') ?: $class->add(Input::get('discount', []));
        !Input::has('deleteDiscount') ?: $class->delete(Input::get('deleteDiscount'));
    }

    public function generateCode()
    {
        return $this->setCode(str_random(18));
    }

    public function setCode($code)
    {
        $this->secret_code = $code;

        return $this;
    }

    public function getCode()
    {
        return $this->secret_code;
    }

    public function add($discounts)
    {
        foreach($discounts as $key => $discount) {            
            $fill = isset($discount['fill']) ? $discount['fill'] : $discount;
            $fill['discount'] = strtr(array_get($fill, 'discount'), ['%' => '']);
            $fill['expires'] = isset($fill['expires']) ? true : false;

            if(($key == "new" || !is_numeric($key)) && $fill['discount'] > 0 && array_get($fill, 'sites_id') > 0) {
                $d = new \Veer\Models\UserDiscount;
            } else { 
                $d = \Veer\Models\UserDiscount::find($key);
            }

            if(is_object($d)) {
                $d->fill($fill);
                $d->save();
            }
        }

        event('veer.message.center', trans('veeradmin.discount.update'));

        return $this;
    }

    public function delete($id)
    {
        \Veer\Models\UserDiscount::destroy($id);

        event('veer.message.center', trans('veeradmin.discount.delete') .
            " " . $this->restore_link('UserDiscount', $id));

        return $this;
    }
}
