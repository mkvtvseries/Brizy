<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 11/19/18
 * Time: 4:56 PM
 */

class Brizy_Editor_Forms_Api {

	const AJAX_GET_DEFAULT_FORM = 'brizy_default_form';
	const AJAX_GET_FORM = 'brizy_get_form';
	const AJAX_CREATE_FORM = 'brizy_create_form';
	const AJAX_DELETE_FORM = 'brizy_delete_form';
	//const AJAX_FORM_INTEGRATION_STATUS = 'brizy_form_integration_status';
	const AJAX_SUBMIT_FORM = 'brizy_submit_form';

	const AJAX_GET_INTEGRATION = 'brizy_get_integration';
	const AJAX_UPDATE_INTEGRATION = 'brizy_update_integration';
	const AJAX_DELETE_INTEGRATION = 'brizy_delete_integration';

	const AJAX_AUTHENTICATE_INTEGRATION = 'brizy_authenticate_integration';
	const AJAX_AUTHENTICATION_CALLBACK = 'brizy_authentication_callback';

	/**
	 * @var Brizy_Editor_Project
	 */
	private $project;

	/**
	 * @var Brizy_Editor_Post
	 */
	private $post;

	/**
	 * @return Brizy_Editor_Project
	 */
	public function get_project() {
		return $this->project;
	}

	/**
	 * @return Brizy_Editor_Post
	 */
	public function get_post() {
		return $this->post;
	}

	/**
	 * Brizy_Editor_API constructor.
	 *
	 * @param Brizy_Editor_Project $project
	 * @param Brizy_Editor_Post $post
	 */
	public function __construct( $project, $post ) {

		$this->project = $project;
		$this->post    = $post;

		$this->initialize();
	}

	private function authorize() {
		if ( ! wp_verify_nonce( $_REQUEST['hash'], Brizy_Editor_API::nonce ) ) {
			wp_send_json_error( array( 'code' => 400, 'message' => 'Bad request' ), 400 );
		}
	}

	private function generateCallback( $formId, $service ) {
		$params = array(
			'action'  => self::AJAX_AUTHENTICATION_CALLBACK,
			'form_id' => $formId,
			'service' => $service

		);

		return set_url_scheme( admin_url( 'admin-ajax.php' ) ) . '?' . http_build_query( $params );
	}


