<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class SendPasswordMail extends Mailable
{
    public $nama;
    public $email;
    public $password;

    public function __construct($nama, $email, $password)
    {
        $this->nama = $nama;
        $this->email = $email;
        $this->password = $password;
    }

    public function build()
    {
        return $this->subject('Akun SSB Anda')
                    ->view('emails.send_password');
    }
}
