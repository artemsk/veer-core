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

        !\Illuminate\Support\Facades\Request::has('updateBillStatus') ?: $class->setId(\Illuminate\Support\Facades\Request::input('updateBillStatus'))
                ->status(\Illuminate\Support\Facades\Request::input('billUpdate.' . \Illuminate\Support\Facades\Request::input('updateBillStatus') . '.status_id'))
                ->comment(\Illuminate\Support\Facades\Request::input('billUpdate.' . \Illuminate\Support\Facades\Request::input('updateBillStatus')));
        !\Illuminate\Support\Facades\Request::has('updateBillSend') ?: $class->setId(head(\Illuminate\Support\Facades\Request::input('updateBillSend', [])))->markAsSent();
        !\Illuminate\Support\Facades\Request::has('updateBillPaid') ?: $class->setId(head(\Illuminate\Support\Facades\Request::input('updateBillPaid', [])))
                ->markAsPaid(key(\Illuminate\Support\Facades\Request::input('updateBillPaid', [])));
        !\Illuminate\Support\Facades\Request::has('updateBillCancel') ?: $class->setId(head(\Illuminate\Support\Facades\Request::input('updateBillCancel', [])))
                ->markAsCancel(key(\Illuminate\Support\Facades\Request::input('updateBillCancel', [])));
        !\Illuminate\Support\Facades\Request::has('deleteBill') ?: $class->setId(\Illuminate\Support\Facades\Request::input('deleteBill'))->delete();

        if(\Illuminate\Support\Facades\Request::has('addNewBill') && \Illuminate\Support\Facades\Request::has('billCreate.fill.orders_id')) {
            $class->add(\Illuminate\Support\Facades\Request::input('billCreate.fill'), \Illuminate\Support\Facades\Request::input('billCreate.template'));
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

            $sendEmail = \Illuminate\Support\Arr::pull($params, 'send_to_customer');

            \Illuminate\Support\Arr::set($params, 'name', \Veer\Models\OrderStatus::where('id','=', \Illuminate\Support\Arr::get($params, 'status_id'))
                ->pluck('name'));

            \Veer\Models\OrderHistory::create($params + ['order_cache' => '']);

            if(!empty($sendEmail)) { 
                $this->sendEmailOrdersStatus(\Illuminate\Support\Arr::get($params, 'orders_id'), [
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
                ->update(['paid' => $status]);

        return $this;
    }

    public function markAsCancel($status = true)
    {
        \Veer\Models\OrderBill::where('id','=',$this->id)
                ->update(['canceled' => $status]);

        return $this;
    }

    public function delete()
    {
        \Veer\Models\OrderBill::where('id', '=', $this->id)->delete();

        return $this;
    }

    public function add($params, $template = null)
    {
        $order = \Veer\Models\Order::find(\Illuminate\Support\Arr::get($params, 'orders_id'));
        $status = \Veer\Models\OrderStatus::find(\Illuminate\Support\Arr::get($params, 'status_id'));
        $payment = $payment_method = \Illuminate\Support\Arr::get($params, 'payment_method');
        $sendEmail = \Illuminate\Support\Arr::pull($params, 'sendTo', null);

		if(empty($payment)) {
            $payment = \Veer\Models\OrderPayment::find(\Illuminate\Support\Arr::get($params, 'payment_method_id'));
            $payment_method = isset($payment->name) ? $payment->name : $payment_method;
        }

		$content = '';
        if(!empty($template)) {
            /* leave 'view' instead of 'viewx' because we always need (rendered) html representation of the bill */
            $content = view("components.bills." . $template, [
                "order" => $order,
                "status" => $status,
                "payment" => $payment,
                "price" => \Illuminate\Support\Arr::get($params, 'price')
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
