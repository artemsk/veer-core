<?php

namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class Order {

    use DeleteTrait, EcommerceTrait;

    protected $id;
    protected $order;
    protected $order_content;
    protected $discount;
    
    protected $action;
    protected $type = 'order';

    protected $triggers = [
        'pin' => ['unpin', 'pin'],
        'updatePaymentHold' => ['holdPayment', 'unholdPayment'],
        'updatePaymentDone' => ['donePayment', 'undonePayment'],
        'updateShippingHold' => ['holdShipping', 'unholdShipping'],
        'updateOrderClose' => ['close', 'open'],
        'updateOrderHide' => ['hide', 'unhide'],
        'updateOrderArchive' => ['archive', 'unarchive'],
    ];

    public function __construct()
    {
        \Eloquent::unguard();
    }

    public static function request()
    {
        self::request_actions();
        Bill::request();

        if(Input::has('id')) {
            return (new static)->one();
        }
    }

    protected function prepareData($fill)
    {
        if(empty($fill['sites_id'])) { $fill['sites_id'] = app('veer')->siteId; }

		if(empty($fill['users_id']) && $this->action != 'add') {
            $fill['users_id'] = \Auth::id();
        }

        foreach(['free', 'close', 'hidden', 'archive', 'delivery_free', 'delivery_hold', 'payment_hold', 'payment_done'] as $key) {
            $fill[$key] = isset($fill[$key]) ? 1 : 0;
        }

        if($fill['close']) { $fill['close_time'] = now(); }
		$fill['progress'] = isset($fill['progress']) ? strtr($fill['progress'], ["%" => ""]) : 5;
		$fill['delivery_plan'] = !empty($fill['delivery_plan']) ? parse_form_date($fill['delivery_plan']) : null;
		$fill['delivery_real'] = !empty($fill['delivery_real']) ? parse_form_date($fill['delivery_real']) : null;

        $fill += [
            'cluster_oid' => null, 'cluster' => null, 'delivery_method_id' => $this->order->delivery_method_id,
            'payment_method_id' => $this->order->payment_method_id, 'status_id' => $this->order->status_id,
            'userbook_id' => $this->order->userbook_id
        ];
        
		if($this->order->cluster_oid != $fill['cluster_oid'] || $this->order->cluster != $fill['cluster']) {
			$existingOrders = \Veer\Models\Order::where('sites_id', '=', $fill['sites_id'])
				->where('cluster', '=', $fill['cluster'])->where('cluster_oid', '=', $fill['cluster_oid'])->first();

			// we cannot update cluster ids if they already exist
			if(is_object($existingOrders) || empty($fill['cluster_oid'])) {
                array_forget($fill, ['cluster_oid', 'cluster']);
			}
		}

		if($this->order->delivery_method_id != $fill['delivery_method_id'] && empty($fill['delivery_method'])) {
			$fill['delivery_method'] = \Veer\Models\OrderShipping::where('id', '=', $fill['delivery_method_id'])->pluck('name');
		}

        if($this->order->payment_method_id != $fill['payment_method_id'] && empty($fill['payment_method'])) {
			$fill['payment_method'] = \Veer\Models\OrderPayment::where('id', '=', $fill['payment_method_id'])->pluck('name');
		}

        return $fill;
    }

    public function delete()
    {
        if (!empty($this->id) && $this->deleteOrder($this->id) !== false) {
            event('veer.message.center', trans('veeradmin.order.delete') .
				" " . $this->restore_link('order', $this->id));
        }

        return $this;
    }

    public function userbookToOrder($userbook, $_save = true)
    {
        $getBook = !is_object($userbook) ? \Veer\Models\UserBook::find($userbook) : $userbook;
        if(is_object($getBook)) {
            $this->order->userbook_id = $getBook->id;
            $this->order->country = $getBook->country;
            $this->order->city = $getBook->city;
            $this->order->address = trim($getBook->postcode . ' ' . $getBook->address);
            
            if($_save) { $this->order->save(); }
        }

        return $this;
    }

    public function userbook($userbooks, $_save = true)
    {
        foreach(isset($userbooks['fill']) ? [$userbooks] : $userbooks as $book) {
            $newBook = app('veershop')->updateOrNewBook($book);
            if(isset($newBook) && is_object($newBook)) {
                $this->userbookToOrder($newBook, $_save);
            }
        }

        return $this;
    }

    public function content($data, $content_id = null)
    {
        if(!empty($content_id)) {
            $content = \Veer\Models\OrderProduct::find($content_id);
            if(is_object($content)) {
                $content = app('veershop')->editOrderContent($content, $data, $this->order);
                $content->save();
            }
        } else {
            app('veershop')->attachOrderContent($data, $this->order);
        }

        return $this;
    }

    public function item($product, $qty = 1, $price = null, $attributes = [], $forceSpecial = false)
    {
        if(is_numeric($product) && !$forceSpecial) {
            $data = ':' . $product . ',' . $qty . ',' . $attributes; // existing product
        } else {
            $data = $product . ':' . $price . ':' . $qty; // special item
        }

        return $this->content($data);
    }

    public function deleteItem($id)
    {
        \Veer\Models\OrderProduct::destroy($id);

        return $this;
    }

    public function deleteHistory($id, $_save = true)
    {
        \Veer\Models\OrderHistory::where('id', '=', $id)->forceDelete();

        $previous = \Veer\Models\OrderHistory::where('orders_id','=', $this->order->id)
                ->orderBy('id','desc')->first();

        if(is_object($previous)) {
            $this->order->status_id = $previous->status_id;
        }

        if($_save) { $this->order->save(); }
        
        event('veer.message.center', trans('veeradmin.order.history.delete'));

        return $this;
    }

    public function deleteStatus($id, $_save = true)
    {
        return $this->deleteHistory($id, $_save);
    }

    protected function addOrder($order, $userbook = [], $order_content = null, $options = [])
    {
        $options += ['_pretend' => false, '_skipPrepare' => false, '_allowSkipContent' => true, '_skipObjectCreate' => false];
        
        if(!$options['_skipObjectCreate']) { $this->order = new \Veer\Models\Order; }

        $this->order->fill($options['_skipPrepare'] ? $order : $this->prepareData($order));

        $validator = \Validator::make($this->order->toArray(), [
            'email' => 'required_without:users_id|email',
            'users_id' => 'required_without:email',
        ]);

        $validator_content = \Validator::make([
            'content' => $order_content
        ], [
            'content' => $options['_allowSkipContent'] ? '' : 'required'
        ]);

        if($validator->fails() || $validator_content->fails()) {
            event('veer.message.center', trans('veeradmin.order.new.error'));
            return false;
        }

        list($this->order, $this->discount) = 
                app('veershop')->addNewOrder($this->order, $this->order->users_id, $userbook, $options['_pretend']);

        return $this;
    }

    public function add($order, $userbook = [], $order_content = null, $options = [], $_sendEmail = false)
    {
        $result = $this->addOrder($order, $userbook, $order_content, $options);
        if($result === false) { return false; }

        $this->id = $this->order->id;

        if(!empty($order_content)) { $this->content($order_content); }

		$this->order = app('veershop')->sumOrderPricesAndWeight($this->order);
        $this->order = app('veershop')->recalculateOrderDelivery($this->order);
        $this->order = app('veershop')->recalculateOrderPayment($this->order);		

        $this->order->price = ($this->order->delivery_free == true) ? $this->order->content_price :
                ($this->order->content_price + $this->order->delivery_price);

        $this->order->save();
        $this->status(['status_id' => $this->order->status_id]);
        
        if($this->order->userdiscount_id > 0 && !empty($this->discount)) {
			app('veershop')->changeUserDiscountStatus($this->discount);
		}

        if($_sendEmail) { $this->sendEmail(); }

        return $this;
    }

    protected function one()
    {
        $this->id = Input::get('id');
        $this->action = Input::get('action');
		$this->order = \Veer\Models\Order::find($this->id);
		if(!is_object($this->order)) { $this->order = new \Veer\Models\Order; }

        $fill = Input::has('fill') ? $this->prepareData(Input::get('fill')) : null;
        
		if($this->action == "delete") {
            $this->delete();
			app('veer')->skipShow = true;
            return \Redirect::route('admin.show', ['orders']);
		}

        if($this->order->status_id != $fill['status_id']) { $addStatusToHistory = true; }
		if($this->order->userbook_id != $fill['userbook_id']) { $this->userbookToOrder($fill['userbook_id'], false); }

		$this->order->fill($fill);

		if($this->action == "add") {
            $result = $this->addOrder($fill, Input::get('userbook.0', []), Input::get('attachContent'), [
                '_skipObjectCreate' => true, '_skipPrepare' => true, '_allowSkipContent' => false
            ]);
            if($result === false) { return false; }            
			$addStatusToHistory = true;
            $this->id = $this->order->id;
		}

        $this->goThroughEverything();
        
		$this->order->save();
        $this->id = $this->order->id;

		if(isset($addStatusToHistory)) { $this->status(['status_id' => $this->order->status_id]); }

		if($this->action == "add" && $this->order->userdiscount_id > 0 && !empty($this->discount)) {
			app('veershop')->changeUserDiscountStatus($this->discount);
		}

		if(Input::has('sendMessageToUser')) {
			(new \Veer\Commands\CommunicationSendCommand(Input::get('communication')))->handle();
			event('veer.message.center', trans('veeradmin.user.page.sendmessage'));
		}

		if($this->action == "add") {
			$this->sendEmail();
			app('veer')->skipShow = true;
			Input::replace(['id' => $this->order->id]);
			return \Redirect::route('admin.show', ['orders', 'id' => $this->order->id]);
		}
    }

    protected function goThroughEverything()
    {
		!($this->action == "addUserbook" || $this->action == "updateUserbook") ?: $this->userbook(Input::get('userbook', []), false);
		!Input::has('editContent') ?: $this->content(
                Input::get('ordersProducts.' . Input::get('editContent') . '.fill', []), Input::get('editContent'));
		!Input::has('attachContent') ?: $this->content(Input::get('attachContent'));
		!Input::has('deleteContent') ?: $this->deleteItem(Input::get('deleteContent'));
		
		// sums price & weight
		$this->order = app('veershop')->sumOrderPricesAndWeight($this->order);

		// recalculate delivery
		if($this->action == "recalculate" || $this->action == "add") {
			$this->order = app('veershop')->recalculateOrderDelivery($this->order);
			$this->order = app('veershop')->recalculateOrderPayment($this->order);
		}

        $this->order->price = ($this->order->delivery_free == true) ? $this->order->content_price :
                ($this->order->content_price + $this->order->delivery_price);

		!(Input::has('deleteHistory')) ?: $this->deleteHistory(Input::get('deleteHistory'), false);
    }

    /**
	 * send email when creating new order
	 */
	public function sendEmail()
	{
		$data = $this->order->toArray();
		$data['orders_id'] = app('veershop')->getOrderId($this->order->cluster, $this->order->cluster_oid);
		$data['link'] = $this->order->site->url . "/order/" . $this->order->id;

		$subject = trans('veeradmin.emails.order.new.subject', ['oid' => $data['orders_id']]);

		if(!empty($this->order->email)) {
			(new \Veer\Commands\SendEmailCommand('emails.order-new',
				$data, $subject, $this->order->email, null, $this->order->sites_id))->handle();
		}

        return $this;
	}

    public static function request_actions()
    {
        $class = new static;

        foreach($class->triggers as $trigger => $funcs) {
            $class->id = head(Input::get($trigger, []));
            key(Input::get($trigger, [])) == 1 ? $class->{$funcs[0]}() : $class->{$funcs[1]}();
        }

        if(Input::has('updateOrderStatus')) {
            $class->id = Input::get('updateOrderStatus');
            $class->status(Input::get('history.' . $class->id));
        }
    }

    public function setId($id)
    {
        $this->id = $id;
        
        return $this;
    }

    public function getOrder($id)
    {
        $this->id = $id;
        $this->order = \Veer\Models\Order::find($id);
        
        return $this;
    }

    protected function on_off($key, $value)
    {
        \Veer\Models\Order::where('id', '=', $this->id)
            ->update([$key => $value]);

        return $this;
    }

    public function pin()
    {
        return $this->on_off('pin', 1);
    }

    public function unpin()
    {
        return $this->on_off('pin', 0);
    }

    public function holdPayment()
    {
        return $this->on_off('payment_hold', 1);
    }

    public function unholdPayment()
    {
        return $this->on_off('payment_hold', 0);
    }

    public function donePayment()
    {
        return $this->on_off('payment_done', 1);
    }

    public function undonePayment()
    {
        return $this->on_off('payment_done', 0);
    }

    public function holdShipping()
    {
        return $this->on_off('delivery_hold', 1);
    }

    public function unholdShipping()
    {
        return $this->on_off('delivery_hold', 0);
    }

    public function close()
    {
        \Veer\Models\Order::where('id', '=', $this->id)
            ->update(['close' => 1, "close_time" => now()]);
    }

    public function open()
    {
        \Veer\Models\Order::where('id', '=', $this->id)
            ->update(['close' => 0]);
    }

    public function hide()
    {
        return $this->on_off('hidden', 1);
    }

    public function unhide()
    {
        return $this->on_off('hidden', 0);
    }

    public function archive()
    {
        return $this->on_off('archive', 1);
    }

    public function unarchive()
    {
        return $this->on_off('archive', 0);
    }

    public function status($history)
    {
        array_set($history, 'orders_id', $this->id);
        array_set($history, 'name',
            \Veer\Models\OrderStatus::where('id','=', array_get($history, 'status_id'))
                ->pluck('name')
            );
        if(empty($history['name'])) { $history['name'] = '[?]'; }
        
        $update = ['status_id' => array_get($history, 'status_id')];
        $progress = array_pull($history, 'progress');
        if(!empty($progress)) { $update['progress'] = $progress; }

        $sendEmail = array_pull($history, 'send_to_customer');

        \Veer\Models\OrderHistory::create($history);
        \Veer\Models\Order::where('id' ,'=', $this->id)
            ->update($update);

        if(!empty($sendEmail)) { 
            $this->sendEmailOrdersStatus($this->id, ['history' => $history]);
        }

        return $this;
    }

    public function mailStatus($history)
    {
        $this->sendEmailOrdersStatus($this->id, ['history' => $history]);

        return $this;
    }
}
