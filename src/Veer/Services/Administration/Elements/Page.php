<?php

namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class Page extends Entity {

    /**
     * 
     * @var string
     */
    protected $type = 'page';

    /**
     *
     * @var string
     */
    protected $className = \Veer\Models\Page::class;

    /**
     * Actions for pages based on Request. Triggers are hardcoded.
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

        $changeStatusPage = starts_with($class->action, "changeStatusPage") ? substr($class->action, 17) : null;
        $deletePage = starts_with($class->action, "deletePage") ? substr($class->action, 11) : null;
        $quickAddPage = Input::has('title') ? Input::all() : null;
        $sortPages = $class->action == 'sort' ? Input::all() : null;

        $class->toggleStatus($changeStatusPage)
            ->delete($deletePage)
            ->add($quickAddPage)
            ->sort($sortPages);
    }
           
    /**
     * Change Page status - hidden or not.
     *
     * @param int $id
     * @return \Veer\Services\Administration\Elements\Page
     */
    public function toggleStatus($id)
    {
        if(empty($id)) {
            return $this;
        }

        $page = $this->entity instanceof $this->className &&
            $this->entity->id == $id ? $this->entity : \Veer\Models\Page::find($id);
        
        if(is_object($page)) {
            $page->hidden = $page->hidden == true ? false : true;
            $page->save();

            event('veer.message.center', trans('veeradmin.page.status'));
        }

        return $this;
    }

    /**
     * Delete Page with relations.
     * 
     * @helper Model delete
     * @param int $id
     * @return \Veer\Services\Administration\Elements\Page
     */
    public function delete($id)
    {
        if(!empty($id) && $this->deletePage($id)) {
            event('veer.message.center', trans('veeradmin.page.delete') .
                " " . $this->restore_link('page', $id));
        }

        return $this;           
    }

    /**
     * Add Page with relations.
     * 
     * @helper Model create
     * @param array $data
     * @return \Veer\Services\Administration\Elements\Page
     */
    public function add($data)
    {
        if (empty($data) || !is_array($data)) {
            return $this;
        }
        
        $data += ['url' => '', 'txt' => '', 'categories' => null, 'title' => ''];        
        $txt = preg_replace("/{{(?s).*}}/", "", $data['txt'], 1); // @todo test preg:
        preg_match("/{{(?s).*}}/", $data['txt'], $small);
        $small_txt = !empty($small[0]) ? trim(substr(trim($small[0]), 2, -2)) :
            (!empty($data['small_txt']) ? $data['small_txt'] : '');

        $max = \DB::table((new $this->className)->getTable())->max('manual_order');
        $fill = [
            'title' => trim($data['title']),
            'url' => trim($data['url']),
            'hidden' => 1,
            'manual_order' => $max + 10,
            'users_id' => \Auth::id(),
            'small_txt' => trim($small_txt),
            'txt' => trim($txt)
        ];
        
        $page = $this->create($fill);        
        
        $categories = explode(',', $data['categories']);
        if(!empty($categories)) {
            $page->categories()->attach($categories);
        }

        $this->id = $page->id;
        $this->entity = $page;

        $this->image(array_get($data, 'uploadImage'));
        $this->file(array_get($data, 'uploadFiles'));
   
        return $this;
    }

    /**
     * Sort pages. Max 24 per batch (depends on pagination).
     *
     * @param array $data
     * @return \Veer\Services\Administration\Elements\Page
     */
    public function sort($data)
    {
        if(empty($data) || !is_array($data)) {
            return $this;
        }
        
        $url_params = array_get($data, '_refurl');
        $parse_str = $data;
        if(!empty($url_params)) {
            parse_str(starts_with($url_params, '?') ? substr($url_params, 1) : $url_params, $parse_str);
            if(!empty($parse_str['page'])) {
                \Input::merge(['page' => $parse_str['page']]);
            }
        }

        $parse_str += ['filter' => null, 'filter_id' => null, 'sort' => null, 'sort_direction' => null, 'page' => 1];

        $pages = new \Veer\Services\Show\Page;
        $oldsorting = $pages->getAllPages([
            [$parse_str['filter'] => $parse_str['filter_id']],
            [$parse_str['sort'] => $parse_str['sort_direction']]
        ]);

        if (is_object($oldsorting)) {
            $bottom_sort = $oldsorting[count($oldsorting) - 1]->manual_order;
            $sort = $oldsorting[0]->manual_order;
            foreach($this->sortElementsEntities($oldsorting, $data) as $id) {
                if($sort < $bottom_sort && $parse_str['sort_direction'] == 'desc') { $sort = $bottom_sort; }
                if($sort > $bottom_sort && $parse_str['sort_direction'] == 'asc') { $sort = $bottom_sort; }
                \Veer\Models\Page::where('id', '=', $id)->update(['manual_order' => $sort]);
                if($parse_str['sort_direction'] == 'desc') { $sort--; } else { $sort++; }
            }
        }

        return $this;
    }

    /**
     * @param array $fill
     * @return array
     */
    protected function prepareData($fill)
    {
        foreach(['original', 'show_small', 'show_comments', 'show_title', 'show_date', 'in_list'] as $field) {
            $fill[$field] = !empty($fill[$field]) ? 1 : 0;
        }
        
        $fill['users_id'] = empty($fill['users_id']) ? \Auth::id() : $fill['users_id'];
        if(!empty($fill['url'])) { 
            $fill['url'] = trim($fill['url']);
        }
        return $fill;
    }
    
}
