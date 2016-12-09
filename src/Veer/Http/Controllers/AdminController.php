<?php

namespace Veer\Http\Controllers;

use Veer\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input as Input;

class AdminController extends Controller
{

    public function __construct()
    {
        parent::__construct();

        $this->middleware('auth');
        $this->middleware('auth.admin');

        app('veer')->loadedComponents['template'] = app('veer')->template = $this->template
            = config('veer.template-admin');

        app('veer')->isBoundSite = false;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        return redirect()->route('admin.show', 'sites');
        
        /*return view(app('veer')->template.'.dashboard',
            array(
            "template" => app('veer')->template
        ));*/
    }

    /**
     * Display the specified resource.
     *
     * @param string $model
     */
    public function show($model)
    {
        $specialRoute = $this->specialRoutes($model);
        if(!empty($specialRoute)) { return $specialRoute; }

        if(in_array($model, ['categories', 'pages', 'products', 'users', 'orders'])) {
            $model = $this->checkOnePageEntities($model);
        }                

        $items = $this->getItems($model);
        if(!empty($items)) {
            return $this->sendViewOrJson($items, $model == 'lists' ? 'userlists' : $model);
        }
    }

    /**
     * special routes: search or restore
     */
    protected function specialRoutes($model)
    {
        if(Input::has('SearchField')) {
            $search = (new \Veer\Services\Show\Search)->searchAdmin($model);

            if(is_object($search)) { return $search; }
        }

        if($model == "restore") {
            $this->restore(Input::get('type'), Input::get('id'));
            return back();
        }
    }

    /**
     * get Items
     */
    protected function getItems($model)
    {
        $data = $this->getRouteParams($model, [Input::get('filter') => Input::get('filter_id')]);
        if(extract($data)) {
            $params = !empty($params) ? $params : null;
            return (new $class)->{$method}($params);
        }

        logger('Not found admin route: ' . $model);
    }

    /**
     * send response - view or simple json
     */
    protected function sendViewOrJson($items, $view)
    {
        if (null != Input::get('_json')) return response()->json($items);

        /* for admin we always use 'view' instead of 'viewx' */
        return view($this->template.'.'.$view,
            array(
            "items" => $items,
            "template" => $this->template
        ));
    }

    /**
     * check entities which have separate page for single entity
     */
    protected function checkOnePageEntities($model)
    {
        $check = $model == "categories" ? Input::get('category') : Input::get('id');

        return empty($check) ? $model : str_singular($model);
    }

    /**
     * configure administration routes
     */
    protected function getRouteParams($model, $filters)
    {
        $data = [];
        switch($model) {
            case 'sites': return ['class' => \Veer\Services\Show\Site::class, 'method' => 'getSites'];

            case 'categories': $data += ['method' => 'getAllCategories', 'params' => Input::get('image')];
            case 'category': $data += ['method' => 'getCategoryAdvanced', 'params' => Input::get('category')];
                return $data += ['class' => \Veer\Services\Show\Category::class];

            case 'pages': $data += ['method' => 'getAllPages', 'params' => [$filters, [Input::get('sort') => Input::get('sort_direction')]]];
            case 'page': $data += ['method' => 'getPageAdvanced', 'params' => Input::get('id')];
                return $data += ['class' => \Veer\Services\Show\Page::class];

            case 'products': $data += ['method' => 'getAllProducts', 'params' => [$filters, [Input::get('sort') => Input::get('sort_direction')]]];
            case 'product': $data += ['method' => 'getProductAdvanced', 'params' => Input::get('id')];
                return $data += ['class' => \Veer\Services\Show\Product::class];

            case 'images': return ['class' => \Veer\Services\Show\Image::class, 'method' => 'getImages', 'params' => $filters];
            case 'attributes': return ['class' => \Veer\Services\Show\Attribute::class, 'method' => 'getUngroupedAttributes'];
            case 'tags': return ['class' => \Veer\Services\Show\Tag::class, 'method' => 'getTagsWithoutSite'];
            case 'downloads': return ['class' => \Veer\Services\Show\Download::class, 'method' => 'getDownloads'];

            case 'users': $data += ['method' => 'getAllUsers', 'params' => $filters];
            case 'user': $data += ['method' => 'getUserAdvanced', 'params' => Input::get('id')];
                return $data += ['class' => \Veer\Services\Show\User::class];

            case 'books': $data += ['method' => 'getBooks', 'params' => $filters];
            case 'lists': $data += ['method' => 'getLists', 'params' => $filters]; // userlists view
            case 'searches': $data += ['method' => 'getSearches', 'params' => $filters];
            case 'communications': $data += ['method' => 'getCommunications', 'params' => $filters];
            case 'comments': $data += ['method' => 'getComments', 'params' => $filters];
            case 'roles': $data += ['method' => 'getRoles', 'params' => $filters];
                return $data += ['class' => \Veer\Services\Show\UserProperties::class];

            case 'orders':$data += ['method' => 'getAllOrders', 'params' => $filters];
            case 'order':$data += ['method' => 'getOrderAdvanced', 'params' => Input::get('id')];
                return $data += ['class' => \Veer\Services\Show\Order::class];

            case 'bills': $data += ['method' => 'getBills', 'params' => $filters];
            case 'discounts': $data += ['method' => 'getDiscounts', 'params' => $filters];
            case 'shipping': $data += ['method' => 'getShipping', 'params' => $filters];
            case 'payment': $data += ['method' => 'getPayment', 'params' => $filters];
            case 'statuses': $data += ['method' => 'getStatuses', 'params' => null];
                return $data += ['class' => \Veer\Services\Show\OrderProperties::class];
            
            case 'configuration': $data += ['method' => 'getConfiguration', 'params' => Input::get('site')];
            case 'components': $data += ['method' => 'getComponents', 'params' => Input::get('site')];
            case 'secrets': $data += ['method' => 'getSecrets', 'params' => ''];
            case 'jobs': $data += ['method' => 'getQdbJobs', 'params' => $filters];
            case 'etc': $data += ['method' => 'getUtility', 'params' => $filters];
                return $data += ['class' => \Veer\Services\Show\Site::class];
            default: return $data;
        }
    }
    
