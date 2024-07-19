<?php

class RS_GF_Limited_Entries_Form {
	
	public function __construct() {
		
		// Add a custom settings page to the Gravity Forms form settings area
		add_filter( 'gform_form_settings_fields', array( $this, 'register_custom_form_settings' ), 10, 2 );
		
		// If user already submitted a form, show a confirmation message instead of the form
		add_filter( 'gform_get_form_filter', array( $this, 'maybe_show_confirmation_message' ), 20, 2 );
		
	}
	
	// Singleton instance
	protected static $instance = null;
	
	public static function get_instance() {
		if ( !isset( self::$instance ) ) self::$instance = new static();
		return self::$instance;
	}
	
	// Utilities
	
	/**
	 * Check if a form has the "Limit Entries (Per User)" setting enabled
	 *
	 * @param int|array $form Form array or form ID
	 *
	 * @return bool
	 */
	public function is_enabled( $form ) {
		if ( is_numeric($form) ) $form = GFAPI::get_form($form);
		
		return !! rgar( $form, 'limitEntriesPerUser_enabled' );
	}
	
	/**
	 * Get settings managed by this plugin
	 *
	 * @param int|array $form  Form array or form ID
	 *
	 * @return array|false {
	 *     @type int $count                How many entries are allowed per user.
	 *     @type string $period            Time period to count entries within.
	 *                                     If blank, all entries are counted.
	 *                                     Otherwise: day, week, month, or year.
	 *     @type string $user_source       Which method to identify the same user by. Default: created_by.
	 *                                     Options: created_by, user_id_field, user_email_field.
	 *     @type int $user_id_field_id     If user_source is user_id_field, use this field ID to get the user's ID.
	 *     @type int $user_email_field_id  If user_source is user_email_field, use this field ID to get the user's email.
	 *     @type string $message_type      Which message to display? Default: confirmation.
	 *                                     Options: confirmation, custom_message.
	 *     @type string $custom_message    Custom message to display if message_type is custom_message.
	 * }
	 */
	public function get_settings( $form ) {
		if ( is_numeric($form) ) $form = GFAPI::get_form($form);
		
		// Check if user limit is enabled
		if ( ! $this->is_enabled( $form ) ) return false;
		
		// Get settings
		return array(
			'count'               => rgar( $form, 'limitEntriesPerUser_count' ),
			'period'              => rgar( $form, 'limitEntriesPerUser_period' ),
			'user_source'         => rgar( $form, 'limitEntriesPerUser_user_source' ),
			'user_id_field_id'    => rgar( $form, 'limitEntriesPerUser_user_id_field_id' ),
			'user_email_field_id' => rgar( $form, 'limitEntriesPerUser_user_email_field_id' ),
			'message_type'        => rgar( $form, 'limitEntriesPerUser_message_type' ),
			'custom_message'      => rgar( $form, 'limitEntriesPerUser_message' ),
		);
	}
	
	/**
	 * Get a user's email address
	 *
	 * @param int $user_id
	 *
	 * @return string|false
	 */
	public function get_user_email( $user_id ) {
		$user = get_user_by( 'ID', $user_id );
		
		if ( $user ) {
			$email = $user->get('user_email');
		}else{
			$email = false;
		}
		
		// Allow plugins to filter the user email
		$email = apply_filters( 'rs_gf_limited_entries/get_user_email', $email, $user_id );
		
		return $email;
	}
	
	/**
	 * Get entry count for the given user, based on the settings.
	 *
	 * @param $form
	 * @param $settings
	 * @param $user_id
	 *
	 * @return array {
	 *     @type int $count Number of entries for this user
	 *     @type array $latest_entry The latest entry for this user
	 * }
	 */
	public function get_user_entry_details( $form, $settings, $user_id ) {
		
		// Search entry args
		$search = array(
			'status' => 'active',
			'field_filters' => array(),
		);
		
		$sort = array();
		
		$page = array(
			'page_size' => 1, // We only need to get one entry, though we still count the total
		);
		
		$total_count = 0;
		
		// Specify the date range based on settings
		switch ( $settings['period'] ) {
			
			case 'day':
				$search['start_date'] = date( 'Y-m-d 00:00:00' );
				$search['end_date']   = date( 'Y-m-d 23:59:59' );
				break;
			
			case 'week':
				$search['start_date'] = date( 'Y-m-d 00:00:00', strtotime( '-7 days' ) );
				$search['end_date']   = date( 'Y-m-d 23:59:59' );
				break;
			
			case 'month':
				$search['start_date'] = date( 'Y-m-01 00:00:00' );
				$search['end_date']   = date( 'Y-m-t 23:59:59' );
				break;
			
			case 'year':
				$search['start_date'] = date( 'Y-01-01 00:00:00' );
				$search['end_date']   = date( 'Y-12-31 23:59:59' );
				break;
			
			default:
				// $search['start_date'] = null;
				// $search['end_date']   = null;
				break;
			
		}
		
		// Check which field key and value to search for based on settings
		switch ( $settings['user_source'] ) {
			
			case 'user_id_field':
				$search['field_filters'][] = array(
					'key'   => $settings['user_id_field_id'],
					'value' => $user_id,
				);
				break;
			
			case 'user_email_field':
				$user_email = $this->get_user_email( $user_id );
				$search['field_filters'][] = array(
					'key'   => $settings['user_email_field_id'],
					'value' => $user_email,
				);
				break;
			
			case 'created_by':
			default:
				$search['field_filters'][] = array(
					'key'   => 'created_by',
					'value' => $user_id,
				);
				break;
			
		}
		
		// Get the entries
		$entries = GFAPI::get_entries( $form['id'], $search, $sort, $page, $total_count );
		
		$latest_entry = false;
		
		if ( $entries ) {
			$latest_entry = $entries[0];
		}
		
		return array( $total_count, $latest_entry );
	}
	
