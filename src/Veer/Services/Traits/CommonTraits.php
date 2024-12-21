<?php namespace Veer\Services\Traits;

trait CommonTraits {
	
	/*
	 * Load Images with Elements
	 */
	public function loadImagesWithElements($items, $skipWith = false)
	{
		return $items->load(['images' => function($q) use ($skipWith)
			{
				if($skipWith === false) $q->with('pages', 'products', 'categories', 'users');
				
				$q->orderBy('pivot_id', 'asc');
			}]);
	}
	
	/**
	 * Query Builder: 
	 * 
	 * - who: Many Products | Pages
	 * - with: Images
	 * - to whom: 1 Attribute, category
	 */
	public function getElementsWhereHasModel($type, $table, $id, $siteId = null, $queryParams = null, $returnBuilder = false)
	{
		if($type == "products") $model = \Veer\Models\Product::class;
		else $model = \Veer\Models\Page::class;
		
		$table_field = $table;
		
		if(is_array($table)) list($table, $table_field) = $table;
		
		$items = $model::whereHas($table, function($q) use($id, $table_field) {
					$q->where($table_field.'_id', '=', $id);
				});
				
		if($table != "images") {
			$items = $items->with(['images' => function($query) {
					$query->orderBy('pivot_id', 'asc');
			}]);
		}
		
		if(!empty($siteId)) $items = $items->sitevalidation($siteId);

        $items = $this->elementsCheckSortCount($type, $table, $id, $siteId, $queryParams, $items);
                
		return $returnBuilder === true ? $items : $items->get();		
	}		

    /* checks, orders, counts */
    protected function elementsCheckSortCount($type, $table, $id, $siteId, $queryParams, $items)
    {
        $postfix = "";

        if($type == "pages") {
            $items = $items->excludeHidden();
            $postfix = "_pages";
        }

        if($type == "products") $items = $items->checked();

        app('veer')->loadedComponents['totalElements'][$type][$table][$id][$siteId] = app('veer')->cachingQueries->makeAndRemember(
            $items, 'count', 5);

        return $items->orderBy( \Illuminate\Support\Arr::get($queryParams, 'sort' . $postfix, 'created_at'),
                \Illuminate\Support\Arr::get($queryParams, 'direction' . $postfix, 'desc'))
                ->take(\Illuminate\Support\Arr::get($queryParams, 'take' . $postfix, 25))->skip(\Illuminate\Support\Arr::get($queryParams, 'skip' . $postfix, 0));
    }

	/* with models */
	public function withModels($model, $table, $id, $siteId = null)
	{
		app('veer')->cachingQueries->make(
			$model::whereHas('products', function($query) use($id, $table, $siteId) 
			{
				if(!empty($siteId)) $query->siteValidation($siteId);
				$query->checked()->whereHas($table, function($q) use ($id, $table) {
							$q->where($table.'_id','=',$id);
					});
			})->orWhereHas('pages', function($query) use($id, $table, $siteId) 
			{
				 if(!empty($siteId)) $query->siteValidation($siteId);
				 $query->excludeHidden()->whereHas($table, function($q) use ($id, $table) {
							$q->where($table.'_id','=',$id);
					});
			}));
			
		return app('veer')->cachingQueries->remember(5, 'get');
	}
	
}
