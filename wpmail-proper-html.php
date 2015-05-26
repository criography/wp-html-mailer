<?php
	/*
	Plugin Name: WP_MAIL Multipart HTML Emails
	Description: Makes sure that HTML emails contain plain-text as well
	Version: 1.0.4
	Author: Marek Lenik
	Author URI: http://criography.com
	Plugin URI: http://aardvarklondon.com
	*/



	//Include HTML 2 Plain-Text Parser
	require_once('html2text/lib/Html2Text/Html2Text.php');




	/**
	 * Check if this plug-in is enabled
	 **/
	function wpmail_proper_html_enabled(){
        return in_array( 'wpmail-proper-html/wpmail-proper-html.php', (array) get_option( 'active_plugins', array() ) );
	}






	/**
	 * Send email, using phpmailer From, Subject, Body, and $to (phpmailer doesn't have public access to recipients)
	 * PLEASE NOTE THIS CODE MAY NOT ACTUALLY WORK BECAUSE WASN'T TESTED
	 */
	function wpmail_proper_html_send( $phpmailer, $to ) {
		if ( is_array( $to ) ) {
			$to = implode( ',', $to );
		}
		$headers = 'From: ' . $phpmailer->From;
		mail($to, $phpmailer->Subject, $phpmailer->Body, $headers);
	}





	function str_replace_first($search, $replace, $subject) {
		$pos = strpos($subject, $search);
		if ($pos !== false) {
			$subject = substr_replace($subject, $replace, $pos, strlen($search));
		}
		return $subject;
	}