    /**
     * configure administration routes for create/update/delete etc.
     */
    protected function getRouteParamsAction($model)
    {
        switch($model) {
            case 'configuration':
            case 'components':
            case 'secrets': return \Veer\Services\Administration\Settings::class;
            case 'jobs': return \Veer\Services\Administration\Job::class;
            case 'etc': return \Veer\Services\Administration\Utility::class;
            case 'attributes': return \Veer\Services\Administration\Elements\Attribute::class;
            case 'categories': return \Veer\Services\Administration\Elements\Category::class;
            case 'downloads': return \Veer\Services\Administration\Elements\Download::class;
            case 'images': return \Veer\Services\Administration\Elements\Image::class;
            case 'pages': return \Veer\Services\Administration\Elements\Page::class;
            case 'products': return \Veer\Services\Administration\Elements\Product::class;
            case 'sites': return \Veer\Services\Administration\Elements\Site::class;
            case 'tags': return \Veer\Services\Administration\Elements\Tag::class;
            case 'users': return \Veer\Services\Administration\Elements\User::class;
            case 'roles': return \Veer\Services\Administration\Elements\Role::class;
            case 'communications': return \Veer\Services\Administration\Elements\Communication::class;
            case 'comments': return \Veer\Services\Administration\Elements\Comment::class;
            case 'searches': return \Veer\Services\Administration\Elements\Search::class;
            case 'lists': return \Veer\Services\Administration\Elements\UserList::class;
            case 'books': return \Veer\Services\Administration\Elements\UserBook::class;
            case 'statuses': return \Veer\Services\Administration\Elements\Status::class;
            case 'bills': return \Veer\Services\Administration\Elements\Bill::class;
            case 'discounts': return \Veer\Services\Administration\Elements\Discount::class;
            case 'orders': return \Veer\Services\Administration\Elements\Order::class;
            case 'payment': return \Veer\Services\Administration\Elements\Payment::class;
            case 'shipping': return \Veer\Services\Administration\Elements\Shipping::class;           
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  string $model
     * @return Response
     */
    public function update($model)
    {
        if(Input::has('SearchButton')) { return $this->show($model); }

        app('veer')->skipShow = false;        
        $class = $this->getRouteParamsAction($model);
        $data = !empty($class) ? $class::request($model) : 'Error!';
        
        return !app('request')->ajax() && !(app('veer')->skipShow) ? $this->show($model) : $data;
    }
    
    public function worker()
    {
        if(\Input::has('worker-lock')) return event('lock.for.edit', [[\Auth::id(), 'admin', \Input::get('entity'), \Input::get('id')]]);        
        if(\Input::has('get-messages')) {
            return \Session::pull('veer_message_center');
        }
    }

    /**
     * Restore soft deleted entity
     */
    protected function restore($model = null, $id = null)
    {
        if(empty($model) || empty($id)) { return; }

        $model = "\\" . elements($model);

        $model::withTrashed()->where('id', $id)->restore();

        event('veer.message.center', trans('veeradmin.restored'));
    }

}
