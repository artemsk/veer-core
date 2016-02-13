<?php namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class Site {

    protected $type = 'site';
    protected $id;
    
    public function __construct()
    {
        \Eloquent::unguard();
    }

    public static function request()
    {
        $class = new static;
        $data = Input::get('site');
		$siteToTurnOff = Input::get('turnoff');
		$siteToTurnOn = Input::get('turnon');
        $currentSiteId = app('veer')->siteId;

        if(empty($data) && (!empty($siteToTurnOff) || !empty($siteToTurnOn))) {
            $data = \Veer\Models\Site::all()->keyBy('id')->toArray();
        }

        if(!empty($data)) {
            $class->iterateThroughSites($data, $siteToTurnOff, $siteToTurnOn, $currentSiteId, Input::has('snapshots'));
        }
    }
    
    /* @todo for security reasons - important to check access for users */
    
    public function on($siteid = null)
    {
        return $this->toggle($siteid, true);
    }
    
    public function off($siteid = null)
    {
        return $this->toggle($siteid, false);
    }

    protected function toggle($siteid, $switch)
    {
        if(empty($siteid)) {
            $siteid = $this->id;
        }
        
        $current = app('veer')->siteId;
        if(empty($current) || $current != $siteid) {
            \Veer\Models\Site::where('id', $siteid)
                    ->update(['on_off' => $switch]);
            event('veer.message.center', trans('veeradmin.sites.' . ($switch ? 'up' : 'down'), ['site_id' => $siteid]));
        }

        return $this;
    }
    
    public function add($url)
    {
        $ids = $this->iterateThroughSites([str_random(64) => [
            'url' => $url,
        ]]);

        if(!empty($ids)) {
            $this->id = reset($ids);
        }

        return $this;
    }

    public function moveFrom($fromSiteId, $toSiteId = null)
    {
        if(empty($toSiteId)) {
            $toSiteId = $this->id;
        }

        $from = \Veer\Models\Site::find($fromSiteId);

        if(is_object($from)) { // @todo test
            $from->subsites()->update(['parent_id' => $toSiteId]);
            $from->categories()->update(['sites_id' => $toSiteId]); // pages & products are connected through categories
            $from->components()->update(['sites_id' => $toSiteId]);
            $from->configuration()->update(['sites_id' => $toSiteId]);
            $from->users()->update(['sites_id' => $toSiteId]);
            $from->discounts()->update(['sites_id' => $toSiteId]);
            $from->userlists()->update(['sites_id' => $toSiteId]);
            $from->orders()->update(['sites_id' => $toSiteId]);
            $from->delivery()->update(['sites_id' => $toSiteId]);
            $from->payment()->update(['sites_id' => $toSiteId]);
            $from->communications()->update(['sites_id' => $toSiteId]);
            $from->roles()->update(['sites_id' => $toSiteId]);
            event('veer.message.center', trans('veeradmin.sites.moved', ['from' => $fromSiteId, 'to' => $toSiteId]));
        }

        return $this;
    }

    public function update($id, $data)
    {
        $this->iterateThroughSites([$id => $data], null, null, app('veer')->siteId);

        $this->id = $id;
        
        return $this;
    }
    
    public function refresh($siteid = null)
    {
        if(empty($siteid)) {
            $siteid = $this->id;
        }
        
        if(!empty($siteid)) {
            $site = \Veer\Models\Site::find($siteid);
            !is_object($site) ?: $this->refreshSiteSnapshots($site->url, $siteid);
        }

        return $this;
    }

    protected function iterateThroughSites($data, $turnOff = null, $turnOn = null, $current = null, $updateSnapshots = false)
    {
        $ids = [];
        foreach($data as $key => $values) {

            $values['url'] = trim($values['url']);
            if(empty($values['url'])) { 
                continue; 
            }

            $site = \Veer\Models\Site::firstOrNew(['id' => trim($key)]);
            $site->parent_id = empty($values['parent_id']) ? 0 : $values['parent_id'];
            $site->manual_sort = empty($values['manual_sort']) ? 0 : $values['manual_sort'];
            $site->on_off = !empty($site->on_off) ? true : false;

            if($current != $key) { // @todo test
                $site->url = $values['url'];
                $site->redirect_on = empty($values['redirect_on']) ? 0 : true;
                $site->redirect_url = empty($values['redirect_url']) ? '' : $values['redirect_url'];

                if($key == $turnOff) {
                    $site->on_off = false;
                    event('veer.message.center', trans('veeradmin.sites.down', ['site_id' => $site->id]));
                } elseif($key == $turnOn) {
                    $site->on_off = true;
                    event('veer.message.center', trans('veeradmin.sites.up', ['site_id' => $site->id]));
                }
            }

            if(!isset($site->id)) { 
                event('veer.message.center', trans('veeradmin.sites.new'));
            } else {
                event('veer.message.center', trans('veeradmin.sites.update'));
            }

            $site->save();
            $ids[] = $site->id;
            if($updateSnapshots) {
                $this->refreshSiteSnapshots($site->url, $site->id);
            }
        }

        if($current == $turnOff) { event('veer.message.center', trans('veeradmin.sites.error')); }
        \Cache::flush();
        return $ids;
    }
       
    /**
     * Refresh Snapshots - uses wkhtmltoimage (bugs warn.)
     * 
     */
    protected function refreshSiteSnapshots($siteUrl, $siteId, $width = 1368, $height = 768)
    {
        if (config('veer.wkhtmltoimage') == null) return false;

        set_time_limit(90);
        
        try {
            @unlink(public_path() . "/" . config('veer.images_path') . "/site-" . $siteId . ".jpg");

            passthru(config('veer.wkhtmltoimage') . " --width " . $width . " --disable-smart-width --height " .
                    $height . " " . $siteUrl . " " . public_path() . "/" . config('veer.images_path') .
                        "/site-" . $siteId . ".jpg");
            
        } catch(\Exception $e) {
            
            logger($e->getMessage());
        }

        sleep(5);    
    }
}
