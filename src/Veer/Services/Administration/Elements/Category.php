<?php

namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

/**
 * Category model's actions and commands.
 * Helper for requests.
 */
class Category extends Entity {

    /**
     *
     * @var string
     */
    protected $type = 'category';

    /**
     *
     * @var string
     */
    protected $className = \Veer\Models\Category::class;

    /**
     * Actions for categories based on Request. Triggers are hardcoded.
     * 
     * @return void
     */
    public static function request()
    {
        $class = new static;
        $class->action = \Illuminate\Support\Facades\Request::input('action');

        $class->action != 'delete' ?: $class->delete(\Illuminate\Support\Facades\Request::input('deletecategoryid'));

        if (\Illuminate\Support\Facades\Request::has('category')) {
            return $class->one();
        }

        $addCategory = $class->action == 'add' ? \Illuminate\Support\Facades\Request::all() : null;
        $sortCategory = $class->action == 'sort' ? \Illuminate\Support\Facades\Request::all() : null;

        $class->sort($sortCategory)
            ->addCategory($addCategory);
        //return $class->isAjaxRequest();
    }

    /**
     * Delete a Category with relations.
     *
     * @helper Model delete
     * @todo use restore link?
     * @param int $id
     * @return \Veer\Services\Administration\Elements\Category
     */
    public function delete($id)
    {
        if(!empty($id) && $this->deleteCategory($id)) {
            event('veer.message.center', trans('veeradmin.category.delete'));
        }

        return $this;
    }

    /**
     * Add a Category alias.
     *
     * @helper Model create
     * @param array $data
     * @return \Veer\Services\Administration\Elements\Category|\Response
     */
    public function addCategory($data)
    {
        if(!empty($data) && is_array($data)) {
            $data += ['options' => [], 'newcategory' => '', 'siteid' => 0];
            $this->add($data['newcategory'], $data['siteid'], $data['options']);

            event('veer.message.center', trans('veeradmin.category.add'));
        }

        return $this;
    }

    /**
     * Add a Category.
     *
     * @helper Model create|save
     * @param string $title
     * @param int $siteid
     * @param array $options
     * @param boolean $updateEntity
     * @param boolean $returnId
     * @return \Veer\Services\Administration\Elements\Category|integer
     */
	public function add($title, $siteid = null, $options = [], $updateEntity = true, $returnId = false)
	{
        if(empty($title)) { 
            return $returnId ? null : $this;
        }

        $c = new \Veer\Models\Category;
        $c->title = $title;
        $c->description = \Illuminate\Support\Arr::get($options, 'description', '');
        $c->remote_url = \Illuminate\Support\Arr::get($options, 'remote_url', '');
        $c->manual_sort = \Illuminate\Support\Arr::get($options, 'sort', 999999);
        $c->views = \Illuminate\Support\Arr::get($options, 'views', 0);
        $c->sites_id = empty($siteid) ? app('veer')->siteId : $siteid;
        $c->save();

        if($updateEntity) {
            $this->id = $c->id;
            $this->entity = $c;
        }
        
        return $returnId ? $c->id : $this;
	}

    /**
     * Sort categories.
     * 
     * @param array $data
     * @return \Veer\Services\Administration\Elements\Category
     */
    public function sort($data)
    {
        if(empty($data) || !isset($data['parentid'])) {
            return $this;
        }

        $data += ['relationship' => 'categories'];
        $categoryObj = new \Veer\Services\Show\Category;

        $oldsorting = $data['relationship'] == 'categories' ?
            $categoryObj->getAllCategories(\Illuminate\Support\Arr::get($data, 'image'), []) :
            [$categoryObj->getCategoryAdvanced($data['parentid'])];

        if (is_object($oldsorting) || is_object($oldsorting[0])) {
            foreach($this->sortElements($oldsorting, $data) as $sort => $id) {
                \Veer\Models\Category::where('id', '=', $id)->update(['manual_sort' => $sort]);
            }
        }

        return $this;
    }
    
