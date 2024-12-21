<?php namespace Veer\Services\Show;

class Product {

	use \Veer\Services\Traits\CommonTraits, \Veer\Services\Traits\EntityTraits;
	
	/**
	 * Query Builder: 
	 * 
	 * - who: Sorted Products: new, popular by orders or views
	 * - with: Images
	 * - to whom: ?
	 */
	public function getProductLists($type, $siteId = null, $queryParams = [])
	{
		$items = \Veer\Models\Product::select();
		
		if(!empty($siteId)) $items->sitevalidation($siteId)->checked();
		
		$typeSorting = ["new" => "created_at", "ordered" => "ordered", "viewed" => "viewed"];
		
		$items->with(['images' => function($query) {
			$query->orderBy('pivot_id', 'asc');
		}])->orderBy( \Illuminate\Support\Arr::get($typeSorting, $type, 'created_at'), 'desc');
		
		return $items->take(\Illuminate\Support\Arr::get($queryParams, 'take', 15))
			->skip(\Illuminate\Support\Arr::get($queryParams, 'skip', 0))
			->get();
	}
	
	/**
	 * Query Builder: 
	 * 
	 * - who: 1 Product
	 * - with: Categories (?)
	 * - to whom: make() | product/{id}
	 */
	public function getProduct($id, $siteId = null)
	{
		$field = !is_numeric($id) ? "url" : "id";

		$product = \Veer\Models\Product::where($field, '=', $id);
		
		if(!empty($siteId)) { 
			$product = $product->checked()->sitevalidation($siteId)
			->with(['categories' => function($query) use ($siteId) {
				$query->where('sites_id', '=', $siteId);
			}]); 
		}
		
		return $product->first();
	}
	
	/**
	 * Query Builder: 
	 * 
	 * - who: Many Pages
	 * - with: Images
	 * - to whom: 1 Product | product/{id}
	 */
	public function withPages($siteId, $id, $queryParams)
	{
		return $this->getElementsWhereHasModel('pages', 'products', $id, $siteId, $queryParams);
	}
	
	/**
	 * Query Builder: 
	 * 
	 * - who: Many Sub Products
	 * - with: Images
	 * - to whom: 1 Product | product/{id}
	 */
	public function withChildProducts($siteId, $id, $queryParams = null)
	{
		return $this->getElementsWhereHasModel('products', ['parentproducts', 'parent'], $id, $siteId, $queryParams);
	}

	/**
	 * Query Builder: 
	 * 
	 * - who: Many Parent Products
	 * - with: Images
	 * - to whom: 1 Product | product/{id}
	 */
	public function withParentProducts($siteId, $id, $queryParams = null)
	{
		return $this->getElementsWhereHasModel('products', ['subproducts', 'child'], $id, $siteId, $queryParams);
	}
	
	/**
	 * Query Builder: 
	 * 
	 * - who: Many Categories
	 * - with: Images
	 * - to whom: 1 Product | product/{id}
	 */
	public function withCategories($siteId, $id)
	{
		return $this->getCategoriesWhereHasElements('products', $id, $siteId);		
	}
	
	
	/* get product advanced */
	public function getProductAdvanced($product, $options = [])
	{
		if($product == "new") return new \stdClass(); 

		$items = $this->getProduct($product);
			
		if(is_object($items)) 
		{
			$items->load(
				'subproducts', 'parentproducts', 'pages', 'categories', 
				'tags', 'attributes', 'downloads' );		

			$this->loadImagesWithElements($items, \Illuminate\Support\Arr::get($options, 'skipWith', false));
			
			$items['basket'] = $items->userlists()->where('name','=','[basket]')->count();
			
			$items['lists'] = $items->userlists()->where('name','!=','[basket]')->count();	
		}	
		
		return $items;
	}	
	
	/**
	 * Show Products
	 */
	public function getAllProducts($filters = [], $paginateItems = 24)
	{			
		return $this->getAllEntities(\Veer\Models\Product::class, \Illuminate\Support\Arr::get($filters, 0, []), $paginateItems, \Illuminate\Support\Arr::get($filters, 1, []));
	}	
	
	
	/**
	 * Query Builder: 
	 * 
	 * - who: List of Products in/across Sites 
	 * - with: Images
	 * - to whom: ? 
	 */
	public function getConnectedProducts($id, $siteId = null)
	{
		$p = \Veer\Models\Product::whereIn('id', $id)
			->with(['images' => function($query) {
				$query->orderBy('pivot_id', 'asc');
			}])
			->checked();

		if(!empty($siteId)) $p->sitevalidation($siteId);	
			
		return $p->get();
	}
	

}