	private function initialize() {

		if ( Brizy_Editor::is_user_allowed() ) {
			add_action( 'wp_ajax_' . self::AJAX_GET_DEFAULT_FORM, array( $this, 'default_form' ) );
			add_action( 'wp_ajax_' . self::AJAX_GET_FORM, array( $this, 'get_form' ) );
			add_action( 'wp_ajax_' . self::AJAX_CREATE_FORM, array( $this, 'create_form' ) );
//			add_action( 'wp_ajax_' . self::AJAX_FORM_INTEGRATION_STATUS, array(
//				$this,
//				'update_form_integration_status'
//			) );
			add_action( 'wp_ajax_' . self::AJAX_DELETE_FORM, array( $this, 'delete_form' ) );
			add_action( 'wp_ajax_' . self::AJAX_GET_INTEGRATION, array( $this, 'getIntegration' ) );
			add_action( 'wp_ajax_' . self::AJAX_UPDATE_INTEGRATION, array( $this, 'updateIntegration' ) );
			add_action( 'wp_ajax_' . self::AJAX_DELETE_INTEGRATION, array( $this, 'deleteIntegration' ) );
			add_action( 'wp_ajax_' . self::AJAX_AUTHENTICATE_INTEGRATION, array( $this, 'authenticateIntegration' ) );
		}

		add_action( 'wp_ajax_' . self::AJAX_SUBMIT_FORM, array( $this, 'submit_form' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_SUBMIT_FORM, array( $this, 'submit_form' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_AUTHENTICATION_CALLBACK, array( $this, 'authenticationCallback' ) );
	}

	public function default_form() {
		try {
			$this->authorize();
			$current_user = wp_get_current_user();
			$form         = new Brizy_Editor_Forms_Form();
			$form->setEmailTo( $current_user->user_email );
			$this->success( $form );

		} catch ( Exception $exception ) {
			Brizy_Logger::instance()->exception( $exception );
			$this->error( $exception->getCode(), $exception->getMessage() );
			exit;
		}
	}

	public function get_form() {
		try {
			$this->authorize();

			$manager = new Brizy_Editor_Forms_Manager( Brizy_Editor_Project::get() );

			$form = $manager->getForm( $_REQUEST['form_id'] );

			if ( $form ) {
				$this->success( $form );
			}

			$this->error( 404, 'Form not found' );

		} catch ( Exception $exception ) {
			Brizy_Logger::instance()->exception( $exception );
			$this->error( $exception->getCode(), $exception->getMessage() );
			exit;
		}
	}

	public function create_form() {
		try {
			$this->authorize();

			$manager           = new Brizy_Editor_Forms_Manager( Brizy_Editor_Project::get() );
			$instance          = Brizy_Editor_Forms_Form::create_from_post();
			$validation_result = $instance->validate();

			if ( $validation_result === true ) {
				$manager->addForm( $instance );
				$this->success( $instance );
			}

			$this->error( 400, $validation_result );

		} catch ( Exception $exception ) {
			Brizy_Logger::instance()->exception( $exception );
			$this->error( $exception->getCode(), $exception->getMessage() );
			exit;
		}
	}

	public function delete_form() {
		try {
			$this->authorize();
			$manager = new Brizy_Editor_Forms_Manager( Brizy_Editor_Project::get() );
			$manager->deleteFormById( $_REQUEST['form_id'] );
			$this->success( array() );
		} catch ( Exception $exception ) {
			Brizy_Logger::instance()->exception( $exception );
			$this->error( $exception->getCode(), $exception->getMessage() );
			exit;
		}
	}

	public function submit_form() {
		try {
			$manager = new Brizy_Editor_Forms_Manager( Brizy_Editor_Storage_Common::instance() );
			/**
			 * @var Brizy_Editor_FormsCompatibility fix_Form $form ;
			 */

			$form = $manager->getForm( $_REQUEST['form_id'] );

			if ( $form->hasIntegrations() ) {
				// notify platform
				$platform = new Brizy_Editor_API_Platform();
				$platform->notifyFormSubmit( array(
					'data'             => $_REQUEST['data'],
					'project_language' => $_REQUEST['project_language'],
					'form_id'          => $form->getId(),
				) );

			}

			if ( ! $form ) {
				$this->error( 400, "Invalid form id" );
			}

			$fields = json_decode( stripslashes( $_REQUEST['data'] ) );

			if ( ! $fields ) {
				$this->error( 400, "Invalid form data" );
			}

			$form   = apply_filters( 'brizy_form', $form );
			$fields = apply_filters( 'brizy_form_submit_data', $fields, $form );

			// send email
			$headers   = array();
			$headers[] = 'Content-type: text/html; charset=UTF-8';

			$field_string = array();
			foreach ( $fields as $field ) {
				$field_string[] = "{$field->label}: " . esc_html( $field->value );
			}

			$email_body = implode( '<br>', $field_string );

			$headers    = apply_filters( 'brizy_form_email_headers', $headers, $form, $fields );
			$email_body = apply_filters( 'brizy_form_email_body', $email_body, $form, $fields );

			$result = wp_mail(
				$form->getEmailTo(),
				$form->getSubject(),
				$email_body,
				$headers
			);

			if ( $result ) {
				$this->success( array() );
			}

			$this->error( 500, "Unable to send the email" );

		} catch ( Exception $exception ) {
			Brizy_Logger::instance()->exception( $exception );
			$this->error( $exception->getCode(), $exception->getMessage() );
			exit;
		}
	}

//	public function update_form_integrations_status() {
//
//		try {
//
//			$manager = new Brizy_Editor_Forms_Manager( Brizy_Editor_Project::get() );
//			$form    = $manager->getForm( $_REQUEST['form_id'] );
//
//			if ( $form ) {
//
//				$form->setHasIntegrations( (int) $_REQUEST['has_integrations'] );
//
//				$manager->addForm( $form );
//
//				$this->success( $form );
//			}
//
//		} catch ( Exception $exception ) {
//			Brizy_Logger::instance()->exception( $exception );
//			$this->error( 500, "Invalid post id" );
//			exit;
//		}
//	}

	protected function error( $code, $message ) {
		wp_send_json_error( array( 'code' => $code, 'message' => $message ), $code );
	}

	protected function success( $data ) {
		wp_send_json( $data );
	}

	public function getIntegration() {

		$this->authorize();

		$manager = new Brizy_Editor_Forms_Manager( Brizy_Editor_Project::get() );
		$form    = $manager->getForm( $_REQUEST['form_id'] );
		if ( ! $form ) {
			$this->error( 400, "Invalid form id" );
		}
		$integrationId = $_REQUEST['integration'];
		if ( ! $integrationId ) {
			$this->error( 400, "Invalid form integration" );
		}

		$integration = $form->getIntegration( $integrationId );

		if ( $integration ) {
			$this->success( $integration );
		}

		$this->error( 404, 'Integration not found' );
	}

	public function updateIntegration() {

		$this->authorize();

		$manager = new Brizy_Editor_Forms_Manager( Brizy_Editor_Project::get() );
		$form    = $manager->getForm( $_REQUEST['form_id'] );
		if ( ! $form ) {
			$this->error( 400, "Invalid form id" );
		}
		$integrationJSON = $_REQUEST['integration'];
		if ( ! $integrationJSON ) {
			$this->error( 400, "Invalid form integration" );
		}

		$integration = Brizy_Editor_Forms_Integration::createFromSerializedData( $integrationJSON );

		$integration = $form->updateIntegration( $integration );

		if ( $integration ) {
			$this->success( $integration );
		}


		$this->error( 404, 'Integration not found' );
	}

	public function deleteIntegration() {

		$this->authorize();

		$manager = new Brizy_Editor_Forms_Manager( Brizy_Editor_Project::get() );
		$form    = $manager->getForm( $_REQUEST['form_id'] );
		if ( ! $form ) {
			$this->error( 400, "Invalid form id" );
		}
		$integrationId = $_REQUEST['integration'];
		if ( ! $integrationId ) {
			$this->error( 400, "Invalid form integration" );
		}

		$deleted = $form->deleteIntegration( $integrationId );

		if ( $deleted ) {
			$this->success( null );
		}

		$this->error( 404, 'Integration not found' );
	}

	public function authenticateIntegration() {

		$manager = new Brizy_Editor_Forms_Manager( Brizy_Editor_Project::get() );
		$form    = $manager->getForm( $_REQUEST['form_id'] );
		if ( ! $form ) {
			$this->error( 400, "Invalid form id" );
		}
		$integrationId = $_REQUEST['integration'];
		if ( ! $integrationId ) {
			$this->error( 400, "Invalid form integration" );
		}

		$this->error( 501, 'Not implemented' );
	}

	public function authenticationCallback() {

		$manager = new Brizy_Editor_Forms_Manager( Brizy_Editor_Project::get() );
		$form    = $manager->getForm( $_REQUEST['form_id'] );
		if ( ! $form ) {
			$this->error( 400, "Invalid form id" );
		}
		$serviceId = $_REQUEST['service'];
		if ( ! $serviceId ) {
			$this->error( 400, "Invalid service" );
		}

		$this->error( 501, 'Not implemented' );
	}


}