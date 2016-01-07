<?php
namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class Entity {

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
            $attach_types += ['products', 'parent_pages', 'child_pages'];
        } elseif($this->type == 'product') {
            $attach_types += ['pages', 'parent_products', 'child_products'];
        } elseif($this->type == 'category') {
            $attach_types = ['images', 'products', 'pages']; // only 3 for categories
        } // TODO: test

        if(!in_array($type, $attach_types)) {
            event('veer.message.center', trans('veeradmin.error.impossible.attach.type'));
            return false;
        }

        return true;
    }

    /**
     * @param string $type
     * @param mixed $id
     * @return \Veer\Services\Administration\Elements\Entity
     */
    public function attach($type, $id)
    {
        if($this->isAllowedRelation($type)) {
            $this->action = null;
            $key = $type == 'tags' ? 'tags' : ($type == 'attributes' ? 'attribute' : 'attach' . studly_case($type)); // bugfix
            $value = ':' . (is_array($id) ? implode(',', $id) : $id);

            $this->attachments([$key => $value], $this->entity);
        }
        
        return $this;
    }

    /**
     * @param string $type
     * @param int $id
     * @return \Veer\Services\Administration\Elements\Entity
     */
    public function detach($type, $id)
    {
        if($this->isAllowedRelation($type)) {
            $this->action = ($type == 'tags' || $type == 'attributes') ?:
                    'remove' . studly_case(str_singular($type)) . '.' . $id; // TODO: test

            // tags & attributes will be detached completely
            $key = $type == 'tags' ? 'tags' : ($type == 'attributes' ? 'attribute' : null); // bugfix

            $this->attachments([$key => null], $this->entity);
            $this->action = null;
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
        
        $this->attachments(Input::all(), $this->entity);
		$this->freeForm(Input::get('freeForm'));
    }

    /**
     * @param array $data
     * @param \Veer\Models\Page|\Veer\Models\Product|\Veer\Models\Category $object
     * @return void
     */
    protected function attachments($data, $object)
    {
        if(empty($data) || !is_array($data) || !($object instanceof $this->className)) {
            return null;
        }

        $type = str_plural($this->type);
        $data += ['tags' => '', 'attribute' => '', 'attachImages' => '',
            'attachFiles' => '', 'attachCategories' => '', 'attachPages' => '',
            'attachProducts' => '', 'attachChildPages' => '', 'attachParentPages' => '',
            'attachChildProducts' => '', 'attachParentProducts' => '', 'attachChildCategories' => '',
            'attachParentCategories' => '']; // child & parent categories are not used

        $params = [
            "actionButton" => $this->action,
            "tags" => $data['tags'],
            "attributes" => $data['attribute'],
            "attachImages" => $data['attachImages'],
            "attachFiles" => $data['attachFiles'],
            "attachCategories" => $data['attachCategories'],
            "attachChild" . ucfirst($type) => $data['attachChild' . ucfirst($type)],
            "attachParent" . ucfirst($type) => $data['attachParent' . ucfirst($type)]
        ];

        if($type == 'pages' || $type == 'categories') {
            $params += ["attachProducts" => $data['attachProducts']];
        }
        if($type == 'products' || $type == 'categories') {
            $params += ["attachPages" => $data['attachPages']];
        }

        $prefix = $type == 'pages' ? 'pg' : ($type == 'products' ? 'prd' : 'ct');

		$this->connections($object, $object->id, $type, $params, [
            "prefix" => ["image" => $prefix, "file" => $prefix]
        ]);
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
}
