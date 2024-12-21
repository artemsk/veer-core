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
        $class->action = \Illuminate\Support\Facades\Request::input('action');
        $class->sites_id = \Illuminate\Support\Facades\Request::input('InSite');

        $class->action != 'updateRoles' ?: $class->update(\Illuminate\Support\Facades\Request::input('role'));
        !\Illuminate\Support\Str::startsWith($class->action, 'deleteRole') ?: $class->delete(substr($class->action, 11));
        !\Illuminate\Support\Facades\Request::has('InUsers') ?: $class->attachUsers(\Illuminate\Support\Facades\Request::input('InUsers'));
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

        if (\Illuminate\Support\Str::startsWith($data, "NEW")) {
            $rolesId = $this->role_id;
        } else {
            $rolesId = trim(\Illuminate\Support\Arr::get($parseAttach, 0));
        }

        $usersIds = $this->parseIds(substr(\Illuminate\Support\Arr::get($parseAttach, 1), 0, -1));

        if(!empty($usersIds)) {
            $this->associate("users", $usersIds, (int)$rolesId, "roles_id");
        }

        return $this;
    }
    
}
