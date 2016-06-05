<?php


abstract class Caldera_Forms_Email_Email {

	/**
	 * @var array
	 */
	protected $form;

	/**
	 * @var int
	 */
	protected $entry_id;

	/**
	 * @var \Caldera_Forms_Entry
	 */
	protected $entry;

	/**
	 * @var Caldera_Forms_Email_Recipient
	 */
	protected $default_email_object;

	/**
	 * @var Caldera_Forms_Email_Message
	 */
	protected $message;

	public function __construct( array $form, $entry_id, Caldera_Forms_Entry $entry = null ) {
		$this->form = $form;
		if ( null != $entry ) {
			$this->entry    = $entry;
			$this->entry_id = $entry->get_entry_id();
		}else{
			$this->entry_id = $entry_id;
		}

	}

	public function create(){
		$this->set_recipients();
		$this->set_from();
		$this->set_reply_to();
		$this->create_message();

	}

	protected function prepare_mail(){
		return array();
	}

	public function send(){
		$form = $this->form;
		$mail = $this->prepare_mail();
		$data = Caldera_Forms::get_submission_data( $this->form, $this->entry_id );

		/**
		 * Filter email data before sending
		 *
		 * @since 1.2.3 in this location.
		 * @since unknown in original location (Caldera_Forms::save_final_form)
		 *
		 * @param array $mail Email data
		 * @param array $data Form entry data
		 * @param array $form The form config
		 */
		$mail = apply_filters( 'caldera_forms_mailer', $mail, $data, $form);
		if ( empty( $mail ) || ! is_array( $mail ) ) {
			return;
		}
		$headers = implode("\r\n", $mail['headers']);
		/**
		 * Runs before mail is sent, but after data is prepared
		 *
		 * @since 1.2.3 in this location.
		 * @since unknown in original location (Caldera_Forms::save_final_form)
		 *
		 * @param array $mail Email data
		 * @param array $data Form entry data
		 * @param array $form The form config
		 */
		do_action( 'caldera_forms_do_mailer', $mail, $data, $form);
		// force message to string.
		if( is_array( $mail['message'] ) ){
			$mail['message'] = implode( "\n", $mail['message'] );
		}
		if( ! empty( $mail ) ){
			// is send debug enabled?
			if( !empty( $form['debug_mailer'] ) ){
				add_action( 'phpmailer_init', array( 'Caldera_Forms', 'debug_mail_send' ), 1000 );
			}
			if( wp_mail( (array) $mail['recipients'], $mail['subject'], stripslashes( $mail['message'] ), $headers, $mail['attachments'] )){
				// kill attachment.
				if(!empty($csvfile['file'])){
					if(file_exists($csvfile['file'])){
						unlink($csvfile['file']);
					}
				}

				/**
				 * Fires main mailer completes
				 *
				 * @since 1.3.1
				 *
				 * @param array $mail Email data
				 * @param array $data Form entry data
				 * @param array $form The form config
				 */
				do_action( 'caldera_forms_mailer_complete', $mail, $data, $form );
			}else{
				/**
				 * Fires main mailer fails
				 *
				 * @since 1.2.3
				 *
				 * @param array $mail Email data
				 * @param array $data Form entry data
				 * @param array $form The form config
				 */
				do_action( 'caldera_forms_mailer_failed', $mail, $data, $form );
			}
		}else{
			if(!empty($csvfile['file'])){
				if(file_exists($csvfile['file'])){
					unlink($csvfile['file']);
				}
			}
		}



	}





	protected function set_recipients(){
		if ( ! empty( $this->form[ 'mailer' ][ 'recipients' ] ) ) {
			$recipients = explode( ',', Caldera_Forms::do_magic_tags( $this->form[ 'mailer' ][ 'recipients' ] ) );
			foreach ( $recipients as $_recipient ){
				if ( is_email( $_recipient ) ) {
					$recipient                   = new Caldera_Forms_Email_Recipient;
					$recipient->email            = $recipient;
					$this->message->recipients[] = $recipient;
				}
			}
		} else {
			$this->message->recipients[] = $this->get_default_email_object();
		}
	}

