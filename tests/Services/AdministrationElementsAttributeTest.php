<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class AdministrationElementsAttributeTest extends TestCase
{
    protected $requestUrl = '/admin/attributes';
    protected $inMemoryDb = false;

    protected $attribute;

    public function setUp()
    {
        parent::setUp();
        
        $this->attribute = new \Veer\Services\Administration\Elements\Attribute();
    }

    /**
     * @group attribute
     */
    public function testEmptyResponse()
    {
        $this->sendAdminRequest([]);
        $this->assertResponseOk();
    }

    /**
     * @group attribute
     */
    public function testAdd()
    {
        $this->attribute->add('Color', [
            'Red', 'Orange', 'Blue', 'Black'
        ]);

        $attribute = \Veer\Models\Attribute::where('name', 'Color')->lists('val')->toArray();
        $this->assertEquals(4, count($attribute));
        $this->assertTrue(empty(array_diff(['Red', 'Orange', 'Blue', 'Black'], $attribute)));
    }

    /**
     * @group attribute
     */
    public function testRequestAdd()
    {
        $this->sendAdminRequest([
            'action' => 'newAttribute',
            'newName' => 'ColorFromRequest',
            'newValue' => 'BlackBlack'
        ]);

        $attribute = \Veer\Models\Attribute::where('name', 'ColorFromRequest')->lists('val')->toArray();
        $this->assertEquals(1, count($attribute));
        $this->assertTrue(in_array('BlackBlack', $attribute));
    }

    /**
     * @group attribute
     */
    public function testRename()
    {
        $this->attribute->rename([
            'Color' => 'ColorRenamed'
        ]);

        $attribute = \Veer\Models\Attribute::where('name', 'Color')->lists('val')->toArray();
        $this->assertEquals(0, count($attribute));
    }

    /**
     * @group attribute
     */
    public function testUpdate()
    {
        $attribute = \Veer\Models\Attribute::where('name', 'ColorRenamed')->value('id');
        
        $data = [
            'renameAttrValue' => [$attribute => 'ColorValueRenamed'],
            'descrAttrValue' => [$attribute => 'AddedDescription'],
            'attrType' => [$attribute => 'choose'],
            'newAttrValue' => ['ColorNew' => 'NewBlue, NewBlack']
        ];

        $this->attribute->update($data);
        $renamed = \Veer\Models\Attribute::where('val', 'ColorValueRenamed')->count();
        $this->assertGreaterThan(0, $renamed);
        $descr = \Veer\Models\Attribute::where('descr', 'AddedDescription')->count();
        $this->assertGreaterThan(0, $descr);
        $newval = \Veer\Models\Attribute::where('name', 'ColorNew')
                ->whereIn('val', ['NewBlue', 'NewBlack'])->count();
        $this->assertGreaterThanOrEqual(2, $newval);
    }

    /**
     * @group attribute
     */
    public function testDelete()
    {
        $attributes = \Veer\Models\Attribute::where('name', 'like', 'Color%')->get();
        foreach($attributes as $attribute) {
            $this->attribute->delete($attribute->id);
        }
        $this->assertEquals(0, \Veer\Models\Attribute::where('name', 'like', 'Color%')->count());

        $attributes = \Veer\Models\Attribute::where('name', 'like', 'Color%')->withTrashed()->lists('id');
        $this->assertGreaterThan(0, count($attributes));
        \Veer\Models\Attribute::whereIn('id', $attributes)->withTrashed()->forceDelete();
        $attributes = \Veer\Models\Attribute::where('name', 'like', 'Color%')->withTrashed()->lists('id');
        $this->assertEquals(0, count($attributes));
    }

}
