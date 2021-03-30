<?php

namespace App\Mail;

use App\Models\Upja;
use App\Models\token_forget_upja;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class Upja_Forget_Password extends Mailable
{
    use Queueable, SerializesModels;
    public $upja, $token;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Upja $upja, token_forget_upja $token )
    {
        $this->upja = $upja;
        $this->token = $token;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Forget Password')->from('alsintanlink@gmail.com')->view('email.upja_forget_password');
    }
}
