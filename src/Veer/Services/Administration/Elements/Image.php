<?php namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class Image {
    
    use HelperTrait, AttachTrait, DeleteTrait;
    
    protected $action;
    protected $uploadedIds = [];
    protected $type = 'image';    
    
    public function __construct()
    {
        \Eloquent::unguard();
    }

    public static function request()
    {
        $class = new static;
        $class->action = Input::get('action');

        foreach(array_keys(Input::all()) as $k) {
            if (Input::hasFile($k)) { // @todo attention
                $class->uploadedIds = array_merge($class->uploadedIds, $class->upload('image', $k, null, null, '', null, true));
                event('veer.message.center', trans('veeradmin.image.upload'));
            }
        }

        !Input::has('attachImages') ?: $class->attachImages(Input::get('attachImages'));
        !starts_with($class->action, 'deleteImage') ?: $class->delete(substr($class->action, 12));
    }

    public function add($data, $relation = null, $attach_id = null, $returnId = true)
    {
        $prefix = 'img';
        switch($relation) {
            case 'products': $prefix = 'prd'; break;
            case 'pages': $prefix = 'pg'; break;
            case 'categories': $prefix = 'ct'; break;
            case 'users': $prefix = 'usr'; break;
        }
        
        $id = $this->upload('image', 'uploadImage', $attach_id, $relation, $prefix, null, empty($relation) ? true : false, $data);

        return $returnId ? $id : $this;
    }

    public function delete($id)
    { 
        if(!empty($id) && $this->deleteImage($id)) {
            event('veer.message.center', trans('veeradmin.image.delete'));
        }

        return $this;
    }

    // @todo test, just helper
    // @todo move to attachtrait (used by download too)
    protected function attachImages($data)
    {
        if(empty($data)) {
            return $this;
        }

        $parseTypes = $this->parseForm($data);
        $attach = [];
        if(!empty($parseTypes['target']) && is_array($parseTypes['target'])) {
            foreach($parseTypes['target'] as $t) {
                $t = trim($t);
                if (empty($t) || $t == "NEW") {
                    if (!empty($this->uploadedIds)) {
                        $attach = array_merge($attach, $this->uploadedIds);
                    }
                    continue;
                }
                $attach[] = $t;
            }
        }

        $this->attachFromForm($parseTypes['elements'], $attach, 'images');
        event('veer.message.center', trans('veeradmin.image.attach'));

        return $this;
    }    
}
