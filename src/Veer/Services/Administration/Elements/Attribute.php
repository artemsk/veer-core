<?php

namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class Attribute {

    use DeleteTrait, HelperTrait, AttachTrait;
    
    protected $action;
    protected $type = 'attribute';
    
    public function __construct()
    {
        \Eloquent::unguard();
    }

    /**
     * Actions for attributes based on Request.
     * @return void
     */
    public static function request()
    {
        $class = new static;
        $class->action = Input::get('action');

        $newValue = Input::get('newValue');
        $newName = Input::get('newName');        
        $renameAttrName = Input::get('renameAttrName');

        !starts_with($class->action, "deleteAttrValue") ?: $class->delete(substr($class->action, 16));
        $class->action != 'newAttribute' ?: $class->add($newName, $newValue);
        $class->rename($renameAttrName)
            ->update(Input::all());

        event('veer.message.center', trans('veeradmin.attribute.update'));
    }

    /**
     * Delete an Attribute.
     *
     * @todo restore link ?
     * @helper Model delete
     * @param int $id
     * @return \Veer\Services\Administration\Elements\Attribute
     */
    public function delete($id)
    {
        if(!empty($id) && $this->deleteAttribute($id)) {
            event('veer.message.center', trans('veeradmin.attribute.delete'));
        }

        return $this;
    }

    /**
     * Add a new Attribute with values.
     *
     * @todo test preg_match_all
     * @todo test adding because of strange freeform methods
     * 
     * @param string $name
     * @param mixed $value
     * @return \Veer\Services\Administration\Elements\Attribute
     */
    public function add($name, $value)
    {
        if(is_array($value)) {
            $manyValues[1] = $value;
        } else {
            preg_match_all("/^(.*)$/m", trim($value), $manyValues);
        }
        
        if(!empty($manyValues[1]) && is_array($manyValues[1]) && !empty($name)) {
            foreach ($manyValues[1] as $v) {
                $this->attachToAttributes($name, $v);
            }

            event('veer.message.center', trans('veeradmin.attribute.new'));
        }

        return $this;
    }

    /**
     * Rename an Attribute's name
     *
     * @helper Model update
     * @param array $data
     * @return \Veer\Services\Administration\Elements\Attribute
     */
    public function rename($data)
    {
        if(!empty($data) && is_array($data)) {
            foreach($data as $k => $v) {
                if ($k != $v) {
                    \Veer\Models\Attribute::where('name', '=', $k)->update(['name' => $v]);
                }
            }
        }

        return $this;
    }

    /**
     * Update an Attribute's date by id.
     *
     * @todo rewrite? not necessary as it is a helper method, for norma update eloquent can be used
     * @helper Model update
     * @param array $data
     * @return \Veer\Services\Administration\Elements\Attribute
     */
    public function update($data)
    {
        if(empty($data) || !is_array($data)) {
            return $this;
        }
        
        $data += ['renameAttrValue' => [], 'descrAttrValue' => [], 'attrType' => [], 'newAttrValue' => []];
            
        foreach($data['renameAttrValue'] as $k => $v) {
            $type = array_get($data['attrType'], $k);
            
            \Veer\Models\Attribute::where('id', '=', $k)->update([
                'val' => $v,
                'descr' => array_get($data['descrAttrValue'], $k, ''),
                'type' => ($type == 'descr' || $type == 1) ? 'descr' : 'choose'
            ]);
        }

        foreach($data['newAttrValue'] as $k => $v) { 
            $this->attachToAttributes($k, $v);
        }

        return $this;
    }
    
}
