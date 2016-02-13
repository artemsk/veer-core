<?php namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class Download {

    use HelperTrait, AttachTrait, DeleteTrait;
    
    protected $action;
    protected $uploadedIds = [];
    protected $type = 'download';

    protected $entity;

    public function __construct()
    {
        \Eloquent::unguard();
    }

    public static function request()
    {
        $class = new static;
        $class->action = Input::get('action');
        list($command, $id) = array_pad(explode('.', $class->action, 2), 2, null);

        switch($command) {
            case 'removeFile':
                $class->remove($id);
                break;
            case 'deleteFile':
                $class->delete($id);
                break;
            case 'makeRealLink':
                $class->mklink($id, Input::all());
                break;
            case 'copyFile':
                $class->copyFileToPagesOrProducts($id, Input::all());
                break;
        }

        !Input::hasFile('uploadFiles') ?:
                $class->uploadedIds[] = $class->upload('file', 'uploadFiles', null, null, '', null, true);

        !Input::has('attachFiles') ?: $class->copyFilesToPagesOrProductsFromForm(Input::get('attachFiles'));
    }

    public function add($data, $relation = null, $attach_id = null, $returnId = true)
    {
        $prefix = 'fl';
        $model = null;
        switch($relation) {
            case 'products':
                $model = \Veer\Models\Product::find($attach_id);
                $prefix = 'prd';
                break;
            case 'pages':
                $model = \Veer\Models\Page::find($attach_id);
                $prefix = 'pg';
                break;
            case 'categories':
                $model = \Veer\Models\Category::find($attach_id);
                $prefix = 'ct';
                break;
            case 'users':
                $model = \Veer\Models\User::find($attach_id);
                $prefix = 'usr';
                break;
        }

        $id = $this->upload('image', 'uploadImage', $attach_id, $model, $prefix, null, is_object($model) ? false : true, $data);
        return $returnId ? $id : $this;
    }

    public function remove($id)
    {
        if(!empty($id)) {
            \Veer\Models\Download::where('id', '=', $id)
                    ->update(['elements_id' => 0, 'elements_type' => '']);
        }

        return $this;
    }

    public function delete($id)
    {
        if(!empty($id) && $this->deleteFile($id)) {
            event('veer.message.center', trans('veeradmin.file.delete'));
            // @todo restore link?
        }

        return $this;
    }
    
    public function mklink($id = null, $data = null, $returnId = false)
    {
        if(empty($id) && !isset($this->entity->id)) {
            return $this;
        } elseif(empty($id)) {
            $id = $this->entity->id;
        }

        $data = (array) $data;
        $data += ['times' => 0, 'expiration_day' => null, 'link_name' => null];
        
        $file = is_object($this->entity) ? $this->entity : \Veer\Models\Download::find($id);
        if(!is_object($file)) {
            return $this;
        }

        $new = $file->replicate();
        $new->secret = empty($data['link_name']) ? bcrypt(str_random(100) . date("Ymd", time())) :
            $data['link_name']; // @todo test
        
        if($data['times'] > 0 || !empty($data['expiration_day'])) {
            $new->expires = 1;
            $new->expiration_times = $data['times'];
            if(!empty($data['expiration_day'])) {
                $new->expiration_day = \Carbon\Carbon::parse(strtotime($data['expiration_day']));
            }
        }

        $new->original = 0;
        $new->save();        
        event('veer.message.center', trans('veeradmin.file.download'));

        return $returnId ? $new->id : $this;
    }

    public function copy($id, $type, $ids = [])
    {
        if(empty($id)) {
            return $this;
        }

        $pages_ids = $products_ids = [];
        if($type == 'pages') {
            $pages_ids = (array) $ids;
        } elseif($type == 'products') {
            $products_ids = (array) $ids;
        } else {
            return $this;
        }

        if(!empty($id)) {
            $this->prepareCopying($id, $products_ids, $pages_ids);
        }

        return $this;
    }
    
    protected function copyFileToPagesOrProducts($id, $data = null)
    {
        if(empty($id)) {
            return $this;
        }

        $data = (array) $data;
        $data += ['prdId' => [], 'pgId' => []];
        
        $prdIds = is_array($data['prdId']) ? $data['prdId'] : explode(",", $data['prdId']);
        $pgIds = is_array($data['pgId']) ? $data['pgId'] : explode(",", $data['pgId']);
        $this->prepareCopying($id, $prdIds, $pgIds);

        event('veer.message.center', trans('veeradmin.file.copy'));

        return $this;
    }

    // helper for form
    protected function copyFilesToPagesOrProductsFromForm($data)
    {
        if(empty($data) || !is_array($data)) {
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

        $prdIds = explode(",", array_get($parseTypes, 'elements.0'));
        $pgIds = explode(",", array_get($parseTypes, 'elements.1'));
        foreach ($attach as $f) {
            $this->prepareCopying($f, $prdIds, $pgIds);
        }
        
        event('veer.message.center', trans('veeradmin.file.attach'));

        return $this;
    }

    /**
     * @param \Veer\Models\Download $file
     * @return \Veer\Services\Administration\Elements\Download
     */
    public function set(\Veer\Models\Download $file)
    {
        $this->entity = $file;
        
        return $this;
    }
}
