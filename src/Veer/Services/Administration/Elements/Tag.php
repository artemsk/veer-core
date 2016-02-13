<?php namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class Tag {
    
    use DeleteTrait, AttachTrait, HelperTrait;
    
    protected $action;
    protected $type = 'tag';
    protected $entity;
    
    public function __construct()
    {   
        \Eloquent::unguard();    
    }

    public static function request()
    {
        $class = new static;
        $class->action = Input::get('action');

        !starts_with($class->action, "deleteTag") ?: $class->delete(substr($class->action, 10));
        !Input::has('renameTag') ?: $class->renameTags(Input::get('renameTag'));
        !Input::has('newTag') ?: $class->newTags(Input::get('newTag'));

        event('veer.message.center', trans('veeradmin.tag.update'));
    }

    public function delete($id)
    {
        if(!empty($id) && $this->deleteTag($id)) {
            event('veer.message.center', trans('veeradmin.tag.delete'));
        }

        return $this;
    }

    public function rename($old, $new)
    {
        $tagExists = \Veer\Models\Tag::where('name', '=', $new)->first();
        if(!is_object($tagExists)) {
            \Veer\Models\Tag::where('name', '=', $old)->update([
                'name' => $new
            ]);
        }

        return $this;
    }
    
    protected function renameTags($data)
    {
		if(!empty($data) || !is_array($data)) {
            return $this;
        }

        foreach($data as $key => $value) {
            $value = trim($value);
            $tagDb = \Veer\Models\Tag::where('name', '=', $value)->first();            
            if(!is_object($tagDb)) { // if the same value doesn't exist
                \Veer\Models\Tag::where('id', '=', $key)->update(['name' => $value]);
            }
        }

        return $this;
    }

    // form
    protected function newTags($data)
    {
        $new = $this->parseForm($data);

        if(!is_array($new['target'])) {
            return $this;
        }
        
        foreach($new['target'] as $tag) {
            $tag = trim($tag);
            if(empty($tag)) { continue; }
            $tagDb = \Veer\Models\Tag::firstOrNew(['name' => $tag]);
            $tagDb->save();
            $tags[] = $tagDb->id;
        }
        
        if(isset($tags)) {
            $this->attachFromForm($new['elements'], $tags, 'tags');
        }

        return $this;
    }

    public function add($tag)
    {
        $this->entity = \Veer\Models\Tag::firstOrNew(['name' => $tag]);
        
        $this->entity->save();
    }

    public function attach($id, $type = 'page', $toTag = null)
    {
        $class = elements($type);
        $object = $class::find($id);

        if(!empty($toTag)) { $this->entity = \Veer\Models\Tag::find($id); }

        if(is_object($object) && is_object($this->entity)) {
            $object->tags()->attach($this->entity->id);
        }

        return $this;
    }

    /**
     * Get current entity object.
     *
     * @return object
     */
    public function getEntity()
    {
        return $this->entity;
    }
}
