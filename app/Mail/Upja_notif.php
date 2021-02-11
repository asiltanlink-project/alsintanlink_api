<?php

namespace App\Mail;

use App\Models\Farmer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class Upja_notif extends Mailable
{
    use Queueable, SerializesModels;
    public $farmer;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Farmer $farmer)
    {
        $this->farmer = $farmer;

    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {

        return $this->from('alsintanlink@gmail.com')->view('email.upja_notif');
    }
}
