<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MigrationsCreatePivotCategories extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('categories_pivot', function($table) {
            $table->bigIncrements('id')->index();
            $table->bigInteger('parent_id')->default(0)->index();
            $table->bigInteger('child_id')->default(0)->index();
        });
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('categories_pivot');
	}

}
