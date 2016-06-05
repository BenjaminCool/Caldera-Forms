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
class Caldera_Forms_Email_Recipient extends Caldera_Forms_Object {

	protected $name;

	protected $email;

	public function __toString() {
		$string = 'From :';
		if( ! empty( $this->name ) ){
			$string .= $this->name . ' <' . $this->email . '>';
		}else{
			$string .= $this->email;
		}

		return $string;
	}
}
