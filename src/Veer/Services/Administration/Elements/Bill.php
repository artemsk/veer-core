<?php

namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class Bill {

    use EcommerceTrait;
    
    protected $id;
    protected $type = 'bill';

    public function __construct()
    {
        \Eloquent::unguard();
    }

    public static function request()
    {
        $class = new static;

        !Input::has('updateBillStatus') ?: $class->setId(Input::get('updateBillStatus'))
                ->status(Input::get('billUpdate.' . Input::get('updateBillStatus') . '.status_id'))
                ->comment(Input::get('billUpdate.' . Input::get('updateBillStatus')));
        !Input::has('updateBillSend') ?: $class->setId(head(Input::get('updateBillSend', [])))->markAsSent();
        !Input::has('updateBillPaid') ?: $class->setId(head(Input::get('updateBillPaid', [])))
                ->markAsPaid(key(Input::get('updateBillPaid', [])));
        !Input::has('updateBillCancel') ?: $class->setId(head(Input::get('updateBillCancel', [])))
                ->markAsCancel(key(Input::get('updateBillCancel', [])));
        !Input::has('deleteBill') ?: $class->setId(Input::get('deleteBill'))->delete();

        if(Input::has('addNewBill') && Input::has('billCreate.fill.orders_id')) {
            $class->add(Input::get('billCreate.fill'), Input::get('billCreate.template'));
        }
    }

    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    public function status($status_id)
    {
        if(!empty($this->id) && !empty($status_id)) {
            \Veer\Models\OrderBill::where('id', '=', $this->id)
                ->update(['status_id' => $status_id]);
        }

        return $this;
    }

    public function comment($params)
    {
        if(!empty($params['comments'])) {

            $sendEmail = array_pull($params, 'send_to_customer');

            array_set($params, 'name',
                \Veer\Models\OrderStatus::where('id','=', array_get($params, 'status_id'))
                    ->pluck('name')
            );

            \Veer\Models\OrderHistory::create($params + ['order_cache' => '']);

            if(!empty($sendEmail)) { 
                $this->sendEmailOrdersStatus(array_get($params, 'orders_id'), [
                    "history" => $params
                ]);
            }
        }

        return $this;
    }

    public function markAsSent()
    {
        \Veer\Models\OrderBill::where('id', '=', $this->id)
            ->update(['sent' => true]);

        $b = \Veer\Models\OrderBill::find($this->id);

        if(is_object($b)) { 
            $this->sendEmailBillCreate($b, $b->order);
        }

        return $this;
    }

	protected function sendEmailBillCreate($b, $order)
	{
		$data['orders_id'] = app('veershop')->getOrderId($order->cluster, $order->cluster_oid);
		$data['name'] = $order->name;
		$data['bills_id'] = $b->id;
		$data['link'] = $order->site->url . "/order/bills/" . $b->id . "/" . $b->link;

		$subject = \Lang::get('veeradmin.emails.bill.new.subject', ['oid' => $data['orders_id']]);

		(new \Veer\Commands\SendEmailCommand('emails.bill-create',
			$data, $subject, $order->email, null, $order->sites_id))->handle();
	}

    public function markAsPaid($status = true)
    {
        \Veer\Models\OrderBill::where('id', '=', $this->id)
                ->update(array('paid' => $status));

        return $this;
    }

    public function markAsCancel($status = true)
    {
        \Veer\Models\OrderBill::where('id','=',$this->id)
                ->update(array('canceled' => $status));

        return $this;
    }

    public function delete()
    {
        \Veer\Models\OrderBill::where('id', '=', $this->id)->delete();

        return $this;
    }

    public function add($params, $template = null)
    {
        $order = \Veer\Models\Order::find(array_get($params, 'orders_id'));
        $status = \Veer\Models\OrderStatus::find(array_get($params, 'status_id'));
        $payment = $payment_method = array_get($params, 'payment_method');
        $sendEmail = array_pull($params, 'sendTo', null);

		if(empty($payment)) {
            $payment = \Veer\Models\OrderPayment::find(array_get($params, 'payment_method_id'));
            $payment_method = isset($payment->name) ? $payment->name : $payment_method;
        }

		$content = '';
        if(!empty($template)) {
            /* leave 'view' instead of 'viewx' because we always need (rendered) html representation of the bill */
            $content = view("components.bills." . $template, [
                "order" => $order,
                "status" => $status,
                "payment" => $payment,
                "price" => array_get($params, 'price')
            ])->render();
        }

        $b = new \Veer\Models\OrderBill;
        $b->fill($params);
        $b->users_id = isset($order->users_id) ? $order->users_id : 0;
        $b->payment_method = $payment_method;
        $b->content = $content;
        if(!empty($sendEmail)) $b->sent = true;
        $b->save();

		if(!empty($sendEmail) && is_object($order)) {
            $this->sendEmailBillCreate($b, $order);
        }

        return $this;
    }
}
