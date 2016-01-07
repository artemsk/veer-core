<?php

namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class Page extends Entity {

    protected $type = 'page';
    protected $className = \Veer\Models\Page::class;

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
     * @param array $data
     * @return \Veer\Services\Administration\Elements\Page
     */
    public function add($data)
    {
        if (empty($data) || !is_array($data)) {
            return $this;
        }
        
        $data += ['url' => '', 'txt' => '', 'categories' => null, 'title' => ''];        
        $txt = preg_replace("/{{(?s).*}}/", "", $data['txt'], 1); // TODO: test preg:
        preg_match("/{{(?s).*}}/", $data['txt'], $small);

        $max = \DB::table((new $this->className)->getTable())->max('manual_order');
        $fill = [
            'title' => trim($data['title']),
            'url' => $data['url'],
            'hidden' => 1,
            'manual_order' => $max + 10,
            'users_id' => \Auth::id(),
            'small_txt' => !empty($small[0]) ? substr(trim($small[0]), 2, -2) : '',
            'txt' => trim($txt)
        ];
        
        $page = $this->create($fill);        
        
        $categories = explode(',', $data['categories']);
        if(!empty($categories)) {
            $page->categories()->attach($categories);
        }

        if(Input::hasFile('uploadImage')) {
            $this->upload('image', 'uploadImage', $page->id, 'pages', 'pg', null);
        }

        if(Input::hasFile('uploadFile')) {
            $this->upload('file', 'uploadFile', $page->id, $page, 'pg', null);
        }

        $this->id = $page->id;
        $this->entity = $page;        
        return $this;
    }

    /**
     * @param array $data
     * @return \Veer\Services\Administration\Elements\Page
     */
    public function sort($data)
    {
        if(empty($data)) {
            return $this;
        }
        
        $url_params = array_get($data, '_refurl');
        $parse_str = $data;
        if(!empty($url_params)) {
            parse_str(starts_with($url_params, '?') ? substr($url_params, 1) : $url_params, $parse_str);
            if(!empty($parse_str['page'])) {
                \Input::merge(['page' => $parse_str['page']]); // TODO: check & test
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
            $fill[$field] = isset($fill[$field]) ? 1 : 0; 
        }
        
        $fill['users_id'] = empty($fill['users_id']) ? \Auth::id() : $fill['users_id'];
        $fill['url'] = trim($fill['url']); 
        return $fill;
    }  
}
