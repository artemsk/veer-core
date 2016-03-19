<?php

namespace Veer\Services\Administration\Elements;

trait EcommerceTrait {

    /**
	 * send emails when updating order status
	 */
	protected function sendEmailOrdersStatus($orderId, $options = array())
	{
		$data = \Veer\Models\Order::where('id','=',$orderId)
					->select('sites_id', 'cluster', 'cluster_oid', 'name', 'email')->first();

		$data_array = $data->toArray();

		$data_array['orders_id'] = app('veershop')->getOrderId($data->cluster, $data->cluster_oid);
		$data_array['status'] = array_get($options, 'history');
		$data_array['link'] = $data->site->url . "/order/" . $orderId;

		$subject = \Lang::get('veeradmin.emails.order.subject', array('oid' => $data_array['orders_id']));

		if(!empty($data->email)) { (new \Veer\Commands\SendEmailCommand('emails.order-status',
			$data_array, $subject, $data->email, null, $data->sites_id))->handle(); }
	}
    
}
