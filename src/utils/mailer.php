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
	
	public function __construct($mailTo) {
        self::__construct('Daemon Service Message', 'Daemon Message', $mailTo);
    }
	
	public function __construct($mailSubject, $mailMessage, $mailTo, $mailCc = null, $mailBcc = null $mailCharset = 'windows-1252') {
        $this->mailSubject = $mailSubject;
        $this->mailMessage = $mailMessage;
        $this->mailTo = $mailTo;
        $this->mailCc = $mailCc;
        $this->mailBcc = $mailBcc;
		$this->mailCharset = $mailCharset;
    }

	private function getHeaders() {
		$headers  = "Content-type: text/html; charset={$this->mailCharset}\r\n";
		$headers .= "From: {$this->mailFromName} <{$this->mailFrom}>\r\n";
		$headers .= "Reply-To: {$this->mailReplyTo}\r\n";
		if(isset($this->mailCc)) $headers .= "Cc: {$this->mailCc}\r\n";
		if(isset($this->mailBcc)) $headers .= "Bcc: {$this->mailBcc}\r\n";
		$headers .= "X-Mailer: Daemon/{phpversion()}";
		return headers;
	}
	
	public function send() {
		mail($this->mailTo, $this->mailSubject, $this->mailMessage, $this->getHeaders());
	}
}