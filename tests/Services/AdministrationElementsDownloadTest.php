<?php

class AdministrationElementsDownloadTest extends TestCase {
    
    protected $requestUrl = '/admin/downloads';
    protected $inMemoryDb = true;

    protected $handler;

    public function setUp()
    {
        parent::setUp();

        $this->handler = new Veer\Services\Administration\Elements\Download;
    }

    public function testEmptyRequest()
    {
        $this->sendAdminRequest([]);
        $this->assertResponseOk();
    }

}