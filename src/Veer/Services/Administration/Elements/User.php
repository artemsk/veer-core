<?php

namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class User {

    use DeleteTrait {
        deletePage as deletePageTrait;
        deleteUserBook as deleteUserBookTrait;
    }

    use HelperTrait, AttachTrait;

    protected $type = 'user';
    protected $action;
    protected $id;
    protected $user;

    public function __construct()
    {
        \Eloquent::unguard();
    }

    /**
     * @param int $id
     * @return \Veer\Services\Administration\Elements\User
     */
    public function find($id)
    {
        $user = \Veer\Models\User::find($id);

        if (is_object($user)) {
            $this->id = $id;
            $this->user = $user;
        }

        return $this;
    }

    /**
     * @param array $data
     * @return \Veer\Services\Administration\Elements\User
     */
    public function update($data)
    {
        if ($this->user instanceof \Veer\Models\User && !empty($data)) {
            $this->user->fill($this->prepareData($data));
            $this->user->save();
        }

        return $this;
    }

    /**
     * 
     * @return void
     */
    public static function request()
    {
        //\Event::fire('router.filter: csrf');

        $class = new static;
        $class->action = \Illuminate\Support\Facades\Request::input('action');

        if (\Illuminate\Support\Facades\Request::has('id')) {
            return $class->one();
        }

        $class->updateRestrictions(\Illuminate\Support\Facades\Request::input('changeRestrictUser'));
        $class->updateBan(\Illuminate\Support\Facades\Request::input('changeStatusUser'));
        $class->delete(\Illuminate\Support\Facades\Request::input('deleteUser'));
        $class->add(
                $class->action == 'Add' ? \Illuminate\Support\Facades\Request::all() : null
        );
    }

    /**
     * 
     * @return mixed
     */
    protected function one()
    {
        $this->id = \Illuminate\Support\Facades\Request::input('id');

        $fill = \Illuminate\Support\Facades\Request::has('fill') ? $this->prepareData(\Illuminate\Support\Facades\Request::input('fill')) : null;

        if ($this->action == "add") { // @todo test
            $user = $this->validateAndCreate($fill);
        } else {
            $user = \Veer\Models\User::find($this->id);
        }

        if (!is_object($user)) {
            return event('veer.message.center', trans('veeradmin.error.model.not.found'));
        }

        if (!empty($fill)) {
            $user->fill($fill);
        }

        $user->save();
        $this->id = $user->id;
        $this->user = $user;

        $this->goThroughEverything();

        if ($this->action == "add") {
            app('veer')->skipShow = true;
            \Illuminate\Support\Facades\Request::replace(['id' => $this->id]);
            return \Redirect::route('admin.show', ['users', 'id' => $this->id]);
        }
    }

    /**
     * @return void
     */
    protected function goThroughEverything()
    {
        $addAsAdministrator = \Illuminate\Support\Facades\Request::input('addAsAdministrator');
        $administrator = \Illuminate\Support\Facades\Request::input('administrator');
        $attachImages = \Illuminate\Support\Facades\Request::input('attachImages');
        $removeImageAction = \Illuminate\Support\Str::startsWith($this->action, 'removeImage') ? substr($this->action, 12) : null;
        $attachPages = \Illuminate\Support\Facades\Request::input('attachPages');
        $removePage = \Illuminate\Support\Str::startsWith($this->action, 'removePage') ? substr($this->action, 11) : null;
        $deletePage = \Illuminate\Support\Str::startsWith($this->action, 'deletePage') ? substr($this->action, 11) : null;
        $addOrUpdateUserBook = ($this->action == "addUserbook" || $this->action == "updateUserbook") ? \Illuminate\Support\Facades\Request::input('userbook') : null;
        $deleteUserBook = \Illuminate\Support\Facades\Request::input('deleteUserbook');
        $cancelDiscount = \Illuminate\Support\Facades\Request::input('cancelDiscount');
        $attachDiscounts = \Illuminate\Support\Facades\Request::input('attachDiscounts');
        $sendMessage = \Illuminate\Support\Facades\Request::has('sendMessageToUser') ? \Illuminate\Support\Facades\Request::input('communication') : null;

        !$addAsAdministrator ?: $this->mkadmin();
        // user id or user object is needed:
        $this->updateOneAdministrator($administrator)
            ->attachImages($attachImages, $removeImageAction)
            ->attachPages($attachPages)
            ->attachDiscounts($attachDiscounts)
            ->sendMessageToUser($sendMessage);
        $this->removePage($removePage) // partly independ.
            ->deletePage($deletePage) // independ.
            ->addOrUpdateUserBook($addOrUpdateUserBook) // independ.
            ->deleteUserBook($deleteUserBook) // independ.
            ->cancelDiscount($cancelDiscount); // independ.

        // orders & bills <> independ.
        Order::request_actions();
        Bill::request();
    }

    /**
     * @param array $data
     * @return \Veer\Services\Administration\Elements\User
     */
    public function updateRestrictions($data)
    {
        if (!empty($data) && is_array($data)) {

            \Veer\Models\User::where('id', '=', key($data))
                    ->update([
                        'restrict_orders' => head($data)
            ]);

            event('veer.message.center', trans('veeradmin.user.update'));
        }

        return $this;
    }

    /**
     * @param array $data
     * @return \Veer\Services\Administration\Elements\User
     */
    public function updateBan($data)
    {
        if (is_array($data) && !empty($data) && key($data) != \Auth::id()) {

            \Veer\Models\User::where('id', '=', key($data))
                    ->update(['banned' => head($data)]);

            if (head($data) == true) {
                \Veer\Models\UserAdmin::where('users_id', '=', key($data))
                        ->update(['banned' => head($data)]);
            }

            event('veer.message.center', trans('veeradmin.user.ban'));
        }

        return $this;
    }

    /**
     * @param mixed $data
     * @return \Veer\Services\Administration\Elements\User
     */
    public function delete($data)
    {
        $id = is_array($data) ? key($data) : $data;

        if (!empty($id) && $this->deleteUser($id) !== false) {
            event('veer.message.center', trans('veeradmin.user.delete') .
                    " " . $this->restore_link("user", $id));
        }

        return $this;
    }

    /**
     * @param array $data
     * @return \Veer\Services\Administration\Elements\User
     */
    public function add($data)
    {
        if (empty($data) || !is_array($data)) {
            return $this;
        }

        $data += [
            'sites_id' => \Illuminate\Support\Arr::pull($data, 'siteId', app('veer')->siteId)
        ];

        $user = $this->validateAndCreate($data);

        if (is_object($user)) {
            $user->email = $data['email'];
            $user->password = $data['password'];
            $user->sites_id = $data['sites_id'];

            if (!empty($data['freeForm'])) {
                preg_match_all("/^(.*)$/m", trim($data['freeForm']), $parseForm);
            }

            $freeFormKeys = [ // strict order
                'username', 'phone', 'firstname', 'lastname', 'birth', 'gender', 'roles_id',
                'newsletter', 'restrict_orders', 'banned'
            ];

            foreach (!empty($parseForm[1]) ? $parseForm[1] : [] as $key => $value) {
                if (!empty($value)) {
                    $user->{$freeFormKeys[$key]} = $value;
                }
            }

            $user->save();

            $this->user = $user;
            $this->id = $user->id;
        }

        return $this;
    }

    /**
     * @param array $fill
     * @return \Veer\Models\User|boolean
     */
    protected function validateAndCreate($fill)
    {
        $rules = [
            'email' => 'required|email|unique:users,email,NULL,id,deleted_at,NULL,sites_id,' . $fill['sites_id'],
            'password' => 'required|min:6',
        ];

        $validator = \Validator::make($fill, $rules);

        if ($validator->fails()) {
            $messages = implode(' ', $validator->errors()->all());
            event('veer.message.center', trans('veeradmin.user.new.error') . ':');
            event('veer.message.center', $messages);
            return false;
        }

        event('veer.message.center', trans('veeradmin.user.new'));
        return new \Veer\Models\User;
    }

    /**
     * @param array $fill
     * @return array
     */
    protected function prepareData($fill)
    {
        if (empty($fill['sites_ids'])) {
            $fill['sites_id'] = app('veer')->siteId;
        }

        if (isset($fill['password']) && empty(trim($fill['password']))) {
            unset($fill['password']);
        }

        $fill['restrict_orders'] = isset($fill['restrict_orders']) ? true : false;
        $fill['newsletter'] = isset($fill['newsletter']) ? true : false;
        $fill['birth'] = parse_form_date(\Illuminate\Support\Arr::get($fill, 'birth', 0));

        return $fill;
    }

    /**
     * @return \Veer\Services\Administration\Elements\User
     */
    public function mkadmin()
    {
        $admin = \Veer\Models\UserAdmin::withTrashed()
                        ->where('users_id', '=', $this->id)->first();

        if (!is_object($admin)) {
            \Veer\Models\UserAdmin::create(['users_id' => $this->id]);
        } else {
            $admin->restore();
        }

        event('veer.message.center', trans('veeradmin.user.admin'));

        return $this;
    }

    /**
     * @todo use ranks to determine who can update whom
     * @param array $data
     * @return \Veer\Services\Administration\Elements\User
     */
    public function updateOneAdministrator($data)
    {
        if (!empty($data) && is_array($data)) {
            $data['banned'] = !empty($data['banned']) ? true : false;

            if ($this->id == \Auth::id()) { // one cannot ban himself
                unset($data['banned']);
            }

            \Veer\Models\UserAdmin::where('users_id', '=', $this->id)
                    ->update($data);
        }

        return $this;
    }

    /**
     * Attach, detach, upload Image
     * @param string $data
     * @param int $remove_id
     * @return \Veer\Services\Administration\Elements\User
     */
    public function attachImages($data, $remove_id = null)
    {
        if(!empty($data)) {
            $this->attachElements($data, $this->user, 'images');
        }

        if(!empty($remove_id)) {
            $this->user->images()->detach($remove_id);
        }

        // check if the files exist in the request
        $this->upload('image', 'uploadImage', $this->id, 'users', 'usr', null, false, null);

        return $this;
    }

    /**
     * @param mixed $data
     * @return \Veer\Services\Administration\Elements\User
     */
    public function attachPages($data)
    {
        if (!empty($data)) {
            $pages = !is_array($data) ? $this->parseIds(trim($data)) : $data;
            $this->associate("pages", $pages, $this->id, "users_id");
            event('veer.message.center', trans('veeradmin.user.page.attach'));
        }

        return $this;
    }

    /**
     * @param int $id
     * @return \Veer\Services\Administration\Elements\User
     */
    public function removePage($id)
    {
        if (!empty($id)) {
            $this->associate("pages", [$id], 0, "users_id");
            event('veer.message.center', trans('veeradmin.user.page.detach'));
        }

        return $this;
    }

    /**
     * @param int $id
     * @return \Veer\Services\Administration\Elements\User
     */
    public function deletePage($id)
    {
        if (!empty($id)) {
            $this->deletePageTrait($id);
            event('veer.message.center', trans('veeradmin.page.delete'));
        }

        return $this;
    }

    /**
     * @param array $userbook
     * @return \Veer\Services\Administration\Elements\User
     */
    public function addOrUpdateUserBook($userbook)
    {
        if (!empty($userbook)) {
            foreach ($userbook as $book) {
                app('veershop')->updateOrNewBook($book);
            }

            event('veer.message.center', trans('veeradmin.book.update'));
        }

        return $this;
    }

    /**
     * @param mixed $data
     * @return \Veer\Services\Administration\Elements\User
     */
    public function deleteUserBook($data)
    {
        if (!empty($data)) {
            $id = is_array($data) ? head($data) : $data;
            $this->deleteUserBookTrait($id);

            event('veer.message.center', trans('veeradmin.book.delete'));
        }

        return $this;
    }

    /**
     * @param mixed $data
     * @return \Veer\Services\Administration\Elements\User
     */
    public function cancelDiscount($data)
    {
        if (!empty($data)) {
            $id = is_array($data) ? head($data) : $data;
            \Veer\Models\UserDiscount::where('id', '=', $id)
                    ->update(['status' => 'canceled']);

            event('veer.message.center', trans('veeradmin.discount.cancel'));
        }

        return $this;
    }

    /**
     * @param mixed $data
     * @return \Veer\Services\Administration\Elements\User
     */
    public function attachDiscounts($data)
    {
        if (!empty($data)) {
            $discounts = !is_array($data) ? $this->parseIds(trim($data)) : $data;
            $this->associate("UserDiscount", $discounts, $this->id, "users_id", "id", "users_id = 0 and status = 'wait'");

            event('veer.message.center', trans('veeradmin.discount.attach'));
        }

        return $this;
    }

    /**
     * @param mixed $data
     * @return \Veer\Services\Administration\Elements\User
     */
    public function sendMessageToUser($data)
    {
        if (!empty($data)) {
            (new \Veer\Commands\CommunicationSendCommand($data))->handle();

            event('veer.message.center', trans('veeradmin.user.page.sendmessage'));
        }

        return $this;
    }

}