// YOU WILL HAVE CHECK IF wp_mail() IS NOT DEFINED BY WORDPRESS CORE OR SOMEWHERE ELSE
	if ( wpmail_proper_html_enabled() && !function_exists('wp_mail') ) {

/**
 * Send mail, similar to PHP's mail
 *
 * A true return value does not automatically mean that the user received the
 * email successfully. It just only means that the method used was able to
 * process the request without any errors.
 *
 * Using the two 'wp_mail_from' and 'wp_mail_from_name' hooks allow from
 * creating a from address like 'Name <email@address.com>' when both are set. If
 * just 'wp_mail_from' is set, then just the email address will be used with no
 * name.
 *
 * The default content type is 'text/plain' which does not allow using HTML.
 * However, you can set the content type of the email by using the
 * 'wp_mail_content_type' filter.
 *
 * The default charset is based on the charset used on the blog. The charset can
 * be set using the 'wp_mail_charset' filter.
 *
 * @since 1.2.1
 * @uses apply_filters() Calls 'wp_mail' hook on an array of all of the parameters.
 * @uses apply_filters() Calls 'wp_mail_from' hook to get the from email address.
 * @uses apply_filters() Calls 'wp_mail_from_name' hook to get the from address name.
 * @uses apply_filters() Calls 'wp_mail_content_type' hook to get the email content type.
 * @uses apply_filters() Calls 'wp_mail_charset' hook to get the email charset
 * @uses do_action_ref_array() Calls 'phpmailer_init' hook on the reference to
 *		phpmailer object.
 * @uses PHPMailer
 *
 * @param string|array $to Array or comma-separated list of email addresses to send message.
 * @param string $subject Email subject
 * @param string $message Message contents
 * @param string|array $headers Optional. Additional headers.
 * @param string|array $attachments Optional. Files to attach.
 * @return bool Whether the email contents were sent successfully.
 */


		function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {


			// default vars
			$defaults = array(
				'boundary'   => '__________MIMEboundary__________',
				'from_email' => 'support@aardvarklondon.com',
				'from_name'  => 'Aardvark London',
			);




			// Compact the input, apply the filters, and extract them back out

			/**
			* Filter the wp_mail() arguments.
			*
			* @since 2.2.0
			*
			* @param array $args A compacted array of wp_mail() arguments, including the "to" email,
			*                    subject, message, headers, and attachments values.
			*/
			$atts = apply_filters( 'wp_mail', compact( 'to', 'subject', 'message', 'headers', 'attachments' ) );

			if ( isset( $atts['to'] ) ) {
				$to = $atts['to'];
			}

			if ( isset( $atts['subject'] ) ) {
				$subject = $atts['subject'];
			}

			if ( isset( $atts['message'] ) ) {
				$message = $atts['message'];
			}

			if ( isset( $atts['headers'] ) ) {
				$headers = $atts['headers'];
			}

			if ( isset( $atts['attachments'] ) ) {
				$attachments = $atts['attachments'];
			}

			if ( ! is_array( $attachments ) ) {
				$attachments = explode( "\n", str_replace( "\r\n", "\n", $attachments ) );
			}

			global $phpmailer;





			// (Re)create it, if it's gone missing
			if ( !is_object( $phpmailer ) || !is_a( $phpmailer, 'PHPMailer' ) ) {
				require_once ABSPATH . WPINC . '/class-phpmailer.php';
				require_once ABSPATH . WPINC . '/class-smtp.php';
				$phpmailer = new PHPMailer( true );
			}





			// Headers
			if ( empty( $headers ) ) {
				$headers = array();
			} else {
				if ( !is_array( $headers ) ) {
					// Explode the headers out, so this function can take both
					// string headers and an array of headers.
					$tempheaders = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
				} else {
					$tempheaders = $headers;
				}
				$headers = array();
				$cc = array();
				$bcc = array();


				// If it's actually got contents
				if ( !empty( $tempheaders ) ) {
					// Iterate through the raw headers
					foreach ( (array) $tempheaders as $header ) {
						if ( strpos($header, ':') === false ) {
							if ( false !== stripos( $header, 'boundary=' ) ) {
								$parts = preg_split('/boundary=/i', trim( $header ) );
								$boundary = trim( str_replace( array( "'", '"' ), '', $parts[1] ) );
							}
							continue;
						}
						// Explode them out
						list( $name, $content ) = explode( ':', trim( $header ), 2 );



						// Cleanup crew
						$name    = trim( $name    );
						$content = trim( $content );

						switch ( strtolower( $name ) ) {
							// Mainly for legacy -- process a From: header if it's there
							case 'from':
								if ( strpos($content, '<' ) !== false ) {
									// So... making my life hard again?
									$from_name = substr( $content, 0, strpos( $content, '<' ) - 1 );
									$from_name = str_replace( '"', '', $from_name );
									$from_name = trim( $from_name );

									$from_email = substr( $content, strpos( $content, '<' ) + 1 );
									$from_email = str_replace( '>', '', $from_email );
									$from_email = trim( $from_email );
								} else {
									$from_email = trim( $content );
								}
								break;
							case 'content-type':
								if ( strpos( $content, ';' ) !== false ) {
									list( $type, $charset ) = explode( ';', $content );
									$content_type = trim( $type );
									if ( false !== stripos( $charset, 'charset=' ) ) {
										$charset = trim( str_replace( array( 'charset=', '"' ), '', $charset ) );
									} elseif ( false !== stripos( $charset, 'boundary=' ) ) {
										$boundary = trim( str_replace( array( 'BOUNDARY=', 'boundary=', '"' ), '', $charset ) );
										$charset = 'UTF-8';
									}
								} else {
									$content_type = trim( $content );
								}
								break;
							case 'cc':
								$cc = array_merge( (array) $cc, explode( ',', $content ) );
								break;
							case 'bcc':
								$bcc = array_merge( (array) $bcc, explode( ',', $content ) );
								break;
							default:
								// Add it to our grand headers array
								$headers[trim( $name )] = trim( $content );
								break;
						}
					}
				}
			}





			// Empty out the values that may be set
			$phpmailer->ClearAddresses();
			$phpmailer->ClearAllRecipients();
			$phpmailer->ClearAttachments();
			$phpmailer->ClearBCCs();
			$phpmailer->ClearCCs();
			$phpmailer->ClearCustomHeaders();
			$phpmailer->ClearReplyTos();



			//make sure the boundary is set
			if(empty($boundary)){
				$boundary = $defaults['boundary'];
			}




			// From email and name
			// If we don't have an email from the input headers default to values used at contact page
			if ( !isset( $from_name ) ){
				$from_name = $defaults['from_name'];
			}




			/* If we don't have an email from the input headers default to wordpress@$sitename
			 * Some hosts will block outgoing mail from this address if it doesn't exist but
			 * there's no easy alternative. Defaulting to admin_email might appear to be another
			 * option but some hosts may refuse to relay mail from an unknown domain. See
			 * http://trac.wordpress.org/ticket/5007.
			 */
			if ( !isset( $from_email ) ) {
				// Get the site domain and get rid of www.
				$sitename = strtolower( $_SERVER['SERVER_NAME'] );
				if ( substr( $sitename, 0, 4 ) == 'www.' ) {
					$sitename = substr( $sitename, 4 );
				}

				$from_email = 'wordpress@' . $sitename;
			}




			/**
			 * Filter the email address to send from.
			 *
			 * @since 2.2.0
			 *
			 * @param string $from_email Email address to send from.
			 */
			$phpmailer->From = apply_filters( 'wp_mail_from', $from_email );

			/**
			 * Filter the name to associate with the "from" email address.
			 *
			 * @since 2.3.0
			 *
			 * @param string $from_name Name associated with the "from" email address.
			 */
			$phpmailer->FromName = apply_filters( 'wp_mail_from_name', $from_name );





			// Set destination addresses
			if ( !is_array( $to ) )
				$to = explode( ',', $to );

			foreach ( (array) $to as $recipient ) {
				try {
					// Break $recipient into name and address parts if in the format "Foo <bar@baz.com>"
					$recipient_name = '';
					if( preg_match( '/(.*)<(.+)>/', $recipient, $matches ) ) {
						if ( count( $matches ) == 3 ) {
							$recipient_name = $matches[1];
							$recipient = $matches[2];
						}
					}
					$phpmailer->AddAddress( $recipient, $recipient_name);
				} catch ( phpmailerException $e ) {
					continue;
				}
			}





			// parse HTML and get slightly Markdown-ified plain-text string
			$h2t       = new \Html2Text\Html2Text($message, false);
			$firstName = str_word_count(trim($recipient_name), 1);




			// Set mail's subject and body
			$phpmailer->Subject = $subject;
			$phpmailer->Body    = "\r\n\r\n".'--'.$boundary."\r\n".
														'Content-Type: text/plain; charset="UTF-8"'."\r\n".
														'Content-Transfer-Encoding: 7bit'."\r\n"."\r\n".
														( isset($firstName[0]) ?
																str_replace_first(trim($recipient_name), $firstName[0], $h2t->get_text()) :
																$h2t->get_text()
														)."\r\n\r\n".


														"\r\n"."\r\n".'--'.$boundary."\r\n".
														'Content-Type: text/html; charset="UTF-8"'."\r\n".
														'Content-Transfer-Encoding: 7bit'."\r\n"."\r\n".
														( isset($firstName[0]) ?
																str_replace_first(trim($recipient_name), $firstName[0], $message) :
																$message
														)."\r\n\r\n".
														'--'.$boundary.'--';




			// Add any CC and BCC recipients
			if ( !empty( $cc ) ) {
				foreach ( (array) $cc as $recipient ) {
					try {
						// Break $recipient into name and address parts if in the format "Foo <bar@baz.com>"
						$recipient_name = '';
						if( preg_match( '/(.*)<(.+)>/', $recipient, $matches ) ) {
							if ( count( $matches ) == 3 ) {
								$recipient_name = $matches[1];
								$recipient = $matches[2];
							}
						}
						$phpmailer->AddCc( $recipient, $recipient_name );
					} catch ( phpmailerException $e ) {
						continue;
					}
				}
			}

			if ( !empty( $bcc ) ) {
				foreach ( (array) $bcc as $recipient) {
					try {
						// Break $recipient into name and address parts if in the format "Foo <bar@baz.com>"
						$recipient_name = '';
						if( preg_match( '/(.*)<(.+)>/', $recipient, $matches ) ) {
							if ( count( $matches ) == 3 ) {
								$recipient_name = $matches[1];
								$recipient = $matches[2];
							}
						}
						$phpmailer->AddBcc( $recipient, $recipient_name );
					} catch ( phpmailerException $e ) {
						continue;
					}
				}
			}




			// Set to use PHP's mail()
			$phpmailer->IsMail();

			// Set Content-Type and charset
			// If we don't have a content-type from the input headers
			if ( !isset( $content_type ) ){
				$content_type = 'text/plain';
			}


			/**
			 * Filter the wp_mail() content type.
			 *
			 * @since 2.3.0
			 *
			 * @param string $content_type Default wp_mail() content type.
			 */
			$content_type = apply_filters( 'wp_mail_content_type', $content_type );

			if($content_type == 'text/html'){
				$content_type = 'multipart/alternative; boundary="'.$boundary.'" ';

				// Set whether it's plaintext, depending on $content_type
				$phpmailer->IsHTML( true );
			}

			$phpmailer->ContentType = $content_type;





			// If we don't have a charset from the input headers
			if ( !isset( $charset ) ){
				$charset = get_bloginfo( 'charset' );
			}





			// Set the content-type and charset
			$phpmailer->CharSet = apply_filters( 'wp_mail_charset', $charset );

			// Set custom headers
			if ( !empty( $headers ) ) {
				foreach( (array) $headers as $name => $content ) {
					$phpmailer->AddCustomHeader( sprintf( '%1$s: %2$s', $name, $content ) );
				}


				if ( stripos( $content_type, 'multipart' )!==false && ! empty($boundary) && strpos($phpmailer->ContentType, 'boundary')===false){
					$phpmailer->AddCustomHeader( sprintf( "Content-Type: %s;\n\t boundary=\"%s\"", $content_type, $boundary ) );
				}

			}

			if ( !empty( $attachments ) ) {
				foreach ( $attachments as $attachment ) {
					try {
						$phpmailer->AddAttachment($attachment);
					} catch ( phpmailerException $e ) {
						continue;
					}
				}
			}






			/**
			 * Fires after PHPMailer is initialized.
			 *
			 * @since 2.2.0
			 *
			 * @param PHPMailer &$phpmailer The PHPMailer instance, passed by reference.
			 */
			do_action_ref_array( 'phpmailer_init', array( &$phpmailer ) );




			// Send!
			try {
				return $phpmailer->Send();
			} catch ( phpmailerException $e ) {
				return false;
			}






			return true;
		}

	} // end yourplugin_enabled()
?>
