<?php namespace Veer\Services\Show;

class Search {

	use \Veer\Services\Traits\FilterTraits;
	
	protected $targetModel;
	
	/**
	 * Query Builder: 
	 * 
	 * - who: Search Results for Many Products & Pages
	 * - with: Images
	 * - to whom: make() | search/{id} or $_POST
	 */
	public function getSearchResultsWithSite($siteId, $q, $queryParams = [])
	{
		$p = ['products' => [], 'pages' => []];
		
		$qq = explode(' ', $q);

		$p['products'] = $this->searchModel(\Veer\Models\Product::class, 
			\Illuminate\Support\Arr::get($queryParams, 'search_field_product', 'title'), $qq, $siteId, $queryParams);

		$p['pages'] = $this->searchModel(\Veer\Models\Page::class, 
			\Illuminate\Support\Arr::get($queryParams, 'search_field_page', 'title'), $qq, $siteId, $queryParams);
		
		return $p;
	}
	
	/* search model */
	protected function searchModel($model, $field, $q, $siteId = null, $queryParams = [])
	{
		$results = $model::whereNested(function($query) use ($q, $field) {
				foreach ($q as $word) {
					$query->where(function($queryNested) use ($word, $field) {
						$queryNested->where($field, '=', $word)
							->orWhere($field, 'like', $word . '%%%')
							->orWhere($field, 'like', '%%%' . $word)
							->orWhere($field, 'like', '%%%' . $word . '%%%');
					});
				}
			})->with(['images' => function($query) {
			$query->orderBy('pivot_id', 'asc');
		}]);

		if(!empty($siteId) && $model == \Veer\Models\Product::class) $results->checked()->siteValidation($siteId);
		
		if(!empty($siteId) && $model == \Veer\Models\Page::class) $results->excludeHidden()->siteValidation($siteId);
		
		return $results->orderBy(\Illuminate\Support\Arr::get($queryParams, 'sort', 'created_at'), \Illuminate\Support\Arr::get($queryParams, 'direction', 'desc'))
			->take(\Illuminate\Support\Arr::get($queryParams, 'take', 25))
			->skip(\Illuminate\Support\Arr::get($queryParams, 'skip', 0))->get();
	}
	
	protected function parseQ($q)
	{		
		if(\Illuminate\Support\Str::startsWith($q, '!')) return \Illuminate\Support\Arr::add(explode(":", substr(mb_strtolower($q),1)), 1, null);
				
		return [null, null];
	}
	
	protected function getModelName($model, $t)
	{
		if(empty($model)) return $this->findModelNameByUrl($t);
		
		if(in_array($model, ['product', 'page', 'category', 'user', 'order'])) { 
			
			$this->targetModel = \Illuminate\Support\Str::plural($model);
			
			return elements($this->targetModel); 
		}
	}
	
	protected function findModelNameByUrl($t)
	{
		$models = ["books" => "UserBook", "lists" => "UserList", "roles" => "UserRole", "statuses" => "OrderStatus", "payment" => "OrderPyment", "shipping" => "OrderShipping", "discounts" => "UserDiscount", "bills" => "OrderBill", "jobs" => null, "etc" => null];
		
		if(!array_key_exists($t, $models)) return elements($t);
		
		if(isset($models[$t])) return elements($models[$t]);
	}
	
	/**
	 * 
	 * @todo leftovers: categories, attributes,
	 */
	protected function getSearchFields($t)
	{
		$fields = ["users" => ["email", "username", "firstname", "lastname", "phone"], "books" => ["name", "country", "region", "city", "postcode", "address", "nearby_station", "b_bank", "b_bik", "b_others"], "searches" => ["q"], "comments" => ["author", "txt", "rate"], "pages" => ["title", "small_txt", "txt"], "products" => ["title", "descr", "production_code"], "tags" => ["name"], "orders" => ["id", "cluster_oid", "email", "phone"], "bills" => ["id", "orders_id"]];
		
		return isset($fields[$t]) ? $fields[$t] : null;
	}
	
	/** 
	 * Search
	 */
	public function searchAdmin($t, $paginateItems = 25)
	{
		$this->targetModel = $t;
		
		$q = \Input::get('SearchField');
		
		list($model, $id) = $this->parseQ($q);
		
		$field = $model == 'category' ? 'category' : 'id';
		
		$model = $this->getModelName($model, $this->targetModel);
				
		if(!empty($id)) return \Redirect::route('admin.show', [$this->targetModel, $field => $id]);
		
		$view = $this->targetModel;
		
		$searchFields = $this->getSearchFields($this->targetModel);
		
		if(!empty($searchFields))
		{
			$items = $model::whereNested(function($query) use($q, $searchFields) {
				foreach($searchFields as $s) { $query->orWhere($s, 'like', '%'.$q.'%'); }
			})->paginate($paginateItems);	
		}
		
		if(isset($items) && is_object($items))
		{
			return viewx(app('veer')->template.'.'.$view, ["items" => $items, "template" => app('veer')->template]);
		}
			
		return false;
	}	
}