	// Hooks
	public function register_custom_form_settings( $fields, $form ) {
		
		$new_fields = array(
			array(
				'name'    => 'limitEntriesPerUser_enabled',
				'type'    => 'checkbox',
				'label'   => null,
				'tooltip' => null,
				'choices' => array(
					array(
						'name'  => 'limitEntriesPerUser_enabled',
						'label' => __( 'Enable entry limit (Per User)', 'gravityforms' ),
					),
				),
				'fields'  => array(
					array(
						'type' => 'html',
						'name' => 'limitEntriesPerUser_html_message', // If no name, dependency does not work
						'allow_html' => true,
						'html' => __( 'This option requires users to be logged in. Use the field "<a href="#gform-settings-checkbox-choice-requirelogin">Require user to be logged in</a>" below to customize the error message if not logged in.', 'rs-gf-limited-entries' ),
						'dependency' => array(
							'live'   => true,
							'fields' => array(
								array(
									'field' => 'limitEntriesPerUser_enabled',
								),
							),
						),
					),
					array(
						'name'       => 'limitEntriesPerUser_Number',
						'type'       => 'text_and_select',
						'label'      => __( 'Number of Entries (Per User)', 'gravityforms' ),
						'dependency' => array(
							'live'   => true,
							'fields' => array(
								array(
									'field' => 'limitEntriesPerUser_enabled',
								),
							),
						),
						'inputs'     => array(
							'text'   => array(
								'name'       => 'limitEntriesPerUser_count',
								'input_type' => 'number',
							),
							'select' => array(
								'name'    => 'limitEntriesPerUser_period',
								'choices' => array(
									array(
										'label' => __( 'total entries', 'gravityforms' ),
										'value' => '',
									),
									array(
										'label' => __( 'per day', 'gravityforms' ),
										'value' => 'day',
									),
									array(
										'label' => __( 'per week', 'gravityforms' ),
										'value' => 'week',
									),
									array(
										'label' => __( 'per month', 'gravityforms' ),
										'value' => 'month',
									),
									array(
										'label' => __( 'per year', 'gravityforms' ),
										'value' => 'year',
									),
								),
							),
						),
					),
					
					// Which method to identify the same user
					array(
						'name'          => 'limitEntriesPerUser_user_source',
						'label'         => __( 'User Source', 'rs-gf-limited-entries' ),
						'type'          => 'radio',
						'default_value' => 'created_by',
						'horizontal'    => false,
						'dependency' => array(
							'live'   => true,
							'fields' => array(
								array(
									'field' => 'limitEntriesPerUser_enabled',
								),
							),
						),
						'choices'       => array(
							array(
								'label' => __( 'Form Submitter ("Created By" field)', 'rs-gf-limited-entries' ),
								'value' => 'created_by',
							),
							array(
								'label' => __( 'User ID Field', 'rs-gf-limited-entries' ),
								'value' => 'user_id_field'
							),
							array(
								'label' => __( 'User Email Field', 'rs-gf-limited-entries' ),
								'value' => 'user_email_field',
							),
						),
						'fields'  => array(
							// Field to select a user field
							array(
								'name'       => 'limitEntriesPerUser_user_id_field_id',
								'label'      => __( 'User Field', 'rs-gf-limited-entries' ),
								'type'       => 'field_select',
								'args'       => array(
									'input_types' => array( 'workflow_user', 'text', 'number', 'hidden' ),
								),
								'required'   => true,
								'dependency' => array(
									'live'   => true,
									'fields' => array(
										array(
											'field' => 'limitEntriesPerUser_enabled',
										),
										array(
											'field' => 'limitEntriesPerUser_user_source',
											'values' => array( 'user_id_field' ),
										),
									),
								),
							),
							
							// Field to select a user by email
							array(
								'name'       => 'limitEntriesPerUser_user_email_field_id',
								'label'      => __( 'Email Field', 'rs-gf-limited-entries' ),
								'type'       => 'field_select',
								'args'       => array(
									'disable_first_choice' => true,
									'input_types'          => array( 'email' ),
									'append_choices'       => array(
										array( 'label' => __( 'Select an Email Field', 'rs-gf-limited-entries' ), 'value' => '' ),
									),
								),
								'required'   => true,
								'dependency' => array(
									'live'   => true,
									'fields' => array(
										array(
											'field' => 'limitEntriesPerUser_enabled',
										),
										array(
											'field' => 'limitEntriesPerUser_user_source',
											'values' => array( 'user_email_field' ),
										),
									),
								),
							),
						
						),
					),
					
					// What type of method should be displayed
					array(
						'name'          => 'limitEntriesPerUser_message_type',
						'label'         => __( 'Message Type', 'rs-gf-limited-entries' ),
						'type'          => 'radio',
						'default_value' => 'confirmation',
						'horizontal'    => false,
						'dependency' => array(
							'live'   => true,
							'fields' => array(
								array(
									'field' => 'limitEntriesPerUser_enabled',
								),
							),
						),
						'choices' => array(
							array(
								'label' => __( 'Default Confirmation', 'rs-gf-limited-entries' ),
								'value' => 'confirmation'
							),
							array(
								'label' => __( 'Custom message', 'rs-gf-limited-entries' ),
								'value' => 'custom_message',
							),
						),
						'fields'  => array(
							// Custom message to display
							array(
								'name'       => 'limitEntriesPerUser_message',
								'type'       => 'textarea',
								'label'      => esc_html__( 'Custom message', 'gravityforms' ),
								'tooltip'    => __('<strong>Require Login Message</strong> Enter a message to be displayed to users who have hit the limit (shortcodes and HTML are supported).', 'rs-gf-limited-entries' ),
								'allow_html' => true,
								'dependency' => array(
									'live'   => true,
									'fields' => array(
										array(
											'field' => 'limitEntriesPerUser_enabled',
										),
										array(
											'field' => 'limitEntriesPerUser_message_type',
											'values' => array( 'custom_message' ),
										),
									),
								),
							),
						),
					),
					
				
				),
			),
		);
		
		// Add fields to the "Restrictions" section after the field [name] = "limitEntries" (index = 0)
		$index = 0;
		
		array_splice( $fields['restrictions']['fields'], $index + 1, 0, $new_fields );
		
		return $fields;
	}
	
