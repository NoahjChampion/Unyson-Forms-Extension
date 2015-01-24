<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

class FW_Extension_Contact_Form extends FW_Extension_Forms_Form {

	public function _init() {
		if ( ! is_admin() ) {
			$this->add_actions();
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_form_builder_type() {
		return 'form-builder';
	}

	public function get_form_builder_value( $form_id ) {

		$form = FW_Session::get( $this->get_name() . '-forms/' . $form_id );

		return ( empty( $form['form'] ) ? array() : $form['form'] );
	}

	public function render( $data ) {
		$form = $data['form'];

		if ( empty( $form ) ) {
			return '';
		}

		$form_id = $data['id'];
		$data['time'] = time();
		FW_Session::set( $this->get_name() . '-forms/' . $form_id, $data );
		do_action( 'fw-' . $this->get_name() . '-render-form' );

		$submit_button = $this->render_view( 'form',
			array(
				'submit_button_text' => fw_ext_contact_form_search_option(
					'submit_button_text',
					$data,
					__( 'Send', 'fw' )
				)
			)
		);

		return fw_ext( 'forms' )->render_form( $form_id, $form, $this->get_name(), $submit_button );
	}

	public function process_form( $form_values, $data ) {
		$flash_id = 'fw_ext_contact_form_process';

		$form_id = FW_Request::POST( 'fw_ext_forms_form_id' );

		if ( empty( $form_id ) ) {
			FW_Flash_Messages::add(
				$flash_id,
				__( 'Unable to process the form', 'fw' ),
				'error'
			);
		}

		$form = FW_Session::get( $this->get_name() . '-forms/' . $form_id );

		if ( empty( $form ) ) {
			FW_Flash_Messages::add(
				$flash_id,
				__( 'Unable to process the form', 'fw' ),
				'error'
			);
		}

		$to = $form['email_to'];

		if ( ! filter_var( $to, FILTER_VALIDATE_EMAIL ) ) {
			FW_Flash_Messages::add(
				$flash_id,
				__( 'Invalid destination email (please contact the site administrator)', 'fw' ),
				'error'
			);

			return;
		}


		$result = fw_ext_mailer_send_mail(
			$to,
			get_the_title( $form_id ),
			$this->render_view( 'email', array(
				'form_values'       => $form_values,
				'shortcode_to_item' => $data['shortcode_to_item'],
			) )
		);

		if ( $result['status'] ) {
			FW_Flash_Messages::add(
				$flash_id,
				fw_get_db_post_option( $form_id, 'success_message', __( 'Message sent!', 'fw' ) )
			);
		} else {
			FW_Flash_Messages::add(
				$flash_id,
				fw_get_db_post_option( $form_id, 'failure_message', __( 'Oops something went wrong.', 'fw' ) ) .
				' <em>(' . $result['message'] . ')</em>'
			);
		}
	}

	/**
	 * @internal
	 */
	public function _action_post_form_type_save() {
		if ( ! fw_ext_mailer_is_configured() ) {
			FW_Flash_Messages::add(
				'fw-ext-forms-' . $this->get_form_type() . '-mailer',
				str_replace(
					array(
						'{mailer_link}'
					),
					array(
						// the fw()->extensions->manager->get_extension_link() method is available starting with v2.1.7
						version_compare( fw()->manifest->get_version(), '2.1.7', '>=' )
							? fw_html_tag( 'a',
							array( 'href' => fw()->extensions->manager->get_extension_link( 'forms' ) ),
							__( 'Mailer', 'fw' ) )
							: __( 'Mailer', 'fw' )
					),
					__( 'Please configure the {mailer_link} extension.', 'fw' )
				),
				'error'
			);
		}
	}

	/**
	 * @internal
	 */
	public function _action_theme_remove_forms_from_session() {
		$forms = FW_Session::get( $this->get_name() . '-forms' );
		if ( !is_array( $forms ) || empty($forms) ) {
			return;
		}

		foreach ( $forms as $key => $form ) {
			if ( time() - $form['time'] >= ( 10800 ) ) {
				FW_Session::del( $this->get_name() . '-forms/' . $key );
			}
		}
	}

	private function add_actions() {
		add_action( 'wp_loaded', array( $this, '_action_theme_remove_forms_from_session' ), 999 );
	}
}