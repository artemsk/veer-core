<?php

namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class Status {

    use DeleteTrait;
    
    protected $type = 'status';

    public function __construct()
    {
        \Eloquent::unguard();
    }

    public static function request()
    {
        $class = new static;

        !Input::has('deleteStatus') ?: $class->delete(Input::get('deleteStatus'));

        if(Input::has('addStatus')) {
            foreach(Input::get('InName') as $key => $value) {
                if(!empty($value)) {
                    $class->addOrUpdateFromRequest(new \Veer\Models\OrderStatus, $key, null, $key, '#000');
                    $_added = true;
                }
            }
            !isset($_added) ?: event('veer.message.center', trans('veeradmin.status.new'));
        }

        if(Input::has('updateGlobalStatus')) {
            $o = \Veer\Models\OrderStatus::find(Input::get('updateGlobalStatus'));
            if(is_object($o)) {
                $class->addOrUpdateFromRequest($o, $o->id, $o->name, $o->manual_order, $o->color);
                event('veer.message.center', trans('veeradmin.status.update'));
            }
        }
    }

    protected function addOrUpdateFromRequest($o, $id, $name, $manual_order, $color)
    {
        $this->addOrUpdateGlobalStatus($o, [
            'name' => Input::get('InName.' . $id, $name),
            'manual_order' => Input::get('InOrder.' . $id, $manual_order),
            'color' => Input::get('InColor.' . $id, $color),
            'flag' => Input::get('InFlag.' . $id)
        ]);
    }
    
	/**
	 * delete Status
	 */
	public function delete($id)
	{
		\Veer\Models\Order::where('status_id', '=', $id)
			->update(['status_id' => 0]);

		\Veer\Models\OrderBill::where('status_id','=',$id)
			->update(['status_id' => 0]);

		\Veer\Models\OrderHistory::where('status_id','=',$id)
			->update(['status_id' => 0]);

		\Veer\Models\OrderStatus::destroy($id);

        event('veer.message.center', trans('veeradmin.status.delete').
				" " . $this->restore_link('OrderStatus', $id));

        return $this;
	}

    public function add($data)
    {
        $data += ['manual_order' => 99999, 'color' => '#000', 'flag' => null];

        if(!empty($data['name'])) {
            $this->addOrUpdateGlobalStatus(new \Veer\Models\OrderStatus, $data);
            event('veer.message.center', trans('veeradmin.status.new'));
        }

        return $this;
    }
    
    public function update($id, $data)
    {
        $o = \Veer\Models\OrderStatus::find($id);

        if(is_object($o)) {
            $data += $o->toArray();

            $this->addOrUpdateGlobalStatus($o, [
                'name' => $data['name'],
                'manual_order' => $data['manual_order'],
                'color' => $data['color'],
                'flag' => $data['flag']
            ]);
        }

        return $this;			
    }

	/**
	 * add or update global status (query)
	 * @param type $o
	 * @param type $data
	 */
	protected function addOrUpdateGlobalStatus($o, $data)
	{
		$o->name = $data['name'];
		$o->manual_order = $data['manual_order'];
		$o->color = $data['color'];

		$flags = array('flag_first' => 0,'flag_unreg' => 0, 'flag_error' => 0,
				'flag_payment' => 0, 'flag_delivery' => 0, 'flag_close' => 0,
				'secret' => 0);

        if(!empty($data['flag'])) { $flags[$data['flag']] = 1; }

		$o->fill($flags);
		$o->save();
	}
}
