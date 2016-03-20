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
        $this->testImage = __DIR__ . '/../studs/image.jpg';
    }

    /**
     * @group category
     */
    public function testEmptyRequest()
    {
        $this->sendAdminRequest([]);
        $this->assertResponseOk();
    }

    /**
     * @group category
     */
    public function testAddCategory()
    {
        $this->handler->add(null);
        $this->handler->add('Category1');
        $this->assertNotEmpty($this->handler->getId());
        $entity = $this->handler->getEntity();
        $this->assertTrue(is_object($entity));
        $this->assertEquals($entity->title, 'Category1');
    }

    /**
     * @group category
     */
    public function testUpdateCategory()
    {
        $this->handler->add('Category2');
        $data = ['title' => 'Category2Updated', 'description' => 'Description'];
        $this->handler->update($data);
        $id = $this->handler->getId();
        $this->assertGreaterThan(0, $id);
        $this->handler->find($id);
        $this->assertEquals('Category2Updated', $this->handler->getEntity()->title);
        $this->assertEquals('Description', $this->handler->getEntity()->description);
    }

    /**
     * @group category
     */
    public function testDeleteCategory()
    {
        $this->handler->add('Category3');
        $id = $this->handler->getId();
        $this->handler->delete($id);

        $this->handler = new Veer\Services\Administration\Elements\Category;
        $this->handler->find($id);
        $this->assertEmpty($this->handler->getId());
        $this->assertEmpty($this->handler->getEntity());
    }

    /**
     * @group category
     */
    public function testAttachWrongDataToCategory()
    {
        $this->handler->add('TestAttach')
            ->attach('images', 99999)
            ->attach('parentcategories', 99999)
            ->attach('subcategories', 1211212)
            ->attach('pages', 12121121)
            ->attach('products', 121121131);

        $entity = Veer\Models\Category::find($this->handler->getId());
        $entity->load('images', 'parentcategories', 'subcategories', 'pages', 'products');
        $this->assertEmpty($entity->images->count());
        $this->assertEmpty($entity->parentcategories->count());
        $this->assertEmpty($entity->subcategories->count());
        $this->assertEmpty($entity->pages->count());
        $this->assertEmpty($entity->products->count());
    }

    /**
     * @group category
     */
    public function testAttachDataToCategory()
    {
        $image = $this->getTestFile($this->testImage);
        $this->handler->add('CategoryWithImage')->image($image);

        $img = $this->handler->getEntity()->images->first();
        $this->assertTrue(is_object($img));
        $this->assertGreaterThan(0, $img->id);
        $this->assertNotEmpty($img->img);

        list(, $assets_path, $folder, , ,) = $this->handler->uploadDataProvider['image'];
        $path = (!config('veer.use_cloud_image')) ?  base_path() . '/' . $folder . '/' .
                config('veer.' . $assets_path) : config('veer.' . $assets_path);
        $this->assertTrue(file_exists($path . '/' . $img->img));

        $parentId = $this->handler->getId();
        $childId = $this->handler->add('ChildCategory', null, [], true, true);

        $page = (new Veer\Services\Administration\Elements\Page)->add([
            'title' => 'TestPage'
        ]);

        $product = (new Veer\Services\Administration\Elements\Product)->add([
            'title' => 'TestProduct'
        ]);

        $this->handler->add('AttachedCategory')
            ->attach('images', $img->id)
            ->attach('parentcategories', $parentId)
            ->attach('subcategories', $childId)
            ->attach('pages', $page->getId())
            ->attach('products', $product->getId());

        $entity = Veer\Models\Category::find($this->handler->getId());
        $entity->load('images', 'parentcategories', 'subcategories', 'pages', 'products');

        $this->assertEquals(1, $entity->images->where('id', $img->id)->count());
        $this->assertArrayHasKey($parentId, $entity->parentcategories->lists('title', 'id')->toArray());
        $this->assertArrayHasKey($childId, $entity->subcategories->lists('title', 'id')->toArray());
        $this->assertArrayHasKey($page->getId(), $entity->pages->lists('id', 'id')->toArray());
        $this->assertArrayHasKey($product->getId(), $entity->products->lists('id', 'id')->toArray());
    }

    /**
     * @group category
     */
    public function testDetachFromCategory()
    {
        $childId = $this->handler->add('ChildCategory', null, [], true, true);
        $this->handler->add('MainCategory')->attach('subcategories', $childId);
        $entity = Veer\Models\Category::find($this->handler->getId());
        $this->assertArrayHasKey($childId, $entity->subcategories->lists('title', 'id')->toArray());

        $this->handler->detach('subcategories', $childId);
        $entity = Veer\Models\Category::find($this->handler->getId());
        $this->assertEquals(0, $entity->subcategories->count());
    }

    /**
     * @group category
     */
    public function testAddCategoryFromArray()
    {
        $this->handler->addCategory([
            'newcategory' => 'NewCategory'
        ]);

        $this->assertNotEmpty($this->handler->getId());
        $entity = $this->handler->getEntity();
        $this->assertTrue(is_object($entity));
        $this->assertEquals($entity->title, 'NewCategory');
    }

    /**
     * @group category
     */
    public function testAttachDetachChildCategory()
    {
        $childId = $this->handler->add('ChildCategory1', null, [], true, true);

        $this->handler->add('MainCategory')->attachChild([$childId])->attachChild('ChildCategory2');
        
        $entity = Veer\Models\Category::find($this->handler->getId());
        $this->assertArrayHasKey($childId, $entity->subcategories->lists('title', 'id')->toArray());
        $this->assertTrue(in_array('ChildCategory2', $entity->subcategories->lists('title', 'id')->toArray()));

        $this->handler->detachChild($childId);

        $entity = Veer\Models\Category::find($this->handler->getId());
        $this->assertArrayNotHasKey($childId, $entity->subcategories->lists('title', 'id')->toArray());
    }

    /**
     * @group category
     */
    public function testAttachDetachParentCategory()
    {
        $parentId = $this->handler->add('ParentCategory1', null, [], true, true);

        $this->handler->add('MainCategory')->attachParent($parentId);

        $entity = Veer\Models\Category::find($this->handler->getId());
        $this->assertArrayHasKey($parentId, $entity->parentcategories->lists('title', 'id')->toArray());

        $this->handler->detachParent($parentId);

        $entity = Veer\Models\Category::find($this->handler->getId());
        $this->assertArrayNotHasKey($parentId, $entity->parentcategories->lists('title', 'id')->toArray());
    }

    /**
     * @group category
     */
    public function testDetachFromCurrentToAnotherParent()
    {
        $anotherParentId = $this->handler->add('NewParentCategory', null, [], true, true);
        $childId = $this->handler->add('ChildCategory', null, [], true, true);

        $this->handler->add('CurrentCategory')->attachChild([$childId]);

        $entity = Veer\Models\Category::find($this->handler->getId());
        $this->assertTrue(in_array('ChildCategory', $entity->subcategories->lists('title', 'id')->toArray()));

        $this->handler->updateChildParent($childId, $anotherParentId);

        $entity = Veer\Models\Category::find($this->handler->getId());
        $this->assertFalse(in_array('ChildCategory', $entity->subcategories->lists('title', 'id')->toArray()));
        $entity = Veer\Models\Category::find($anotherParentId);
        $this->assertTrue(in_array('ChildCategory', $entity->subcategories->lists('title', 'id')->toArray()));
    }

    /**
     * @group category
     */
    public function testSort()
    {
        for($j = 1; $j <= 5; $j++) {
            $this->handler->add('Category' . $j, null, ['sort' => $j * 10]);
        }

        $categories = Veer\Models\Category::all()->sortBy('manual_sort')->lists('manual_sort', 'id')->toArray();
        $this->assertEquals(5, count($categories));

        $lastId = last(array_keys($categories));

        $this->handler->sortChilds([]);
        $this->handler->sort([]);
        $this->handler->sort('String');
        $this->handler->sort([
            'oldindex' => count($categories) - 1,
            'newindex' => 0,
            'parentid' => app('veer')->siteId
        ]);

        $categories = Veer\Models\Category::all()->sortBy('manual_sort')->lists('manual_sort', 'id')->toArray(0);
        
        $firstId = head(array_keys($categories));

        $this->assertEquals($lastId, $firstId);
        
        $this->handler->sort([
            'oldindex' => 0,
            'newindex' => count($categories) - 1,
            'parentid' => app('veer')->siteId
        ]);

        $categories = Veer\Models\Category::all()->sortBy('manual_sort')->lists('manual_sort', 'id')->toArray(0);
        $newLastId = last(array_keys($categories));

        $this->assertEquals($newLastId, $firstId);
    }
}