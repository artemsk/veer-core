<?php
namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

abstract class Entity {

    protected $id;
    protected $action;
    protected $type; // entity type: page, product, category
    protected $className;
    protected $entity;
    
    use HelperTrait, AttachTrait, DeleteTrait;
    
    public function __construct()
    {
        \Eloquent::unguard();
    }

    protected function create($fill)
    {
        // TODO: validate
        
        $object = new $this->className;
        $object->fill($fill);
        $object->save();

        event('veer.message.center', trans('veeradmin.'. $this->type .'.new'));
        return $object;
    }

    /**
     * @param array $data
     * @return \Veer\Services\Administration\Elements\Entity
     */
    public function update($data)
    {
        if ($this->entity instanceof $this->className && !empty($data)) {
            $this->entity->fill($this->prepareData($data));
            $this->entity->save();
            event('veer.message.center', trans('veeradmin.'. $this->type . '.update'));
        }

        return $this;
    }

    /**
     * @return \Veer\Services\Administration\Elements\Entity
     */
    public function toggle()
    {
        return $this->toggleStatus($this->id);
    }

    /**
     * @param string $type
     * @return boolean
     */
    protected function isAllowedRelation($type)
    {
        $attach_types = ['tags', 'attributes', 'images', 'files', 'categories'];

        if($this->type == 'page') {
            $attach_types = array_merge($attach_types, ['products', 'parent_pages', 'child_pages', 'parentpages', 'subpages']);
        } elseif($this->type == 'product') {
            $attach_types = array_merge($attach_types, ['pages', 'parent_products', 'child_products', 'parentproducts', 'subproducts']);
        } elseif($this->type == 'category') {
            $attach_types = ['images', 'products', 'pages', 'parentcategories', 'subcategories', 'parent_categories', 'sub_categories'];
        }

        if(!in_array($type, $attach_types)) {
            event('veer.message.center', trans('veeradmin.error.impossible.attach.type'));
            return false;
        }

        return true;
    }

    protected function relationAliases($type)
    {
        $aliases = [
            'parent_pages'           => 'parentpages',
            'child_pages'            => 'subpages',
            'parent_products'        => 'parentproducts',
            'child_products'         => 'subproducts',
            'attribute'              => 'attributes',
            'attachImages'           => 'images',
            'attachFiles'            => 'files',
            'attachCategories'       => 'categories',
            'attachPages'            => 'pages',
            'attachProducts'         => 'products',
            'attachChildPages'       => 'subpages',
            'attachParentPages'      => 'parentpages',
            'attachChildProducts'    => 'subproducts',
            'attachParentProducts'   => 'parentproducts',
            'attachChildCategories'  => 'subcategories',
            'attachParentCategories' => 'parentcategories',
            'removeImage'            => 'images',
            'removeCategory'         => 'categories',
            'removePage'             => 'pages',
            'removeProduct'          => 'products',
            'removeChildProduct'     => 'subproducts',
            'removeParentProduct'    => 'parentproducts',
            'removeChildPage'        => 'subpages',
            'removeParentPage'       => 'parentpages',
            'removeChildCategory'    => 'subcategories',
            'removeParentCategory'   => 'parentcategories',
            'removeFile'             => 'files',
            'removeAttribute'        => 'attributes',
            'removeTag'              => 'tags'
        ];

        return isset($aliases[$type]) ? $aliases[$type] : $type;
    }

    /**
     * @param string $type
     * @param mixed $id
     * @param boolean $replace
     * @return \Veer\Services\Administration\Elements\Entity
     */
    public function attach($type, $id, $replace = false, $separator = null, $start = null)
    {
        $relation = $this->relationAliases($type);
        if($this->isAllowedRelation($relation) && !empty($id)) {          
            $this->attaching($relation, $id, $replace, $separator, $start);
        }
        
        return $this;
    }

    /**
     * @param string $relation
     * @param mixed $ids
     * @param boolean $replace
     * @param string $separator
     * @param string $start
     */
    protected function attaching($relation, $ids, $replace = false, $separator = ',', $start = ':')
    {
        switch($relation) {
            case 'attributes':
                $this->attachAttributes($ids, $this->entity, $replace);
                break;
            case 'tags':
                $this->attachTags($ids, $this->entity, $separator, $replace);
                break;
            case 'files':
                $this->copyFiles($ids, $this->entity, $separator, $start);
                break;
            default:
                $this->attachElements($ids, $this->entity, $relation, null, $separator, $start, $replace);
                break;
        }
    }

    /**
     * @param string $file_type
     * @param array|null $data
     * @param object|null $object
     */
    protected function addImageOrFile($file_type, $data = null)
    {        
        if(!empty($this->id)) {
            $relation = str_plural($this->type);
            $prefix = $relation == 'pages' ? 'pg' : ($relation == 'products' ? 'prd' : 'ct');
            $key = $file_type == 'image' ? 'uploadImage' : 'uploadFiles';

            $this->upload($file_type, $key, $this->id, ($file_type == 'image' ? $relation : $this->entity), $prefix, [
                "language" => "veeradmin." . $this->type . "." . str_plural($file_type) . ".new"
            ], false, $data);
        }
    }

    /**
     * @param array $data
     * @return \Veer\Services\Administration\Elements\Entity
     */
    public function image($data)
    {
        $this->addImageOrFile('image', $data);
        return $this;
    }

    /**
     * @param array $data
     * @return \Veer\Services\Administration\Elements\Entity
     */
    public function file($data)
    {
        $this->addImageOrFile('file', $data);
        return $this;
    }

