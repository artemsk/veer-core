<?php

class TestCase extends Illuminate\Foundation\Testing\TestCase
{
    /**
     * The base URL to use while testing the application.
     *
     * @var string
     */
    protected $baseUrl = 'http://localhost';
    protected $requestUrl;
    protected $inMemoryDb = true;

    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../vendor/artemsk/veer/bootstrap/app.php';

        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;        
    }

    public function setUp()
    {
        parent::setUp();

        $databaseUrl = __DIR__ . '/../tests/studs/testing.sqlite';
        $runMigrate = false;
        
        $fileSystem = new \Illuminate\Filesystem\Filesystem;
        if(!$this->inMemoryDb && !$fileSystem->exists($databaseUrl)) {
            $fileSystem->put($databaseUrl, '');
            $runMigrate = true;
            fwrite(STDOUT, "\r\n- Migrate \r\n");
        }
        
        $this->app['config']->set('app.key', 'SomeRandomStringWith32Characters');
        $this->app['config']->set('app.debug', true);
        $this->app['config']->set('database.default','sqlite');
        $this->app['config']->set('database.connections.sqlite.database', $this->inMemoryDb ? ':memory:' : $databaseUrl);

        if($runMigrate || $this->inMemoryDb) {
            $this->migrate();
            $this->createSiteAndAdminUser();
        }
        // generally it is not run during console calls, but it is ok for testing, because
        // system knows that site's url is localhost
        app('veer')->run();

        $admin = \Veer\Models\UserAdmin::where('banned', 0)->first();
        \Auth::loginUsingId($admin->users_id);
    }

    protected function migrate()
    {
        $fileSystem = new \Illuminate\Filesystem\Filesystem;
        $classFinder = new Illuminate\Filesystem\ClassFinder;

        foreach($fileSystem->files(__DIR__ . "/../database") as $file)
        {
            $fileSystem->requireOnce($file);
            $migrationClass = $classFinder->findClass($file);

            (new $migrationClass)->up();
        }
    }

    protected function createSiteAndAdminUser()
    {
        $site = new \Veer\Models\Site();
		$site->url = $this->app['config']->get('app.url');
		$site->on_off = 1;
		$site->save();

        $user = new \Veer\Models\User;
        $user->email = 'testing@bolshaya.net';
        $user->password = 'testing';
		$user->sites_id = $site->id;
		$user->save();

        $admin = new \Veer\Models\UserAdmin;
        $admin->save();
        $user->administrator()->save($admin);
    }

    public function tearDown()
    {
        parent::tearDown();
    }

    protected function deleteDbFile()
    {
        @unlink(__DIR__ . '/../tests/studs/testing.sqlite');
    }

    protected function sendAdminRequest($data, $method = 'PUT')
    {
        return $this->call($method, $this->requestUrl, $data);
    }

    protected function getTestFile($file)
    {
        $copyFileName = __DIR__ . '/studs/tmp_file.' . pathinfo($file)['extension'];
        file_put_contents($copyFileName, file_get_contents($file));

        $fileUpload = new \Symfony\Component\HttpFoundation\File\UploadedFile(
            $copyFileName, pathinfo($file)['basename'], \File::mimeType($file), \File::size($file), 0, 1
        );

        return $fileUpload;
    }
}
