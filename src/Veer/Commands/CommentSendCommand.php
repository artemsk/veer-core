<?php

namespace Veer\Commands;

use Veer\Commands\Command;
use Illuminate\Contracts\Bus\SelfHandling;

class CommentSendCommand extends Command
{
    use \Veer\Services\Traits\MessageTraits;
    protected $data;
    protected $options;
    /**
     * Create a new command instance.
     *
     * @params array $data request
     * @params array $options
     */
    public function __construct($data, $options = null)
    {
        $this->data = $data;

        $this->options = $options;
    }
    /**
     * Execute the command.
     *
     */
    public function handle()
    {
        \Event::fire('router.filter: csrf');

        if (\Illuminate\Support\Arr::get($this->data, 'fill.txt') == null) return false;

        $comment = $this->saveComment();

        list(, $emails, $recipients) = $this->parseMessage(\Illuminate\Support\Arr::get($this->data, 'fill.txt'));

        (new \Veer\Commands\PrepareMailMessageCommand([
            "object" => $comment,
            "emails" => $emails,
            "recipients" => $recipients,
            "type" => "comment"
        ]))->handle();

        return $comment->id;
    }
    /**
     * save comment to db
     * 
     */
    protected function saveComment()
    {
        \Eloquent::unguard();

        $comment = new \Veer\Models\Comment;

        $this->setParameters();

        $comment->fill(\Illuminate\Support\Arr::get($this->data, 'fill'));

        $comment->hidden = \Illuminate\Support\Arr::get($this->options, 'checkboxes.hidden', false);

        $this->setMessagingSource($comment, \Illuminate\Support\Arr::get($this->data, 'connected'));

        $comment->save();

        return $comment;
    }
    /*
     * set parameters
     *
     */
    protected function setParameters()
    {
        array_set_empty($this->data, 'fill.users_id', \Auth::id());

        $this->setAuthorName(\Illuminate\Support\Arr::get($this->data, 'fill.users_id'));

        $this->setVotes();
    }
    /*
     * set author name
     */
    protected function setAuthorName($userId)
    {
        if (!empty($userId)) {
            array_set_empty($this->data, 'fill.author', \Auth::user()->username);
        }
    }
    /*
     * set votes
     */
    protected function setVotes()
    {
        if (\Illuminate\Support\Arr::get($this->data, 'vote') == "Yes")
                \Illuminate\Support\Arr::set($this->data, 'fill.vote_y', true);

        if (\Illuminate\Support\Arr::get($this->data, 'vote') == "No")
                \Illuminate\Support\Arr::set($this->data, 'fill.vote_n', true);
    }
}