	protected function set_from(){
		$sendername = esc_html__( 'Caldera Forms Notification', 'caldera-forms' );
		if ( ! empty( $this->form[ 'mailer' ][ 'sender_name' ] ) ) {
			$sendername = $this->form[ 'mailer' ][ 'sender_name' ];
			if ( false !== strpos( $sendername, '%' ) ) {
				$isname = Caldera_Forms::get_slug_data( trim( $sendername, '%' ), $this->form );
				if ( ! empty( $isname ) ) {
					$sendername = $isname;
				}
			}
		}

		if ( ! empty( $this->form[ 'mailer' ][ 'sender_email' ] ) {
			$sendermail = $this->form[ 'mailer' ][ 'sender_email' ];
			if ( false !== strpos( $sendermail, '%' ) ) {
				$ismail = Caldera_Forms::get_slug_data( trim( $sendermail, '%' ), $this->form );
				if ( is_email( $ismail ) ) {
					$sendermail = $ismail;
				}
			}
		}else{
			$sendermail = $this->get_default_email();
		}

		$this->message->from = new Caldera_Forms_Email_Recipient();
		$this->message->from->email = $sendername;
		$this->message->from->email = $sendermail;
	}


	protected function set_reply_to(){
		if ( isset( $this->form['mailer']['reply_to'] )  ) {
			$this->message->reply_to = trim( $this->form[ 'mailer' ][ 'reply_to' ] );
		}
	}

	/**
	 * Create message text, subject and CSV
	 *
	 * @TODO Separate into 3 methods
	 *
	 * @since 1.3.6
	 */
	protected function create_message(){
		//for now, use entry later, once core class supports it
		$data = Caldera_Forms::get_submission_data( $this->form, $this->entry_id );

		$contents = stripslashes( $this->form['mailer']['email_message'] ) ."\r\n";
		$contents = Caldera_Forms::do_magic_tags( $contents  );
		
		// get tags
		preg_match_all( "/%(.+?)%/", $contents, $hastags );
		if ( ! empty( $hastags[ 1 ] ) ) {
			foreach ( $hastags[ 1 ] as $tag_key => $tag ) {
				$tagval = Caldera_Forms::get_slug_data( $tag, $this->form );
				if ( is_array( $tagval ) ) {
					$tagval = implode( ', ', $tagval );
				}
				$contents = str_replace( $hastags[ 0 ][ $tag_key ], $tagval, $contents );
			}
		}

		// ifs
		preg_match_all( "/\[if (.+?)?\](?:(.+?)?\[\/if\])?/", $contents, $hasifs );
		if ( ! empty( $hasifs[ 1 ] ) ) {

			// process ifs
			foreach ( $hasifs[ 0 ] as $if_key => $if_tag ) {
				$content = explode( '[else]', $hasifs[ 2 ][ $if_key ] );
				if ( empty( $content[ 1 ] ) ) {
					$content[ 1 ] = '';
				}
				$vars = shortcode_parse_atts( $hasifs[ 1 ][ $if_key ] );
				foreach ( $vars as $varkey => $varval ) {
					if ( is_string( $varkey ) ) {
						$var = Caldera_Forms::get_slug_data( $varkey, $this->form );
						if ( in_array( $varval, (array) $var ) ) {
							// yes show code
							$contents = str_replace( $hasifs[ 0 ][ $if_key ], $content[ 0 ], $contents );
						} else {
							// nope- no code
							$contents = str_replace( $hasifs[ 0 ][ $if_key ], $content[ 1 ], $contents );
						}
					} else {
						$var = Caldera_Forms::get_slug_data( $varval, $this->form );
						if ( ! empty( $var ) ) {
							// show code
							$contents = str_replace( $hasifs[ 0 ][ $if_key ], $content[ 0 ], $contents );
						} else {
							// no code
							$contents = str_replace( $hasifs[ 0 ][ $if_key ], $content[ 1 ], $contents );
						}
					}
				}
			}
		}

		$submission = $labels = array();
		foreach ( $data as $field_id => $row ) {
			if ( $row === null || ! isset( $this->form[ 'fields' ][ $field_id ] ) ) {
				continue;
			}

			$key = $this->form[ 'fields' ][ $field_id ][ 'slug' ];
			if ( is_array( $row ) ) {
				if ( ! empty( $row ) ) {
					$keys = array_keys( $row );
					if ( is_int( $keys[ 0 ] ) ) {
						$row = implode( ', ', $row );
					} else {
						$tmp = array();
						foreach ( $row as $linekey => $item ) {
							if ( is_array( $item ) ) {
								$item = '( ' . implode( ', ', $item ) . ' )';
							}
							$tmp[] = $linekey . ': ' . $item;
						}
						$row = implode( ', ', $tmp );
					}
				} else {
					$row = null;
				}
			}

			$contents = str_replace( '%' . $key . '%', $row, $contents );
			$this->message->subject = str_replace('%'.$key.'%', $row, $this->message->subject );

			$submission[] = $row;
			$labels[]     = $this->form[ 'fields' ][ $field_id ][ 'label' ];
		}

		// final magic
		$contents = Caldera_Forms::do_magic_tags( $contents );
		$this->message->subject = Caldera_Forms::do_magic_tags( $this->message->subject );

		// CSV -- @TODO replace as part of #624
		if ( ! empty( $this->form[ 'mailer' ][ 'csv_data' ] ) ) {
			ob_start();
			$df = fopen( "php://output", 'w' );
			fputcsv( $df, $labels );
			fputcsv( $df, $submission );
			fclose( $df );
			$csv     = ob_get_clean();
			$csvfile = wp_upload_bits( uniqid() . '.csv', null, $csv );
			if ( isset( $csvfile[ 'file' ] ) && false == $csvfile[ 'error' ] && file_exists( $csvfile[ 'file' ] ) ) {
				$this->message->attachments =  $csvfile[ 'file' ];
			}
		}

		$this->message->message_text = $contents;
	}



	protected function get_default_email_object(){
		if( null == $this->default_email_object ){
			$this->default_email_object = new Caldera_Forms_Email_Recipient;
			$this->default_email_object->email = $this->get_default_email();
		}
	}

	private function get_default_email(){
		$default = get_option( '_caldera_forms_default_email', get_option( 'admin_email' ) );

		/**
		 * Filter fallback email address
		 *
		 * @since 1.3.6
		 *
		 * @param string $default Default email.
		 */
		return apply_filters( 'caldera_forms_default_email', $default  );
	}


}