    /**
     * @todo redo, remove
     * @return null | Response::view()
     */
    protected function isAjaxRequest()
    {
        if(!app('request')->ajax()) {
            return null;
        }
        
        $items = \Veer\Models\Site::with(['categories' => function($query) {
                    $query->has('parentcategories', '<', 1)->orderBy('manual_sort', 'asc');
                }])->orderBy('manual_sort', 'asc')
                        ->where('id', '=', \Illuminate\Support\Facades\Request::input('siteid', app('veer')->siteId))->get();

        /* for admin we always use 'view' instead of 'viewx' */        
        return view(app('veer')->template . '.lists.categories-category', [
            "categories" => $items[0]->categories,
            "siteid" => \Illuminate\Support\Facades\Request::input('siteid', app('veer')->siteId)
        ]);
    }

    /**
     * Attach a parent Category to current Category.
     *
     * @helper Model attach
     * @param int $parent_id
     * @return \Veer\Services\Administration\Elements\Category
     */
    public function attachParent($parent_id)
    {
        if(!empty($parent_id)) {
            $this->attachParentCategory($this->id, $parent_id, $this->entity);
        }
        
        return $this;
    }

    /**
     * Detach a parent Category from current.
     *
     * @helper Model detach
     * @param int $parent_id
     * @return \Veer\Services\Administration\Elements\Category
     */
    public function detachParent($parent_id)
    {
        if(!empty($parent_id)) {
            $this->entity->parentcategories()->detach($parent_id);
            event('veer.message.center', trans('veeradmin.category.parent.detach'));
        }
        
        return $this;
    }

    /**
     * Attach a child category to current.
     *
     * @helper Model attach
     * @param mixed $child
     * @return \Veer\Services\Administration\Elements\Category
     */
    public function attachChild($child)
    {
        $childs = $this->attachElements($child, $this->entity, 'subcategories', [
            "language" => "veeradmin.category.child.attach"
        ]);

        if(!$childs) {
            $this->entity->subcategories()->attach(
                $this->add($child, $this->entity->site->id, [], false, true)
            );
            
            event('veer.message.center', trans('veeradmin.category.child.new'));
        }

        return $this;
    }

    /**
     * Detach a child Category from current.
     *
     * @helper Model detach
     * @param int $child_id
     * @return \Veer\Services\Administration\Elements\Category
     */
    public function detachChild($child_id)
    {
        if(!empty($child_id)) {
            $this->entity->subcategories()->detach($child_id);
            event('veer.message.center', trans('veeradmin.category.child.detach'));
        }
        
        return $this;
    }

    /**
     * Change parent Category in the child Category (detach from current).
     *
     * @helper Model attach|detach
     * @param int $child_id
     * @param int $parent_id
     * @param int|null $current_parent_id
     * @return \Veer\Services\Administration\Elements\Category
     */
    public function updateChildParent($child_id, $parent_id, $current_parent_id = null)
    {
        if(empty($current_parent_id)) {
            $current_parent_id = $this->id;
        }

        $check = \Veer\Models\CategoryPivot::where('child_id', '=', $child_id)
            ->where('parent_id', '=', $parent_id)->first();

        if(!$check) { // update child's parent
            $category = \Veer\Models\Category::find($child_id);
            
            if(is_object($category)) {
                $category->parentcategories()->detach($current_parent_id);
                $category->parentcategories()->attach($parent_id);

                event('veer.message.center', trans('veeradmin.category.child.parent'));
            }
        }

        return $this;
    }

    /**
     * Sort child categories of the current category.
     * 
     * @param array $data
     * @return \Veer\Services\Administration\Elements\Category
     */
    public function sortChilds($data)
    {
        $data['relationship'] = "subcategories";
        return $this->sort($data);
    }

    /**
     * @return mixed
     */
	protected function one()
	{
        $this->id = \Illuminate\Support\Facades\Request::input('category');        
        $category = \Veer\Models\Category::find($this->id);

        if(!is_object($category)) {
            event('veer.message.center', trans('veeradmin.error.model.not.found'));
            return \Redirect::route('admin.show', ['categories']);
        }

        $this->entity = $category;
        
        $this->goThroughEverything();

        if($this->action == 'deleteCurrent') {
            \Illuminate\Support\Facades\Request::replace(['category' => null]);
            app('veer')->skipShow = true;
            event('veer.message.center', trans('veeradmin.category.delete'));
            return \Redirect::route('admin.show', ['categories']);
        }
	}

