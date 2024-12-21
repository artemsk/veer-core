<?php

namespace Veer\Services\Administration\Elements;

use Illuminate\Support\Facades\Input;

class Comment {

    protected $type = 'comment';

    public function __construct()
    {
        \Eloquent::unguard();
    }

    public static function request()
    {
        $class = new static;
        \Illuminate\Support\Facades\Request::input('action') != 'addComment' ?: $class->add(\Illuminate\Support\Facades\Request::all());
        !\Illuminate\Support\Facades\Request::has('hideComment') ?: $class->hide(head(\Illuminate\Support\Facades\Request::input('hideComment', [])));
        !\Illuminate\Support\Facades\Request::has('unhideComment') ?: $class->unhide(head(\Illuminate\Support\Facades\Request::input('unhideComment', [])));
        !\Illuminate\Support\Facades\Request::has('deleteComment') ?: $class->delete(head(\Illuminate\Support\Facades\Request::input('deleteComment', [])));
    }

    public function add($data)
    {
        (new \Veer\Commands\CommentSendCommand($data))->handle();

        event('veer.message.center', trans('veeradmin.' . $this->type . '.new'));

        return $this;
    }

    public function hide($id)
    {
        \Veer\Models\Comment::where('id', '=', $id)->update(['hidden' => true]);

        event('veer.message.center', trans('veeradmin.' . $this->type . '.hide'));

        return $this;
    }

    public function unhide($id)
    {
        \Veer\Models\Comment::where('id', '=', $id)->update(['hidden' => false]);

        event('veer.message.center', trans('veeradmin.' . $this->type . '.unhide'));

        return $this;
    }

    public function delete($id)
    {
        \Veer\Models\Comment::where('id', '=', $id)->delete();

		event('veer.message.center', trans('veeradmin.' . $this->type . '.delete'));

        return $this;
    }

}
