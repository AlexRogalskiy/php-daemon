<?php

namespace Mail;

class Mailer {
	
	// default mail subject
	private $mailSubject = null;
	// default mail message
	private $mailMessage = null;
	
	// default recipients list
	private $mailTo = null;
	
	// default copies list
	private $mailCc = null;
	private $mailBcc = null;
	
	// default mail from
	private $mailFrom = 'daemon@test.com';
	// default mail from name
	private $mailFromName = 'Daemon Service';
	
	// default reply address
	private $mailReplyTo = 'no-reply@test.com';
	
	// default mail charset
	private $mailCharset = null;
	
	public static function withDefault($mailTo) {
		$instance = new self('Daemon Service Message', 'Daemon Message', $mailTo);
		return $instance;
    }
	
	public function __construct($mailSubject, $mailMessage, $mailTo, $mailCc = null, $mailBcc = null, $mailCharset = 'windows-1252') {
        $this->mailSubject = $mailSubject;
        $this->mailMessage = $mailMessage;
        $this->mailTo = $mailTo;
        $this->mailCc = $mailCc;
        $this->mailBcc = $mailBcc;
		$this->mailCharset = $mailCharset;
    }

	private function get_headers() {
		$headers  = "Content-type: text/html; charset={$this->mailCharset}\r\n";
		$headers .= "From: {$this->mailFromName} <{$this->mailFrom}>\r\n";
		$headers .= "Reply-To: {$this->mailReplyTo}\r\n";
		if(isset($this->mailCc)) $headers .= "Cc: {$this->mailCc}\r\n";
		if(isset($this->mailBcc)) $headers .= "Bcc: {$this->mailBcc}\r\n";
		$headers .= "X-Mailer: Daemon/{phpversion()}";
		return headers;
	}
	
	public function send() {
		mail($this->mailTo, $this->mailSubject, $this->mailMessage, $this->get_headers());
	}
	
	public function set_mail_subject($mailSubject) {
		$this->mailSubject = mailSubject;
	}
	
	public function set_mail_message($mailMessage) {
		$this->mailMessage = mailMessage;
	}
	
	public function set_mail_to($mailTo) {
		$this->mailTo = mailMessage;
	}
	
	public function set_mail_cc($mailCc) {
		$this->mailCc = mailCc;
	}
	
	public function set_mail_bcc($mailBcc) {
		$this->mailBcc = mailBcc;
	}
	
	public function set_mail_charset($mailCharset) {
		$this->mailCharset = mailCharset;
	}
}