	/**
	 * If user already submitted a form, show a confirmation message instead of the form
	 *
	 * @param string $form_html
	 * @param array $form
	 *
	 * @return string
	 */
	public function maybe_show_confirmation_message( $form_html, $form ) {
		// Check if enabled for this form
		if ( ! $this->is_enabled( $form ) ) return $form_html;
		
		// Check if the user is logged in
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			$form_html = __( 'You must be logged in to submit this form.', 'rs-gf-limited-entries' );
			return wpautop($form_html);
		}
		
		// Get settings for this form
		$settings = $this->get_settings( $form );
		if ( ! $settings ) return $form_html;
		
		// Get the number of entries for this user
		list( $count, $latest_entry ) = $this->get_user_entry_details( $form, $settings, $user_id );
		
		// If the user has reached the limit, show a confirmation message
		if ( $settings['count'] > 0 && $count >= $settings['count'] ) {
			
			// Get the message to display
			$message = $this->get_limit_message( $form, $settings, $latest_entry );
			
			$form_html = wpautop( $message );
			
		}
		
		return $form_html;
	}
	
	/**
	 * Get the message to show when the user has reached the entry limit
	 * Applies merge tags from the user's latest entry, if given
	 *
	 * @param array $form
	 * @param array $settings
	 * @param array|null $latest_entry
	 *
	 * @return mixed
	 */
	public function get_limit_message( $form, $settings, $latest_entry ) {
		$message = false;
		
		if ( $settings['message_type'] == 'confirmation' ) {
			// Get the first confirmation message in the form settings
			if ( $form['confirmations'] ) foreach( $form['confirmations'] as $k => $confirmation ) {
				if ( $confirmation['type'] == 'message' ) {
					$message = $confirmation['message'];
					break;
				}
			}
		}
		
		if ( ! $message ) {
			$message = $settings['custom_message'];
		}
		
		if ( ! $message ) {
			$message = 'You have reached the entry limit for this form.';
		}
		
		// Allow plugins to customize the message using a filter
		$message = apply_filters( 'rs_gf_limited_entries/get_limit_message', $message, $form, $settings );
		
		// Apply entry merge tags from the latest entry
		if ( $latest_entry ) {
			$message = GFCommon::replace_variables( $message, $form, $latest_entry, false, false, false );
		}
		
		// Apply shortcodes to the message
		$message = do_shortcode( $message );
		
		return $message;
	}
	
}

RS_GF_Limited_Entries_Form::get_instance();