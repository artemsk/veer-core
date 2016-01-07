<?php namespace Veer\Services\Administration;

class Structure {
	
    protected $action = null;

    public function __construct($t)
    {
        $this->action = 'update' . ucfirst($t);
        app('veer')->skipShow = false;
    }
    
    public function handle()
    {
        return $this->{$this->action}();
    }
    
	/**
	 * Update Sites
	 * @return void 
	 */
	public function updateSites()
	{
        return (new Elements\Site)->run();
    }	
        
	/**
	 * Update Root Categories
	 */
	public function updateCategories()
	{
		return Elements\Category::request();
	}

	/**
	 * update Products
	 */
	public function updateProducts()
	{
		return Elements\Product::request();
	}
		
	/**
	 * update Pages
	 */
	public function updatePages()
	{
		return Elements\Page::request();
	}
	
	/**
	 * update images 
	 */
	public function updateImages()
	{
        return (new Elements\Image)->run();
    }
	
	/**
	 * update tags
	 */
	public function updateTags()
	{		
		return (new Elements\Tag)->run();
	}
	
	/**
	 * update downloads
	 */
	public function updateDownloads()
	{
		return (new Elements\Download)->run();		
	}	
	
	/**
	 * update attributes
	 */
	public function updateAttributes()
	{
        return Elements\Attribute::request();
	}
}
