<?php

namespace App\Mail;

use App\Models\lab_uji;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class Lab_uji_verif extends Mailable
{
    use Queueable, SerializesModels;
    public $lab_uji;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(lab_uji $lab_uji)
    {
        $this->lab_uji = $lab_uji;

    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {

        return $this->from('alsintanlink@gmail.com')->view('email.lab_uji_verif');
    }
}
