<?php

namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class Communication {

    protected $type = 'communication';

    public function __construct()
    {
        \Eloquent::unguard();
    }

    public static function request()
    {
        $class = new static;
        \Illuminate\Support\Facades\Request::input('action') != 'addMessage' ?: $class->add(\Illuminate\Support\Facades\Request::input('communication'));
        !\Illuminate\Support\Facades\Request::has('hideMessage') ?: $class->hide(head(\Illuminate\Support\Facades\Request::input('hideMessage', [])));
        !\Illuminate\Support\Facades\Request::has('unhideMessage') ?: $class->unhide(head(\Illuminate\Support\Facades\Request::input('unhideMessage', [])));
        !\Illuminate\Support\Facades\Request::has('deleteMessage') ?: $class->delete(head(\Illuminate\Support\Facades\Request::input('deleteMessage', [])));
    }

    public function add($data)
    {
        (new \Veer\Commands\CommunicationSendCommand($data))->handle();

        event('veer.message.center', trans('veeradmin.' . $this->type . '.new'));

        return $this;
    }

    public function hide($id)
    {
        \Veer\Models\Communication::where('id', '=', $id)->update(['hidden' => true]);

        event('veer.message.center', trans('veeradmin.' . $this->type . '.hide'));

        return $this;
    }

    public function unhide($id)
    {
        \Veer\Models\Communication::where('id', '=', $id)->update(['hidden' => false]);

        event('veer.message.center', trans('veeradmin.' . $this->type . '.unhide'));

        return $this;
    }

    public function delete($id)
    {
        \Veer\Models\Communication::where('id', '=', $id)->delete();

		event('veer.message.center', trans('veeradmin.' . $this->type . '.delete'));

        return $this;
    }

}
