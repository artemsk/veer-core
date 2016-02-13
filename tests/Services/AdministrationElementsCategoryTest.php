<?php

class AdministrationElementsCategoryTest extends TestCase
{
    protected $requestUrl = '/admin/categories';
    protected $inMemoryDb = true;

    protected $handler;

    public function setUp()
    {
        parent::setUp();

        $this->handler = new Veer\Services\Administration\Elements\Category;
    }

    public function testEmptyRequest()
    {
        $this->sendAdminRequest([]);
        $this->assertResponseOk();
    }

}