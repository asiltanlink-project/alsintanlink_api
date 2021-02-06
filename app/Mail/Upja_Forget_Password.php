<?php

namespace App\Mail;

use App\Models\Upja;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class Upja_Forget_Password extends Mailable
{
    use Queueable, SerializesModels;
    public $upja;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Upja $upja)
    {
        $this->upja = $upja;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('alsintanlink@gmail.com')->view('email.upja_forget_password');
    }
}
