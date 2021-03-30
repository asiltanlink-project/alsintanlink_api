<?php

namespace App\Mail;

use App\Models\lab_uji\transaction_lab_uji_jadwal_uji;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class Master_Kode_Billing extends Mailable
{
    use Queueable, SerializesModels;
    public $transaction_lab_uji_jadwal_uji;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(transaction_lab_uji_jadwal_uji $transaction_lab_uji_jadwal_uji)
    {
        $this->transaction_lab_uji_jadwal_uji = $transaction_lab_uji_jadwal_uji;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $location = storage_path("app/public/lab_uji_upload/kode_billing/" .
                    $this->transaction_lab_uji_jadwal_uji->kode_billing);
        return $this->subject('Kode Billing')->from('alsintanlink@gmail.com')->view('email.master_kode_billing')->attach($location);;
    }
}
