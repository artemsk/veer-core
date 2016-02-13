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
        return Elements\Site::request();
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
        return Elements\Image::request();
    }
	
	/**
	 * update tags
	 */
	public function updateTags()
	{		
		return Elements\Tag::request();
	}
	
	/**
	 * update downloads
	 */
	public function updateDownloads()
	{
		return Elements\Download::request();
	}	
	
	/**
	 * update attributes
	 */
	public function updateAttributes()
	{
        return Elements\Attribute::request();
	}
}
