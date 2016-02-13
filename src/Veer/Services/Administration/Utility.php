<?php namespace Veer\Services\Administration;

use Veer\Services\VeerApp;

class Utility {
   
    protected $action;
    
    public function __construct()
    {
        //
    }
    
    // @todo unite request & handle
    public static function request()
    {
        $class = new static;
        $class->action = \Input::get('actionButton');

        switch($class->action) {
            case 'runRawSql':
                $class->sql(Input::get('freeFormSql'));
                break;
            case 'checkLatestVersion':
                return $class->getVersion(false, true);
            case 'sendPingEmail':
                $class->pingEmail(Input::get('customPingEmail'));
                break;
            case 'clearTrashed':
                $class->clearTrashed(Input::get('tableName'));
                break;
            case 'clearCache':
                $class->clearCache();
                break;
        }
    }
    
    public function handle()
    {
        return self::request();
    }
        
    // @todo warning! very dangerous!
    public function sql($sql)
    {        
		if(!empty($sql)) {
            \DB::statement($sql);
        }
        
        info('SQL: ' . $sql);
        return $this;
    }
    
    public function getVersion($current = true, $returnView = false)
    {
        $latest = $this->_checkLatestVersion();
			
        // for ajax calls
        if(app('request')->ajax() && $returnView) {
            // should we return view?
            return view('components.version', array(
                "latest" => $latest,
                "current" => VeerApp::VEERVERSION,
            ));		
        }
        
        return $current ? VeerApp::VEERVERSION : $latest;
    }
    
    public function pingEmail($email = null)
    {
        $pingEmail = empty($email) ? config('mail.from.address') : $email;
        if(empty($pingEmail)) {
            return $this;
        }

        \Mail::send('emails.ping', [], function($message) use ($pingEmail) {
            $message->to($pingEmail);
        });

        return $this;
    }
    
    public function clearTrashed($table)
    {
        if(!empty($table)) {
            \Illuminate\Support\Facades\DB::table($table)
                    ->whereNotNull('deleted_at')->delete();
        }

        return $this;
    }
    
    public function clearCache()
    {
        \Cache::flush();

        return $this;
    }
    
    protected function _checkLatestVersion()
	{
		$client = new \GuzzleHttp\Client();
		$response = $client->get(VeerApp::VEERCOREURL . "/releases", ['verify' => false]);
		$res = json_decode($response->getBody());
				
		return head($res)->tag_name;
	}
}
