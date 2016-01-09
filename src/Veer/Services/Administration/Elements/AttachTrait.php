<?php namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

trait AttachTrait {
       
    /**
     * Attach Elements
     * 
     */
    protected function attachElements($ids, $object, $relation, $message = [], $separator = ",", $start = ":", $replace = false)
    {
        $elements = !is_array($ids) ? $this->parseIds($ids, $separator, $start) : $ids;

        if(is_array($elements)) {         
            $method = $replace == true ? 'sync' : 'attach';
            
            $object->{$relation}()->{$method}($elements);

            if(!empty($message)) { event('veer.message.center', trans(array_get($message, 'language', 'veeradmin.empty'))); }
            return true;
        }
    }

    /**
     * Attach Attributes
     * 
     */
    protected function attachAttributes($attributes, $object, $replace = true)
    {
        if(!is_array($attributes)) { $attributes = (array) $attributes; }

        \Eloquent::unguard();
        $attrArr = [];
        foreach($attributes as $a) {
            if(!is_array($a)) {
                $a = (array) $a;
            }
            $a += ['name' => null, 'val' => '', 'type' => 'descr'];            
            if(empty($a['name'])) { continue; }

            $attr = \Veer\Models\Attribute::firstOrNew([
                "name" => $a['name'],
                "val" => $a['val'],
                "type" => $a['type']
            ]);

            if(!$attr->exists) {
                $attr->name = $a['name'];
                $attr->val = $a['val'];
                $attr->type = $a['type'];
                $attr->descr = array_get($a, 'descr', '');
                $attr->save();
            }
            
            $attrArr[$attr->id] = ["product_new_price" => array_get($a, 'price', '')];
        }

        $this->attachElements($attrArr, $object, 'attributes', null, ",", ":", $replace);
    }

    /**
     * Attach Tags
     * 
     */
    protected function attachTags($tags, $object, $separator = ',', $replace = true)
    {
        \Eloquent::unguard();
        $tagArr = [];
        
        if(is_array($tags)) {
            $matches[1] = $tags;
        } elseif(!empty($separator)) {
            $matches[1] = explode($separator, $tags);
        } else {
            preg_match_all("/^(.*)$/m", trim($tags), $matches);
        }        

        if(!empty($matches[1]) && is_array($matches[1])) {         
            foreach($matches[1] as $tag) {
                $tag = trim($tag);
                if(empty($tag)) { continue; }

                $tagDb = \Veer\Models\Tag::firstOrNew(['name' => $tag]);
                if(!$tagDb->exists) {
                    $tagDb->name = $tag;
                    $tagDb->save();
                }
                $tagArr[] = $tagDb->id;
            }
        }
        $this->attachElements($tagArr, $object, 'tags', null, ",", ":", $replace);
    }

    /**
     * Update Attributes Connections
     * @todo Test
     */
    protected function attachToAttributes($name, $form)
    {
        $new = $this->parseForm($form);
        if(!is_array($new['target'])) { return null; }
        
        foreach($new['target'] as $a) {
            $a = trim($a);            
            if(empty($a)) { continue; }
            
            if(starts_with($a, ":")) {
                // id
                $aDb = \Veer\Models\Attribute::find(substr($a, 1));
                if(!is_object($aDb)) { continue; }
            } else {
                // string values
                $aDb = \Veer\Models\Attribute::firstOrNew([
                    'name' => $name,
                    'val' => $a                    
                ]);
                $aDb->type = empty($aDb->type) ? 'descr' : $aDb->type;
                $aDb->descr = empty($aDb->descr) ? '' : $aDb->descr;
                $aDb->save();
            }            
            
            $attributes[] = $aDb->id;
        }
        
        if(isset($attributes)) { $this->attachFromForm($new['elements'], $attributes, 'attributes'); }
    }

    /**
     * Parsing free form for tag|image connections
     * @todo Test
     * Ex.: values, values, values [:id,id:id,id:id,id]
     */
    protected function parseForm($textarea)
    {
        $small = '';
        preg_match("/\[(?s).*\]/", $textarea, $small);
        $parseTypes = explode(":", substr(array_get($small, 0, ''), 2, -1));
        $parseAttach = explode("[", $textarea);
        $attach = explode(",", trim(array_get($parseAttach, 0)));

        return ['target' => $attach, 'elements' => $parseTypes];
    }
    
    /**
     * Attach Based on Form Input
     * @todo Test
     * [id,id,id] [id,id,id] [id,id,id] 
     */
    protected function attachFromForm($str, $attach, $type)
    {
        $models = ['Product', 'Page', 'Category', 'User'];
        
        foreach(is_array($str) ? $str : [] as $k => $v) {
            if($k > 3) continue;
            $p = explode(",", $v);
            
            foreach($p as $id) {
                $class = "\\Veer\\Models\\".$models[$k];
                $object = $class::find($id);
                                
                if(is_object($object)) { $this->attachElements($attach, $object, $type, null); }
            }
        }
    }

    /**
     * Attach Parent Category
     * 
     */
    protected function attachParentCategory($cid, $parent_id, $category)
    {
        $check = \Veer\Models\CategoryPivot::where('child_id', '=', $cid)
                ->where('parent_id', '=', $parent_id)->first();

        if(!$check) {
            $category->parentcategories()->attach($parent_id);
            event('veer.message.center', trans('veeradmin.category.parent.new'));
        }
    }

    /**
	 * Associate (belongTo, hasMany relationships)
	 * - updating parents (parent field) in childs tables
	 *
	 * @param string $relation Child model, ex: page, user, product etc.
	 * @param array $childs Ids
	 * @param string $childsField
	 * @param int $parentId
	 * @param string $parentField
	 * @param string $raw Raw where Sql
	 * @return void
	 */
	protected function associate($relation, $childs, $parentId, $parentField, $childsField = "id", $raw = null)
	{
		$relation = "\\" . elements(str_singular($relation));
		$r = $relation::whereIn($childsField, $childs);
		if(!empty($raw)) { $r->whereRaw($raw); }
		$r->update([$parentField => $parentId]);
	}
    
    /*
    abstract protected function parseIds($ids, $separator = ",", $start = ":");
     */
}