    /**
     * @param null $fill
     */
    protected function goThroughEverything($fill = null)
    {
        switch ($this->action) {
            case 'deleteCurrent':
                $this->deleteCategory($this->id);
                break;
            case 'saveParent':
                !\Illuminate\Support\Facades\Request::has('parentId') ?: $this->attachParent(\Illuminate\Support\Facades\Request::input('parentId'));
                break;
            case 'updateParent':
                if(\Illuminate\Support\Facades\Request::has('parentId') && \Illuminate\Support\Facades\Request::has('lastCategoryId') &&
                        \Illuminate\Support\Facades\Request::input('lastCategoryId') != \Illuminate\Support\Facades\Request::input('parentId')) {
                    $this->attachParent(\Illuminate\Support\Facades\Request::input('parentId'));
                }
                break;
            case 'removeParent':
                !\Illuminate\Support\Facades\Request::has('parentId') ?: $this->detachParent(\Illuminate\Support\Facades\Request::input('parentId'));
                break;
            case 'updateCurrent':
                $this->update(array_intersect_key(\Illuminate\Support\Facades\Request::all(), array_keys(['title', 'remoteUrl', 'description'])));
                break;
            case 'addChild':
                !\Illuminate\Support\Facades\Request::has('child') ?: $this->attachChild(\Illuminate\Support\Facades\Request::input('child'));
                break;
            case 'removeInChild':
                !\Illuminate\Support\Facades\Request::has('currentChildId') ?: $this->detachChild(\Illuminate\Support\Facades\Request::input('currentChildId'));
                break;
            case 'updateInChild':
                if(\Illuminate\Support\Facades\Request::has('currentChildId') && \Illuminate\Support\Facades\Request::has('parentId') && \Illuminate\Support\Facades\Request::has('lastCategoryId') &&
                        \Illuminate\Support\Facades\Request::input('lastCategoryId') != \Illuminate\Support\Facades\Request::input('parentId')) {
                    $this->updateChildParent(
                        \Illuminate\Support\Facades\Request::input('currentChildId'), \Illuminate\Support\Facades\Request::input('parentId'), \Illuminate\Support\Facades\Request::input('lastCategoryId')
                    );
                }
                break;
            case 'sort':
                $this->sortChilds(\Illuminate\Support\Facades\Request::all());
                break;
            case 'updateImages':
                if(\Illuminate\Support\Facades\Request::hasFile('uploadImage')) {
                    $this->image(\Illuminate\Support\Facades\Request::input('uploadImage'));
                }
            case 'updateProducts':
            case 'updatePages':
                $this->attachmentActions();
                break;
        }

		$this->detachmentActions();
        $this->productsOrPagesActions();
    }

    /**
     * @return void
     */
    protected function productsOrPagesActions()
    {
        $changeStatusPage = \Illuminate\Support\Str::startsWith($this->action, "changeStatusPage") ? substr($this->action, 17) : null;
        $deletePage = \Illuminate\Support\Str::startsWith($this->action, "deletePage") ? substr($this->action, 11) : null;
        $changeStatusProduct = \Illuminate\Support\Str::startsWith($this->action, "changeStatusProduct") ? substr($this->action, 20) : null;
        $deleteProduct = \Illuminate\Support\Str::startsWith($this->action, "deleteProduct") ? substr($this->action, 14) : null;
        $showEarlyProduct = \Illuminate\Support\Str::startsWith($this->action, "showEarlyProduct") ? substr($this->action, 17) : false;

        (new Product)->toggleStatus($changeStatusProduct)
            ->delete($deleteProduct)
            ->available($showEarlyProduct);

        (new Page)->toggleStatus($changeStatusPage)
            ->delete($deletePage); 
    }

    /**
     * Category does not have a status. Do nothing.
     *
     * @param int $id
     */
    public function toggleStatus($id)
    {
        return $this;
    }

    /**
     * @param array $fill
     */
    protected function prepareData($fill)
    {
        return $fill;
    }
    
}
