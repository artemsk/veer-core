<?php namespace Veer\Services\Administration\Elements;

trait DeleteTrait {
    
    protected function deleteEntity($id, $type)
    {
        $className = '\\Veer\\Models\\' . ucfirst($type); 
        $p = $className::find($id);
		if(is_object($p)) {
            
            switch($type) {
                case 'product':
                    $p->subproducts()->detach();
                    $p->parentproducts()->detach();
                    $p->pages()->detach();
                    break;
                case 'page':
                    $p->subpages()->detach();
                    $p->parentpages()->detach();
                    $p->products()->detach();
                    break;
            }
            
			$p->categories()->detach();
			$p->tags()->detach();
			$p->attributes()->detach();
			$p->images()->detach();
			$p->downloads()->update(["elements_id" => 0]);
			
			$p->userlists()->delete();
			$p->delete();
			// [orders_products], comments, communications skip
            return true;
		}  
        
        return false;
    }
    
    /**
	 * Delete Product & relationships
     * 
	 */
	protected function deleteProduct($id)
	{
		return $this->deleteEntity($id, 'product');
	}
    
    /**
	 * Delete Page & relationships
     * 
	 */
	protected function deletePage($id)
	{
		return $this->deleteEntity($id, 'page');
	}
    
    /**
	 * Delete Category: Category & connections
	 *
	 */
	protected function deleteCategory($cid)
	{
        if(empty($cid)) {
            return false;
        }
        
		\Veer\Models\Category::destroy($cid);
		\Veer\Models\CategoryConnect::where('categories_id','=',$cid)->forceDelete();
		\Veer\Models\CategoryPivot::where('parent_id','=',$cid)->orWhere('child_id','=',$cid)->forceDelete();
		\Veer\Models\ImageConnect::where('elements_id','=',$cid)
		->where('elements_type','=','Veer\Models\Category')->forceDelete();
		// We do not delete communications for deleted items
        return true;
	}
    
    /**
	 * Delete Image function
	 * 
	 */
	protected function deleteImage($id)
	{
		$img = \Veer\Models\Image::find($id);
		if(is_object($img)) {
			$img->pages()->detach();
			$img->products()->detach();
			$img->categories()->detach();
			$img->users()->detach();
            $this->deletingLocalOrCloudFiles('images', $img->img, config("veer.images_path"));
			$img->delete();
            return true;
		}
        return false;
	}
    
    /**
	 * Delete attribute
	 *
     */
	protected function deleteAttribute($id)
	{
		$t = \Veer\Models\Attribute::find($id);
		if(is_object($t)) {
			$t->pages()->detach();
			$t->products()->detach();
			$t->delete();
            return true;
		}
        return false;
	}
    
    /**
	 * Delete Tag
	 * 
	 */
	protected function deleteTag($id)
	{
		$t = \Veer\Models\Tag::find($id);
		if(is_object($t)) {
			$t->pages()->detach();
			$t->products()->detach();
			$t->delete();
            return true;
		}
        return false;
	}

    /**
     * Delete User
     * 
     */
    protected function deleteUser($id)
    {
        if($id == \Auth::id()) {
			return false;
		}

        $u = \Veer\Models\User::find($id);
		if(is_object($u)) {
			$u->discounts()->update(["status" => "canceled"]);
			$u->userlists()->update(["users_id" => false]);
			$u->books()->update(["users_id" => false]);
			$u->images()->detach();
			$u->searches()->detach();
			$u->administrator()->delete();
			// don't update: orders, bills, pages, comments, communications
			// do not need: site, role
			$u->delete();
            return true;
		}
        return false;
    }

    /**
	 * delete Book
	 * @param int $id
     * @todo relations
	 */
	protected function deleteUserBook($id)
	{
        \Veer\Models\UserBook::where('id', '=', $id)->delete();
        return true;
	}

    /**
	 * delete User Role
	 * @param type $id
	 */
	protected function deleteUserRole($id)
	{
		$u = \Veer\Models\UserRole::find($id);
		if(is_object($u)) {
			$u->users()->update(['roles_id' => null]);
			$u->delete();
            return true;
		}
        return false;
	}

    /**
	 * delete Search
	 * @param int $id
	 */
	protected function deleteSearch($id)
	{
        $s = \Veer\Models\Search::find($id);
		if(is_object($s)) {
			$s->users()->detach();
			$s->delete();
            return true;
		}
        return false;
	}

    /**
	 * delete List
     * @todo relations
	 */
	protected function deleteList($id)
	{
		\Veer\Models\UserList::where('id', '=', $id)->delete();
        return true;
	}

    protected function deleteOrder($id)
	{
        $order = \Veer\Models\Order::find($id);
		if(is_object($order)) {
			\Veer\Models\OrderHistory::where('orders_id', '=', $id)->delete();
			$order->orderContent()->delete();
			$order->bills()->delete();
			$order->secrets()->delete();
			// communications skip

			$order->delete();
            return true;
		}
        return false;
	}

    /**
     * Restore link
     * 
     */
    protected function restore_link($type, $id)
	{
		return "<a href=". route('admin.update', array('restore', 'type' => $type, 'id' => $id)) .">".
			\Lang::get('veeradmin.undo')."</a>";
	}
    
}