<?php

namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class Category extends Entity {

    protected $type = 'category';
    protected $className = \Veer\Models\Category::class;

    /**
     *
     * @return void
     */
    public static function request()
    {
        $class = new static;
        $class->action = Input::get('action');

        $class->action != 'delete' ?: $class->delete(Input::get('deletecategoryid'));

        if (Input::has('category')) {
            return $class->one();
        }

        $addCategory = $class->action == 'add' ? Input::all() : null;
        $sortCategory = $class->action == 'sort' ? Input::all() : null;

        $class->sort($sortCategory)
            ->addCategory($addCategory);
        
        //return $class->isAjaxRequest();
    }

    /**
     * @param int $id
     * @return \Veer\Services\Administration\Elements\Category
     */
    public function delete($id)
    {
        if(!empty($id) && $this->deleteCategory($id)) {
            event('veer.message.center', trans('veeradmin.category.delete'));
            // @todo restore link?
        }

        return $this;
    }

    /**
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
        $c->description = array_get($options, 'description', '');
        $c->remote_url = array_get($options, 'remote_url', '');
        $c->manual_sort = array_get($options, 'sort', 999999);
        $c->views = array_get($options, 'views', 0);
        $c->sites_id = empty($siteid) ? app('veer')->siteId : $siteid;
        $c->save();

        if($updateEntity) {
            $this->id = $c->id;
            $this->entity = $c;
        }
        
        return $returnId ? $c->id : $this;
	}

    /**
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
            $categoryObj->getAllCategories(array_get($data, 'image'), []) :
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
                        ->where('id', '=', Input::get('siteid', app('veer')->siteId))->get();

        /* for admin we always use 'view' instead of 'viewx' */        
        return view(app('veer')->template . '.lists.categories-category', [
            "categories" => $items[0]->categories,
            "siteid" => Input::get('siteid', app('veer')->siteId)
        ]);
    }

    /**
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
        $this->id = Input::get('category');        
        $category = \Veer\Models\Category::find($this->id);

        if(!is_object($category)) {
            event('veer.message.center', trans('veeradmin.error.model.not.found'));
            return \Redirect::route('admin.show', ['categories']);
        }

        $this->entity = $category;
        
        $this->goThroughEverything();

        if($this->action == 'deleteCurrent') {
            Input::replace(['category' => null]);
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
                !Input::has('parentId') ?: $this->attachParent(Input::get('parentId'));
                break;
            case 'updateParent':
                if(Input::has('parentId') && Input::has('lastCategoryId') &&
                        Input::get('lastCategoryId') != Input::get('parentId')) {
                    $this->attachParent(Input::get('parentId'));
                }
                break;
            case 'removeParent':
                !Input::has('parentId') ?: $this->detachParent(Input::get('parentId'));
                break;
            case 'updateCurrent':
                $this->update(array_intersect_key(Input::all(), array_keys(['title', 'remoteUrl', 'description'])));
                break;
            case 'addChild':
                !Input::has('child') ?: $this->attachChild(Input::get('child'));
                break;
            case 'removeInChild':
                !Input::has('currentChildId') ?: $this->detachChild(Input::get('currentChildId'));
                break;
            case 'updateInChild':
                if(Input::has('currentChildId') && Input::has('parentId') && Input::has('lastCategoryId') &&
                        Input::get('lastCategoryId') != Input::get('parentId')) {
                    $this->updateChildParent(
                        Input::get('currentChildId'), Input::get('parentId'), Input::get('lastCategoryId')
                    );
                }
                break;
            case 'sort':
                $this->sortChilds(Input::all());
                break;
            case 'updateImages':
                if(Input::hasFile('uploadImage')) {
                    $this->image(Input::get('uploadImage'));
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
        $changeStatusPage = starts_with($this->action, "changeStatusPage") ? substr($this->action, 17) : null;
        $deletePage = starts_with($this->action, "deletePage") ? substr($this->action, 11) : null;
        $changeStatusProduct = starts_with($this->action, "changeStatusProduct") ? substr($this->action, 20) : null;
        $deleteProduct = starts_with($this->action, "deleteProduct") ? substr($this->action, 14) : null;
        $showEarlyProduct = starts_with($this->action, "showEarlyProduct") ? substr($this->action, 17) : false;

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
}
