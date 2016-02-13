<?php

namespace Veer\Services\Administration\Elements;

class Comment {

    protected $type = 'comment';

    public function __construct()
    {
        \Eloquent::unguard();
    }

    public static function request()
    {
        $class = new static;
        Input::get('action') != 'addComment' ?: $class->add(Input::all());
        !Input::has('hideComment') ?: $class->hide(head(Input::get('hideComment', [])));
        !Input::has('unhideComment') ?: $class->unhide(head(Input::get('unhideComment', [])));
        !Input::has('deleteComment') ?: $class->delete(head(Input::get('deleteComment', [])));
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
