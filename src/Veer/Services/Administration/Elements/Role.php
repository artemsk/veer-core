<?php

namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class Role {

    use HelperTrait, DeleteTrait, AttachTrait;
    
    protected $action;
    protected $sites_id;
    protected $type = 'role';

    protected $role_id = 0;

    public function __construct()
    {
        \Eloquent::unguard();
        $this->sites_id = app('veer')->siteId;
    }

    public static function request()
    {
        $class = new static;
        $class->action = Input::get('action');
        $class->sites_id = Input::get('InSite');

        $class->action != 'updateRoles' ?: $class->update(Input::get('role'));
        !starts_with($class->action, 'deleteRole') ?: $class->delete(substr($class->action, 11));
        !Input::has('InUsers') ?: $class->attachUsers(Input::get('InUsers'));
    }

    public function add($data, $returnId = true)
    {
        $sites_id = empty($data['sites_id']) ? $this->sites_id : $data['sites_id'];
        
        $role = \Veer\Models\UserRole::firstOrNew([
            "role" => $data['role'],
            "sites_id" => $sites_id
        ]);

        $role->fill($data);
        $role->sites_id = $sites_id;
        $role->save();

        $this->role_id = $role->id;

        return $returnId ? $role->id : $this;
    }

    public function update($data)
    {
        $data = (array) $data;
        
        foreach($data as $roleId => $role) {
            if($roleId != "new") {
                \Veer\Models\UserRole::where('id', '=', $roleId)
                        ->update($role);                         
            } elseif($roleId == "new" && !empty($role['role'])) {
                $this->add($role);
            }
        }

        event('veer.message.center', trans('veeradmin.role.update'));
        return $this;
    }

    public function delete($id)
    {
        if(!empty($id) && $this->deleteUserRole($id)) {
            event('veer.message.center', trans('veeradmin.role.delete'));
        }

        return $this;
    }

    // @todo detach action ? to every element ?
    public function attach($users_id, $role_id = null)
    {
        if(empty($role_id) && !empty($this->role_id)) {
            $role_id = $this->role_id;
        } elseif(empty($role_id)) {
            return $this;
        }

        if(!empty($users_id)) {
            $this->associate("users", $users_id, (int)$role_id, "roles_id");
        }

        return $this;
    }

    public function detach($users_id)
    {
        if(!empty($users_id)) {
            $this->associate("users", $users_id, 0, "roles_id");
        }

        return $this;
    }

    public function detachRole($role_id)
    {
        \Veer\Models\UserRole::where('roles_id', $role_id)
                ->update(['roles_id' => 0]);

        return $this;
    }

    protected function attachUsers($data)
    {
        $parseAttach = explode("[", $data);

        if (starts_with($data, "NEW")) {
            $rolesId = $this->role_id;
        } else {
            $rolesId = trim(array_get($parseAttach, 0));
        }

        $usersIds = $this->parseIds(substr(array_get($parseAttach, 1), 0, -1));

        if(!empty($usersIds)) {
            $this->associate("users", $usersIds, (int)$rolesId, "roles_id");
        }

        return $this;
    }
    
}
