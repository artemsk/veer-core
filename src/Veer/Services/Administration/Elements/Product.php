<?php

namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class Product extends Entity {

    protected $type = 'product';
    protected $className = \Veer\Models\Product::class;
    
    /**
     *
     * @return void
     */
    public static function request()
    {
        $class = new static;
        $class->action = Input::get('action');

        if (Input::has('id')) {
            return $class->one();
        }

        $changeStatusProduct = starts_with($class->action, "changeStatusProduct") ? substr($class->action, 20) : null;
        $deleteProduct = starts_with($class->action, "deleteProduct") ? substr($class->action, 14) : null;
        $showEarlyProduct = starts_with($class->action, "showEarlyProduct") ? substr($class->action, 17) : false;
        $quickAddPage = Input::has('fill.title') ? Input::all() : null;

        $class->toggleStatus($changeStatusProduct)
            ->delete($deleteProduct)
            ->available($showEarlyProduct)
            ->add($quickAddPage)
            ->quickFreeForm(Input::get('freeForm'));
    }

    /**
     * @param int $id
     * @return \Veer\Models\Product|boolean
     */
    protected function getObject($id)
    {
        if(empty($id)) {
            return false;
        }

        $product = $this->entity instanceof $this->className &&
            $this->entity->id == $id ? $this->entity : \Veer\Models\Product::find($id);

        return is_object($product) ? $product : false;
    }

    /**
     * @param int $id
     * @return \Veer\Services\Administration\Elements\Product
     */
    public function toggleStatus($id)
    {
        $product = $this->getObject($id);
        if($product) {
            $this->changeProductStatus($product);
            event('veer.message.center', trans('veeradmin.product.status'));
        }

        return $this;
    }

    /**
     * @param int $id
     * @return \Veer\Services\Administration\Elements\Product
     */
    public function delete($id)
    {
        if(!empty($id) && $this->deleteProduct($id)) {
            event('veer.message.center', trans('veeradmin.product.delete') .
                " " . $this->restore_link('product', $id));
        }

        return $this;
    }

    /**
     * @param int|null $id
     * @param timestamp|null $when
     * @return \Veer\Services\Administration\Elements\Product
     */
    public function available($id = null, $when = null)
    {
        if($id === false) {
            return $this;
        }

        if(empty($id)) {
            $id = $this->id;
        }

        if(empty($when)) {
            $when = now();
        }

        if($this->entity instanceof $this->className && $this->entity->id == $id) {
            $this->entity->to_show = $when;
            $this->entity->save();
        } else {
            \Veer\Models\Product::where('id', '=', $id)->update(['to_show' => $when]);
        }

        event('veer.message.center', trans('veeradmin.product.show'));
        return $this;
    }

    /**
     * @param array $data
     * @return array
     */
    protected function prepareDataForQuickAdd($data)
    {
        $prices = explode(":", $data['prices']);
        $options = explode(":", $data['options']);
        $fill = [
            'title' => array_get($data, 'fill.title'),
            'url' => !empty($data['fill']['url']) ? trim($data['fill']['url']) : '',
        ];

        foreach(['price', 'price_sales', 'price_opt', 'price_base', 'currency'] as $i => $type) {
            $fill[$type] = array_get($prices, $i, 0);
        }

        foreach(['qty', 'weight', 'score', 'star'] as $i => $type) {
            $fill[$type] = array_get($options, $i, 0);
        }

        $fill['production_code'] = array_get($options, 4, '');
        $fill['status'] = 'hide';
        return $fill;
    }

    /**
     * @param array $data
     * @return \Veer\Services\Administration\Elements\Product
     */
    public function add($data)
    {
        if(empty($data) || !is_array($data)) {
            return $this;
        }
        
        $data += ['prices' => '', 'options' => '', 'categories' => ''];
        $fill = $this->prepareDataForQuickAdd($data);
        $product = $this->create($fill);
        $categories = explode(',', $data['categories']);
        if(!empty($categories)) {
            $product->categories()->attach($categories);
        }
        
        if(Input::hasFile('uploadImage')) {
            $this->upload('image', 'uploadImage', $product->id, 'products', 'prd', null);
        }

        if(Input::hasFile('uploadFile')) {
            $this->upload('file', 'uploadFile', $product->id, $product, 'prd', null);
        }		

        $this->id = $product->id;
        $this->entity = $product;        
        return $this;
    }

    /**
     * @param string $data
     * @return \Veer\Services\Administration\Elements\Product
     */
    public function quickFreeForm($data)
    {
        if(empty($data)) {
            return $this;
        }

        preg_match_all("/^(.*)$/m", trim($data), $parseff);
        if(empty($parseff[1]) || !is_array($parseff[1])) {
            return $this;
        }

        foreach($parseff[1] as $p) {
            $fields = explode("|", $p);

            $fill = [];
            foreach(['title', 'url', 'categories', 'qty', 'weight', 'currency', 'price',
                'price_sales', 'price_opt', 'price_base', 'price_sales_on', 'price_sales_off',
                'to_show', 'score', 'star', 'image', 'file', 'production_code', 'status', 'descr'] as $i => $type) {
                
                switch($type) {
                    case 'categories':
                        $categories = explode(",", array_get($fields, $i, ''));
                        break;
                    case 'image':
                    case 'file':
                        ${$type} = array_get($fields, $i);
                        break;
                    case 'descr':
                        $fill[$type] = substr(array_get($fields, $i, ''), 2, -2);
                        break;
                    case 'status':
                        $fill[$type] = array_get($fields, $i, 'hide');
                        break;
                    default:
                        $fill[$type] = array_get($fields, $i, 0);
                        break;
                }   
            }
            
            $product = $this->create($fill);
            $this->id = $product->id;
            $this->entity = $product;
            empty($categories) ?: $product->categories()->attach($categories);
            empty($image) ?: $this->attachImage($image);
            empty($file) ?: $this->attachFile($file);
        }	
        
        event('veer.message.center', trans('veeradmin.product.new'));
        return $this;
    }

    /**
     * @param string $image
     * @return \Veer\Services\Administration\Elements\Product
     */
    public function attachImage($image)
    {
        $new = new \Veer\Models\Image; 
        $new->img = $image;
        $new->save();
        $new->products()->attach($this->id);
        
        return $this;
    }

    /**
     * @param string $file
     * @return \Veer\Services\Administration\Elements\Product
     */
    public function attachFile($file)
    {
        $new = new \Veer\Models\Download; 
        $new->original = 1;
        $new->fname= $file;
        $new->expires = 0;
        $new->expiration_day = 0;
        $new->expiration_times = 0;
        $new->downloads = 0;
        $this->entity->downloads()->save($new);
        
        return $this;
    }
       
    protected function prepareData($fill)
    {
        $fill['star'] = isset($fill['star']) ? 1 : 0;
        $fill['download'] = isset($fill['download']) ? 1 : 0;
        $fill['url'] = trim($fill['url']);
        $fill['price_sales_on'] = parse_form_date(array_get($fill, 'price_sales_on', 0));
        $fill['price_sales_off'] = parse_form_date(array_get($fill, 'price_sales_off', 0));

        $toShow = parse_form_date(array_get($fill, 'to_show', 0));
        $toShow->hour((int) array_get($this->data, 'to_show_hour', 0));
        $toShow->minute((int) array_get($this->data, 'to_show_minute', 0));

        $fill['to_show'] = $toShow;
        return $fill;
    }
}
