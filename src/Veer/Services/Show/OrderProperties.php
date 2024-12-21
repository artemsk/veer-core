<?php namespace Veer\Services\Show;

use Illuminate\Support\Facades\Input;

class OrderProperties {
	
	use \Veer\Services\Traits\HelperTraits, \Veer\Services\Traits\SortingTraits;
	
	/**
	 * show Bills
	 */
	public function getBills($filters = [], $orderBy = ['created_at', 'desc'], $paginateItems = 50)
	{
		$orderBy = $this->replaceSortingBy($orderBy);
		
		$items = $this->filterBills(key($filters), $filters);
		
		if(empty(app('veer')->loadedComponents['billsTypes'])) $this->getExistingBillTemplates();

		return $items->orderBy($orderBy[0], $orderBy[1])
			->with(
				'order', 'user', 'status', 'payment'
			)->paginate($paginateItems);
	}
	
	/* filter bills */
	protected function filterBills($type, $filters)
	{
		if(in_array($type, ['order', 'user', 'status', 'payment']) || empty($type))
		{
			return $this->buildFilterWithElementsQuery($filters, \Veer\Models\OrderBill::class,
				in_array($type, ['payment', 'status']) ? false : true,
				$type == 'payment' ? 'payment_method_id' : null
			);
		}
		
		return \Veer\Models\OrderBill::where($type, '=', \Illuminate\Support\Arr::get($filters, $type, 0));
	}
	
	/**
	 * show Discounts
	 */
	public function getDiscounts( $filters = [], $paginateItems = 50 )
	{
		if(key($filters) == "status") $items = \Veer\Models\UserDiscount::where('status', '=', head($filters));

		else $items = $this->buildFilterWithElementsQuery($filters, \Veer\Models\UserDiscount::class);
			
		return $items->orderBy('created_at', 'desc')
			->with('user', 'orders')
			->with($this->loadSiteTitle())
			->paginate($paginateItems);
	}	
	
	/**
	 * show Payment Methods
	 */
	public function getPayment( $filters = [], $paginateItems = 50 )
	{
		return $this->buildFilterWithElementsQuery($filters, \Veer\Models\OrderPayment::class)
			->orderBy('sites_id', 'asc')
			->with('orders', 'bills')
			->with($this->loadSiteTitle())
			->paginate($paginateItems);
	}
	
	/**
	 * show Shipping Methods
	 */
	public function getShipping( $filters = [], $paginateItems = 50 )
	{
		return $this->buildFilterWithElementsQuery($filters, \Veer\Models\OrderShipping::class)
			->orderBy('sites_id', 'asc')
			->with('orders')
			->with($this->loadSiteTitle())
			->paginate($paginateItems);
	}
	
	/**
	 * show Statuses
	 */
	public function getStatuses($paginateItems = 50)
	{
        app('veer')->loadedComponents['counted']['orders'] = \Veer\Models\Order::select(\DB::raw('count(*) as orders_count, status_id'))
            ->groupBy('status_id')->lists('orders_count', 'status_id');

        app('veer')->loadedComponents['counted']['orders_history'] = \Veer\Models\OrderHistory::select(\DB::raw('count(*) as orders_count, status_id'))->groupBy('status_id')->lists('orders_count', 'status_id');

        app('veer')->loadedComponents['counted']['bills'] = \Veer\Models\OrderBill::select(\DB::raw('count(*) as orders_count, status_id'))->groupBy('status_id')->lists('orders_count', 'status_id');

		return \Veer\Models\OrderStatus::orderBy('manual_order', 'asc')
			//->with('orders', 'bills', 'orders_with_history')
			->paginate($paginateItems);
	}	
	
}
