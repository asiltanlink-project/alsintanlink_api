<?php

namespace App\Mail;

use App\Models\Upja;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class Upja_Alert extends Mailable
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
        // dd('sa');
        return $this->subject('Peringatan Upja')->from('alsintanlink@gmail.com')->view('email.upja_alert');
    }
}
