<?php

/**
 * @TODO What this does.
 *
 * @package   @TODO
 * @author    Josh Pollock <Josh@JoshPress.net>
 * @license   GPL-2.0+
 * @link
 * @copyright 2016 Josh Pollock
 */
abstract class Caldera_Forms_Email_Message extends Caldera_Forms_Object {
	

	protected $from;

	protected $reply_to;

	protected $recipients = array();

	protected $bcc = array();

	protected $headers = array();

	protected $message_text;

	protected $subject = '';
	protected $attachments;

	protected function recipients_set( Caldera_Forms_Email_Recipient $recipient ){
		$recipient->email = sanitize_email( $recipient->email );
		$this->recipients[] = $recipient;

	}
	

}
