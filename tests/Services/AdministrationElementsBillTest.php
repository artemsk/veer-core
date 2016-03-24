<?php

class AdministrationElementsBillTest extends TestCase {

    protected $requestUrl = '/admin/bills';
    protected $inMemoryDb = true;

    protected $handler;

    public function setUp()
    {
        parent::setUp();

        $this->handler = new \Veer\Services\Administration\Elements\Bill;
    }


}
