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
        Input::get('action') != 'addMessage' ?: $class->add(Input::get('communication'));
        !Input::has('hideMessage') ?: $class->hide(head(Input::get('hideMessage', [])));
        !Input::has('unhideMessage') ?: $class->unhide(head(Input::get('unhideMessage', [])));
        !Input::has('deleteMessage') ?: $class->delete(head(Input::get('deleteMessage', [])));
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