    /**
     * @param string $type
     * @param mixed $id
     * @param boolean $strict
     * @return \Veer\Services\Administration\Elements\Entity
     */
    public function detach($type, $id = null, $strict = false)
    {
        $relation = $this->relationAliases($type);
        if($this->isAllowedRelation($relation)) {
            if($relation == 'files') {
                if(empty($id) && !$strict) {
                    \Veer\Models\Download::where('elements_id', '=', $this->id)
                            ->where('elements_type', '=', $this->className)
                            ->update(['elements_id' => 0, 'elements_type' => '']);
                } elseif(!empty($id)) {
                    \Veer\Models\Download::where('id', '=', $id)
                            ->update(['elements_id' => 0, 'elements_type' => '']);
                }
            } else {                
                if(empty($id) && !$strict) {
                    $this->entity->{$relation}()->detach();
                } elseif(!empty($id)) {
                    $this->entity->{$relation}()->detach((array)$id);
                }
            }
            event('veer.message.center', trans('veeradmin.' . $this->type . '.' . $relation. '.detach'));
        }

        return $this;
    }

    /**
     * @param int $id
     * @return \Veer\Services\Administration\Elements\Entity
     */
    public function find($id)
    {
        $className = $this->className;
        $entity = $className::find($id);

        if (is_object($entity)) {
            $this->id = $id;
            $this->entity = $entity;
        }

        return $this;
    }

    /**
     *
     * @return mixed
     */
    protected function one()
    {
        $this->id = Input::get('id');

        $fill = Input::has('fill') ? $this->prepareData(Input::get('fill')) : null;
        
        if($this->action == 'add' || $this->action == 'saveAs') {            
            if($this->type == 'page') {
                $fill['hidden'] = true;
            } elseif($this->type == 'product') {
                $fill['status'] = 'hide';
            }

            $entity = $this->create($fill);            
        } else {
            $className = $this->className;
            $entity = $className::find($this->id);
        }
        
		if(!is_object($entity)) {
            event('veer.message.center', trans('veeradmin.error.model.not.found'));
            return \Redirect::route('admin.show', [str_plural($this->type)]);
        }

        $this->id = $entity->id;
        $this->entity = $entity;
        
        $this->goThroughEverything($fill);

		if($this->action == 'add' || $this->action == 'saveAs') {
            app('veer')->skipShow = true;
            Input::replace(['id' => $this->id]);
            return \Redirect::route('admin.show', [str_plural($this->type), 'id' => $this->id]);
        }
    }

    /**
     * @param array $fill
     * @return void
     */
    protected function goThroughEverything($fill = null)
    {
        $this->action != 'update' ?: $this->update($fill);
        !starts_with($this->action, "changeStatusPage") ?: $this->toggleStatus(substr($this->action, 17));
        !starts_with($this->action, "updateStatus") ?: $this->toggleStatus(substr($this->action, 13)); // TODO: change to changeStatusProduct
        
        $this->attachmentActions();
        $this->detachmentActions();
        $this->image(Input::get('uploadImage'));
        $this->file(Input::get('uploadFiles'));        
		$this->freeForm(Input::get('freeForm'));
    }

    /**
     * @return void
     */
    protected function attachmentActions()
    {
        if(!($this->entity instanceof $this->className)) {
            return null;
        }
       
        $attachmentTriggers = [
            'tags', 'attribute', 'attachImages',
            'attachFiles', 'attachCategories', 'attachPages',
            'attachProducts', 'attachChildPages', 'attachParentPages',
            'attachChildProducts', 'attachParentProducts', 'attachChildCategories',
            'attachParentCategories'
        ];
        
        foreach(Input::all() as $key => $value) {
            if(in_array($key, $attachmentTriggers) && !empty($value)) {
                $relation = $this->relationAliases($key);
                if($this->isAllowedRelation($relation)) {
                    $replace = ($relation == 'tags' || $relation == 'attributes') ? true : false;
                    $this->attaching($relation, $value, $replace); // TODO: check attachCategories
                }
            }
        }
    }

    protected function detachmentActions()
    {
        $detachTriggers = [
            'removeImage', 'removeCategory', 'removePage', 'removeProduct',
            'removeChildProduct', 'removeParentProduct', 'removeChildPage', 'removeParentPage',
            'removeChildCategory', 'removeParentCategory', 'removeFile', 'removeAttribute', 'removeTag'
        ];

        foreach($detachTriggers as $trigger) {
            if(starts_with($this->action, $trigger)) {
                $parse = explode('.', $this->action);
                empty($parse[1]) ?: $this->detach($trigger, $parse[1], true);
            }
        }

        if(starts_with($this->action, 'removeAllImages')) {
            $this->detach('images');
        }
    }

    /**
     * @param string $data
     * @return \Veer\Services\Administration\Elements\Entity
     */
    public function freeForm($data)
    {
        if(empty($data)) {
            return $this;
        }

        preg_match_all("/^(.*)$/m", trim($data), $ff); // TODO: test
        if(empty($ff[1]) || !is_array($ff[1])) {
            return $this;
        }
        
        foreach($ff[1] as $freeForm) {
            if(starts_with($freeForm, 'Tag:')) {
                $this->attachElements($freeForm, $this->entity, 'tags', null, ",", "Tag:");
            } else {
                $this->attachElements($freeForm, $this->entity, 'attributes', null, ",", "Attribute:");
            }
        }

        return $this;
    }
    
    /**
     * @param array $fill
     */
    protected function prepareData($fill) 
    {
        return $fill;
    }

    /**
     * @param int $id
     */
    public function toggleStatus($id) {}

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return object
     */
    public function getEntity()
    {
        return $this->entity;
    }
}
