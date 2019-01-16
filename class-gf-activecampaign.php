<?php

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

GFForms::include_feed_addon_framework();

/**
 * Gravity Forms ActiveCampaign Add-On.
 *
 * @since     1.0
 * @package   GravityForms
 * @author    Rocketgenius
 * @copyright Copyright (c) 2016, Rocketgenius
 */
class GFActiveCampaign extends GFFeedAddOn {

	/**
	 * Contains an instance of this class, if available.
	 *
	 * @since  1.0
	 * @access private
	 * @var    object $_instance If available, contains an instance of this class.
	 */
	private static $_instance = null;

	/**
	 * Defines the version of the ActiveCampaign Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_version Contains the version, defined from activecampaign.php
	 */
	protected $_version = GF_ACTIVECAMPAIGN_VERSION;

	/**
	 * Defines the minimum Gravity Forms version required.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_min_gravityforms_version The minimum version required.
	 */
	protected $_min_gravityforms_version = '1.9.14.26';

	/**
	 * Defines the plugin slug.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_slug The slug used for this plugin.
	 */
	protected $_slug = 'gravityformsactivecampaign';

	/**
	 * Defines the main plugin file.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_path The path to the main plugin file, relative to the plugins folder.
	 */
	protected $_path = 'gravityformsactivecampaign/activecampaign.php';

	/**
	 * Defines the full path to this class file.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_full_path The full path.
	 */
	protected $_full_path = __FILE__;

	/**
	 * Defines the URL where this Add-On can be found.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string The URL of the Add-On.
	 */
	protected $_url = 'http://www.gravityforms.com';

	/**
	 * Defines the title of this Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_title The title of the Add-On.
	 */
	protected $_title = 'Gravity Forms ActiveCampaign Add-On';

	/**
	 * Defines the short title of the Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_short_title The short title.
	 */
	protected $_short_title = 'ActiveCampaign';

	/**
	 * Defines if Add-On should use Gravity Forms servers for update data.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    bool
	 */
	protected $_enable_rg_autoupgrade = true;
	/**
	 * Defines the capability needed to access the Add-On settings page.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_settings_page The capability needed to access the Add-On settings page.
	 */
	protected $_capabilities_settings_page = 'gravityforms_activecampaign';

	/**
	 * Defines the capability needed to access the Add-On form settings page.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_form_settings The capability needed to access the Add-On form settings page.
	 */
	protected $_capabilities_form_settings = 'gravityforms_activecampaign';

	/**
	 * Defines the capability needed to uninstall the Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_uninstall The capability needed to uninstall the Add-On.
	 */
	protected $_capabilities_uninstall = 'gravityforms_activecampaign_uninstall';

	/**
	 * Defines the capabilities needed for the Post Creation Add-On
	 *
	 * @since  1.0
	 * @access protected
	 * @var    array $_capabilities The capabilities needed for the Add-On
	 */
	protected $_capabilities = array( 'gravityforms_activecampaign', 'gravityforms_activecampaign_uninstall' );

	/**
	 * Stores an instance of the ActiveCampaign API library, if initialized.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    object $api If initialized, an instance of the ActiveCampaign API library.
	 */
	protected $api = null;

	/**
	 * New ActiveCampaign fields that need to be created when saving feed.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    object $api When saving feed, new ActiveCampaign fields that need to be created.
	 */
	protected $_new_custom_fields = array();

	/**
	 * Get an instance of this class.
	 *
	 * @return GFActiveCampaign
	 */
	public static function get_instance() {

		if ( null === self::$_instance ) {
			self::$_instance = new GFActiveCampaign();
		}

		return self::$_instance;

	}


	// # FEED PROCESSING -----------------------------------------------------------------------------------------------


	/**
	 * Process the feed, subscribe the user to the list.
	 *
	 * @param array $feed The feed object to be processed.
	 * @param array $entry The entry object currently being processed.
	 * @param array $form The form object currently being processed.
	 *
	 * @return bool|void
	 */
	public function process_feed( $feed, $entry, $form ) {

		$this->log_debug( __METHOD__ . '(): Processing feed.' );

		/* If API instance is not initialized, exit. */
		if ( ! $this->initialize_api() ) {

			$this->log_error( __METHOD__ . '(): Failed to set up the API.' );

			return;

		}

		/* Setup mapped fields array. */
		$mapped_fields = $this->get_field_map_fields( $feed, 'fields' );

		/* Setup contact array. */
		$contact = array(
			'email'      => $this->get_field_value( $form, $entry, rgar( $mapped_fields, 'email' ) ),
		);

		/* If the email address is invalid or empty, exit. */
		if ( GFCommon::is_invalid_or_empty_email( $contact['email'] ) ) {
			$this->log_error( __METHOD__ . '(): Aborting. Invalid email address: ' . rgar( $contact, 'email' ) );

			return;
		}

		/**
		 * Prevent empty form fields from erasing values already stored in Active Campaign
		 * when updating an existing subscriber.
		 *
		 * @since 1.5
		 *
		 * @param bool  $override If blank fields should override values already stored in Active Campaign
		 * @param array $form     The form object.
		 * @param array $entry    The entry object.
		 * @param array $feed     The feed object.
		 */
		$override_empty_fields = gf_apply_filters( 'gform_activecampaign_override_empty_fields', array( $form['id'] ), true, $form, $entry, $feed );


		/* Assiging properties that have been mapped */
		$properties = array( 'first_name','last_name','phone','orgname' );
		foreach ( $properties as $property ) {
			$field_value = $this->get_field_value( $form, $entry, rgar( $mapped_fields, $property ) );
			$is_mapped = ! rgempty( $property, $mapped_fields );

			/* Only send fields that are mapped. Also, make sure blank values are ok to override existing data */
			if ( $is_mapped && ( $override_empty_fields || ! empty( $field_value ) ) ) {
				$contact[ $property ] = $field_value;
			}
		}



		/* Prepare tags. */
		if ( rgars( $feed, 'meta/tags' ) ) {

			$tags            = GFCommon::replace_variables( $feed['meta']['tags'], $form, $entry, false, false, false, 'text' );
			$tags            = array_map( 'trim', explode( ',', $tags ) );
			$contact['tags'] = gf_apply_filters( 'gform_activecampaign_tags', $form['id'], $tags, $feed, $entry, $form );

		}

		/* Add list to contact array. */
		$list_id                               = $feed['meta']['list'];
		$contact[ 'p[' . $list_id . ']' ]      = $list_id;
		$contact[ 'status[' . $list_id . ']' ] = '1';

		/* Add custom fields to contact array. */
		$custom_fields = rgars( $feed, 'meta/custom_fields' );
		if ( is_array( $custom_fields ) ) {
			foreach ( $feed['meta']['custom_fields'] as $custom_field ) {

				if ( rgblank( $custom_field['key'] ) || 'gf_custom' === $custom_field['key'] || rgblank( $custom_field['value'] ) ) {
					continue;
				}

				$field_value = $this->get_field_value( $form, $entry, $custom_field['value'] );

				if ( rgblank( $field_value ) && ! $override_empty_fields ) {
					continue;
				}

				$contact_key = 'field[' . $custom_field['key'] . ',0]';

				//If contact is already set, don't override it with fields that are hidden by conditional logic
				$is_hidden = GFFormsModel::is_field_hidden( $form, GFFormsModel::get_field( $form, $custom_field['value'] ), array(), $entry );
				if ( isset( $contact[ $contact_key ] ) && $is_hidden ) {
					continue;
				}

				$contact[ $contact_key ] = $field_value;

			}
		}

		/* Set instant responders flag if needed. */
		if ( 1 == $feed['meta']['instant_responders'] ) {
			$contact[ 'instantresponders[' . $list_id . ']' ] = $feed['meta']['instant_responders'];
		}

		/* Set last message flag. */
		$contact[ 'lastmessage[' . $list_id . ']' ] = $feed['meta']['last_message'];

		/* Add opt-in form if set. */
		if ( isset( $feed['meta']['double_optin_form'] ) ) {
			$contact['form'] = $feed['meta']['double_optin_form'];
		}

		/**
		 * Allows the contact properties to be overridden before the contact_sync request is sent to the API.
		 *
		 * @param array $contact The contact properties.
		 * @param array $entry The entry currently being processed.
		 * @param array $form The form object the current entry was created from.
		 * @param array $feed The feed which is currently being processed.
		 *
		 * @since 1.3.5
		 */
		$contact = apply_filters( 'gform_activecampaign_contact_pre_sync', $contact, $entry, $form, $feed );
		$contact = apply_filters( 'gform_activecampaign_contact_pre_sync_' . $form['id'], $contact, $entry, $form, $feed );

		/* Sync the contact. */
		$this->log_debug( __METHOD__ . '(): Contact to be added => ' . print_r( $contact, true ) );
		$sync_contact = $this->api->sync_contact( $contact );

		if ( $sync_contact['result_code'] == 1 ) {

			$this->log_debug( __METHOD__ . "(): {$contact['email']} has been added; {$sync_contact['result_message']}." );

			/* Add note. */
			if( rgars( $feed, 'meta/note' ) ) {

				$note = GFCommon::replace_variables( $feed['meta']['note'], $form, $entry, false, false, false, 'text' );

				/**
				 * Filter the note to be created for this contact.
				 *
				 * @since 1.6
				 *
				 * @param array $note {
				 *     Properties that will be used to create and assign the note.
				 *
				 *     @type int    $contact_id Contact ID to associate the note with.
				 *     @type int    $list_id    List ID to associate the note with.
				 *     @type string $note       Actual note content. HTML will be stripped.
				 *
				 * }
				 * @param array $feed  Current feed being processed.
				 * @param array $entry Current entry object.
				 * @param array $form  Current form object.
				 */
				$note = gf_apply_filters( array( 'gform_activecampaign_note', $form['id'] ), array(
					'contact_id' => $sync_contact['subscriber_id'],
					'list_id'    => $list_id,
					'note'       => $note
				), $feed, $entry, $form );

				$result = $this->api->add_note( $note['contact_id'], $note['list_id'], $note['note'] );
				if( $result['result_code'] == 1 ) {
					$this->log_debug( sprintf( '%s(): Note has been added: %s. %s.', __METHOD__, print_r( $note, true ), $result['result_message'] ) );
				} else {
					$this->log_debug( sprintf( '%s(): Note was not added: %s. %s.', __METHOD__, print_r( $note, true ), $result['result_message'] ) );
				}

			}

			return true;

		} else {

			$this->log_error( __METHOD__ . "(): {$contact['email']} was not added; {$sync_contact['result_message']}" );

			return false;

		}

	}

	// # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------

	/**
	 * Plugin starting point. Handles hooks, loading of language files and PayPal delayed payment support.
	 */
	public function init() {

		parent::init();

		$this->add_delayed_payment_support(
			array(
				'option_label' => esc_html__( 'Subscribe contact to ActiveCampaign only when payment is received.', 'gravityformsactivecampaign' )
			)
		);

	}

	/**
	 * Return the stylesheets which should be enqueued.
	 *
	 * @return array
	 */
	public function styles() {

		$min    = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';
		$styles = array(
			array(
				'handle'  => 'gform_activecampaign_form_settings_css',
				'src'     => $this->get_base_url() . "/css/form_settings{$min}.css",
				'version' => $this->_version,
				'enqueue' => array(
					array( 'admin_page' => array( 'form_settings' ) ),
				),
			),
		);

		return array_merge( parent::styles(), $styles );

	}

	// # PLUGIN SETTINGS -----------------------------------------------------------------------------------------------

	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {

		return array(
			array(
				'title'       => '',
				'description' => $this->plugin_settings_description(),
				'fields'      => array(
					array(
						'name'              => 'api_url',
						'label'             => esc_html__( 'API URL', 'gravityformsactivecampaign' ),
						'type'              => 'text',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'has_valid_api_url' )
					),
					array(
						'name'              => 'api_key',
						'label'             => esc_html__( 'API Key', 'gravityformsactivecampaign' ),
						'type'              => 'text',
						'class'             => 'large',
						'feedback_callback' => array( $this, 'initialize_api' )
					),
					array(
						'type'     => 'save',
						'messages' => array(
							'success' => esc_html__( 'ActiveCampaign settings have been updated.', 'gravityformsactivecampaign' )
						),
					),
				),
			),
		);

	}

	/**
	 * Prepare plugin settings description.
	 *
	 * @return string
	 */
	public function plugin_settings_description() {

		$description = '<p>';
		$description .= sprintf(
			esc_html__( 'ActiveCampaign makes it easy to send email newsletters to your customers, manage your subscriber lists, and track campaign performance. Use Gravity Forms to collect customer information and automatically add it to your ActiveCampaign list. If you don\'t have an ActiveCampaign account, you can %1$ssign up for one here.%2$s', 'gravityformsactivecampaign' ),
			'<a href="http://www.activecampaign.com/" target="_blank">', '</a>'
		);
		$description .= '</p>';

		if ( ! $this->initialize_api() ) {

			$description .= '<p>';
			$description .= esc_html__( 'Gravity Forms ActiveCampaign Add-On requires your API URL and API Key, which can be found in the API tab on the account settings page.', 'gravityformsactivecampaign' );
			$description .= '</p>';

		}

		return $description;

	}

	// ------- Feed page -------

	/**
	 * Prevent feeds being listed or created if the api key isn't valid.
	 *
	 * @return bool
	 */
	public function can_create_feed() {

		return $this->initialize_api();

	}

	/**
	 * Enable feed duplication.
	 *
	 * @access public
	 * @return bool
	 */
	public function can_duplicate_feed( $id ) {

		return true;

	}

	/**
	 * If the api keys are invalid or empty return the appropriate message.
	 *
	 * @return string
	 */
	public function configure_addon_message() {

		$settings_label = sprintf( esc_html__( '%s Settings', 'gravityforms' ), $this->get_short_title() );
		$settings_link  = sprintf( '<a href="%s">%s</a>', esc_url( $this->get_plugin_settings_url() ), $settings_label );

		if ( is_null( $this->initialize_api() ) ) {

			return sprintf( esc_html__( 'To get started, please configure your %s.', 'gravityforms' ), $settings_link );
		}

		return sprintf( esc_html__( 'Please make sure you have entered valid API credentials on the %s page.', 'gravityformsactivecampaign' ), $settings_link );

	}

	/**
	 * Configures which columns should be displayed on the feed list page.
	 *
	 * @return array
	 */
	public function feed_list_columns() {

		return array(
			'feed_name' => esc_html__( 'Name', 'gravityformsactivecampaign' ),
			'list'      => esc_html__( 'ActiveCampaign List', 'gravityformsactivecampaign' )
		);

	}

	/**
	 * Returns the value to be displayed in the ActiveCampaign List column.
	 *
	 * @param array $feed The feed being included in the feed list.
	 *
	 * @return string
	 */
	public function get_column_value_list( $feed ) {

		/* If ActiveCampaign instance is not initialized, return campaign ID. */
		if ( ! $this->initialize_api() ) {
			return $feed['meta']['list'];
		}

		/* Get campaign and return name */
		$list = $this->api->get_list( $feed['meta']['list'] );

		return ( $list['result_code'] == 1 ) ? $list['name'] : $feed['meta']['list'];

	}

	/**
	 * Configures the settings which should be rendered on the feed edit page.
	 *
	 * @return array The feed settings.
	 */
	public function feed_settings_fields() {

		/* Build fields array. */
		$fields = array(
			array(
				'name'          => 'feed_name',
				'label'         => esc_html__( 'Feed Name', 'gravityformsactivecampaign' ),
				'type'          => 'text',
				'required'      => true,
				'default_value' => $this->get_default_feed_name(),
				'class'         => 'medium',
				'tooltip'       => '<h6>' . esc_html__( 'Name', 'gravityformsactivecampaign' ) . '</h6>' . esc_html__( 'Enter a feed name to uniquely identify this setup.', 'gravityformsactivecampaign' ),
			),
			array(
				'name'     => 'list',
				'label'    => esc_html__( 'ActiveCampaign List', 'gravityformsactivecampaign' ),
				'type'     => 'select',
				'required' => true,
				'choices'  => $this->lists_for_feed_setting(),
				'onchange' => "jQuery(this).parents('form').submit();",
				'tooltip'  => '<h6>' . esc_html__( 'ActiveCampaign List', 'gravityformsactivecampaign' ) . '</h6>' . esc_html__( 'Select which ActiveCampaign list this feed will add contacts to.', 'gravityformsactivecampaign' )
			),
			array(
				'name'       => 'fields',
				'label'      => esc_html__( 'Map Fields', 'gravityformsactivecampaign' ),
				'type'       => 'field_map',
				'dependency' => 'list',
				'field_map'  => $this->fields_for_feed_mapping(),
				'tooltip'    => '<h6>' . esc_html__( 'Map Fields', 'gravityformsactivecampaign' ) . '</h6>' . esc_html__( 'Select which Gravity Form fields pair with their respective ActiveCampaign fields.', 'gravityformsactivecampaign' )
			),
			array(
				'name'       => 'custom_fields',
				'label'      => '',
				'type'       => 'dynamic_field_map',
				'dependency' => 'list',
				'field_map'  => $this->custom_fields_for_feed_setting(),
			),
			array(
				'name'       => 'tags',
				'type'       => 'text',
				'label'      => esc_html__( 'Tags', 'gravityformsactivecampaign' ),
				'dependency' => 'list',
				'class'      => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
			),
			array(
				'name'       => 'note',
				'type'       => 'textarea',
				'label'      => esc_html__( 'Note', 'gravityformsactivecampaign' ),
				'dependency' => 'list',
				'class'      => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
			)
		);

		/* Add double opt-in form field if forms exist. */
		$forms = $this->forms_for_feed_setting();

		if ( count( $forms ) > 1 ) {

			$fields[] = array(
				'name'       => 'double_optin_form',
				'label'      => esc_html__( 'Double Opt-In Form', 'gravityformsactivecampaign' ),
				'type'       => 'select',
				'dependency' => 'list',
				'choices'    => $this->forms_for_feed_setting(),
				'tooltip'    => '<h6>' . esc_html__( 'Double Opt-In Form', 'gravityformsactivecampaign' ) . '</h6>' . esc_html__( 'Select which ActiveCampaign form will be used when exporting to ActiveCampaign to send the opt-in email.', 'gravityformsactivecampaign' )
			);

		}

		/* Add feed condition and options fields. */
		$fields[] = array(
			'name'       => 'feed_condition',
			'label'      => esc_html__( 'Conditional Logic', 'gravityformsactivecampaign' ),
			'type'       => 'feed_condition',
			'dependency' => 'list',
			'tooltip'    => '<h6>' . esc_html__( 'Conditional Logic', 'gravityformsactivecampaign' ) . '</h6>' . esc_html__( 'When conditional logic is enabled, form submissions will only be exported to ActiveCampaign when the condition is met. When disabled, all form submissions will be exported.', 'gravityformsactivecampaign' )
		);
		$fields[] = array(
			'name'       => 'options',
			'label'      => esc_html__( 'Options', 'gravityformsactivecampaign' ),
			'type'       => 'checkbox',
			'dependency' => 'list',
			'choices'    => array(
				array(
					'name'          => 'instant_responders',
					'label'         => esc_html__( 'Instant Responders', 'gravityformsactivecampaign' ),
					'default_value' => 1,
					'tooltip'       => '<h6>' . esc_html__( 'Instant Responders', 'gravityformsactivecampaign' ) . '</h6>' . esc_html__( 'When the instant responders option is enabled, ActiveCampaign will send any instant responders setup when the contact is added to the list. This option is not available to users on a free trial.', 'gravityformsactivecampaign' ),
				),
				array(
					'name'          => 'last_message',
					'label'         => esc_html__( 'Send the last broadcast campaign', 'gravityformsactivecampaign' ),
					'default_value' => 0,
					'tooltip'       => '<h6>' . esc_html__( 'Send the last broadcast campaign', 'gravityformsactivecampaign' ) . '</h6>' . esc_html__( 'When the send the last broadcast campaign option is enabled, ActiveCampaign will send the last campaign sent out to the list to the contact being added. This option is not available to users on a free trial.', 'gravityformsactivecampaign' ),
				),
			)
		);


		return array(
			array(
				'title'  => '',
				'fields' => $fields
			)
		);

	}

	/**
	 * Fork of maybe_save_feed_settings to create new ActiveCampaign custom fields.
	 *
	 * @param int $feed_id The current Feed ID.
	 * @param int $form_id The current Form ID.
	 *
	 * @return int
	 */
	public function maybe_save_feed_settings( $feed_id, $form_id ) {

		if ( ! rgpost( 'gform-settings-save' ) ) {
			return $feed_id;
		}

		// store a copy of the previous settings for cases where action would only happen if value has changed
		$feed = $this->get_feed( $feed_id );
		$this->set_previous_settings( $feed['meta'] );

		$settings = $this->get_posted_settings();
		$settings = $this->create_new_custom_fields( $settings );
		$sections = $this->get_feed_settings_fields();
		$settings = $this->trim_conditional_logic_vales( $settings, $form_id );

		$is_valid = $this->validate_settings( $sections, $settings );
		$result   = false;

		if ( $is_valid ) {
			$feed_id = $this->save_feed_settings( $feed_id, $form_id, $settings );
			if ( $feed_id ) {
				GFCommon::add_message( $this->get_save_success_message( $sections ) );
			} else {
				GFCommon::add_error_message( $this->get_save_error_message( $sections ) );
			}
		} else {
			GFCommon::add_error_message( $this->get_save_error_message( $sections ) );
		}

		return $feed_id;
	}

	/**
	 * Prepare ActiveCampaign lists for feed field
	 *
	 * @return array
	 */
	public function lists_for_feed_setting() {

		$lists = array(
			array(
				'label' => esc_html__( 'Select a List', 'gravityformsactivecampaign' ),
				'value' => ''
			)
		);

		/* If ActiveCampaign API credentials are invalid, return the lists array. */
		if ( ! $this->initialize_api() ) {
			return $lists;
		}

		/* Get available ActiveCampaign lists. */
		$ac_lists = $this->api->get_lists();

		/* Add ActiveCampaign lists to array and return it. */
		foreach ( $ac_lists as $list ) {

			if ( ! is_array( $list ) ) {
				continue;
			}

			$lists[] = array(
				'label' => $list['name'],
				'value' => $list['id']
			);

		}

		return $lists;

	}

	/**
	 * Prepare fields for feed field mapping.
	 *
	 * @return array
	 */
	public function fields_for_feed_mapping() {

		$email_field = array(
			'name'       => 'email',
			'label'      => esc_html__( 'Email Address', 'gravityformsactivecampaign' ),
			'required'   => true,
			'tooltip'  => '<h6>' . esc_html__( 'Email Field Mapping', 'gravityformsactivecampaign' ) . '</h6>' . sprintf( esc_html__( 'Only email and hidden fields are available to be mapped. To add support for other field types, visit %sour documentation site%s', 'gravityformsactivecampaign' ), '<a href="https://docs.gravityforms.com/category/activecampaign/">', '</a>' ),
		);

		/**
		 * Allows the list of supported fields types for the email field map to be changed.
		 * Return an array of field types or true (to allow all field types)
		 *
		 * @since 1.5
		 *
		 * @param array|bool $field_types Array of field types or "true" for all field types.
		 */
		$field_types = apply_filters( 'gform_activecampaign_supported_field_types_email_map', array( 'email', 'hidden' ) );

		if ( $field_types !== true & is_array( $field_types ) ) {
			$email_field['field_type'] = $field_types;
		}

		return array(
			$email_field,
			array(
				'name'     => 'first_name',
				'label'    => esc_html__( 'First Name', 'gravityformsactivecampaign' ),
				'required' => false
			),
			array(
				'name'     => 'last_name',
				'label'    => esc_html__( 'Last Name', 'gravityformsactivecampaign' ),
				'required' => false
			),
			array(
				'name'     => 'phone',
				'label'    => esc_html__( 'Phone Number', 'gravityformsactivecampaign' ),
				'required' => false
			),
			array(
				'name'     => 'orgname',
				'label'    => esc_html__( 'Organization Name', 'gravityformsactivecampaign' ),
				'required' => false
			),
		);

	}

	/**
	 * Prepare custom fields for feed field mapping.
	 *
	 * @return array
	 */
	public function custom_fields_for_feed_setting() {

		$fields = array();

		/* If ActiveCampaign API credentials are invalid, return the fields array. */
		if ( ! $this->initialize_api() ) {
			return $fields;
		}

		/* Get available ActiveCampaign fields. */
		$ac_fields = $this->api->get_custom_fields();

		/* If no ActiveCampaign fields exist, return the fields array. */
		if ( empty( $ac_fields ) ) {
			return $fields;
		}

		/* If ActiveCampaign fields exist, add them to the fields array. */
		if ( $ac_fields['result_code'] == 1 ) {

			foreach ( $ac_fields as $field ) {

				if ( ! is_array( $field ) ) {
					continue;
				}

				$fields[] = array(
					'label' => $field['title'],
					'value' => $field['id']
				);


			}

		}

		if ( ! empty( $this->_new_custom_fields ) ) {

			foreach ( $this->_new_custom_fields as $new_field ) {

				$found_custom_field = false;
				foreach ( $fields as $field ) {

					if ( $field['value'] == $new_field['value'] ) {
						$found_custom_field = true;
					}

				}

				if ( ! $found_custom_field ) {
					$fields[] = array(
						'label' => $new_field['label'],
						'value' => $new_field['value']
					);
				}

			}

		}


		if ( empty( $fields ) ) {
			return $fields;
		}

		// Add standard "Select a Custom Field" option.
		$standard_field = array(
			array(
				'label' => esc_html__( 'Select a Custom Field', 'gravityformsactivecampaign' ),
				'value' => '',
			),
		);
		$fields = array_merge( $standard_field, $fields );

		/* Add "Add Custom Field" to array. */
		$fields[] = array(
			'label' => esc_html__( 'Add Custom Field', 'gravityformsactivecampaign' ),
			'value' => 'gf_custom',
		);

		return $fields;

	}

	/**
	 * Prepare ActiveCampaign forms for feed field.
	 *
	 * @return array
	 */
	public function forms_for_feed_setting() {

		$forms = array(
			array(
				'label' => esc_html__( 'Select a Form', 'gravityformsactivecampaign' ),
				'value' => '',
			),
		);

		// If ActiveCampaign API credentials are invalid, return the forms array.
		if ( ! $this->initialize_api() ) {
			return $forms;
		}

		// Get list ID.
		$current_feed = $this->get_current_feed();
		$list_id      = rgpost( '_gaddon_setting_list' ) ? rgpost( '_gaddon_setting_list' ) : $current_feed['meta']['list'];

		// Get available ActiveCampaign forms.
		$ac_forms = $this->api->get_forms();

		// Add ActiveCampaign forms to array and return it.
		if ( ! empty( $ac_forms ) ) {

			foreach ( $ac_forms as $form ) {

				if ( ! is_array( $form ) ) {
					continue;
				}

				if ( $form['sendoptin'] == 0 || ! is_array( $form['lists'] ) || ! in_array( $list_id, $form['lists'] ) ) {
					continue;
				}

				$forms[] = array(
					'label' => $form['name'],
					'value' => $form['id'],
				);

			}

		}

		return $forms;

	}


	// # HELPERS -------------------------------------------------------------------------------------------------------

	/**
	 * Create new ActiveCampaign custom fields.
	 *
	 * @param array $settings
	 *
	 * @return array
	 */
	public function create_new_custom_fields( $settings ) {

		global $_gaddon_posted_settings;

		/* If no custom fields are set or if the API credentials are invalid, return settings. */
		if ( empty( $settings['custom_fields'] ) || ! $this->initialize_api() ) {
			return $settings;
		}

		/* Loop through each custom field. */
		foreach ( $settings['custom_fields'] as $index => &$field ) {

			/* If no custom key is set, move on. */
			if ( rgblank( $field['custom_key'] ) ) {
				continue;
			}

			$custom_key = $field['custom_key'];

			$perstag = trim( $custom_key ); // Set shortcut name to custom key
			$perstag = str_replace( ' ', '_', $custom_key ); // Remove all spaces
			$perstag = preg_replace( '([^\w\d])', '', $custom_key ); // Strip all custom characters
			$perstag = strtoupper( $custom_key ); // Set to lowercase

			/* Prepare new field to add. */
			$custom_field = array(
				'title' => $custom_key,
				'type'  => 1,
				'req'   => 0,
				'p[0]'  => 0
			);

			/* Add new field. */
			$new_field = $this->api->add_custom_field( $custom_field );

			/* Replace key for field with new shortcut name and reset custom key. */
			if ( $new_field['result_code'] == 1 ) {

				$field['key']        = $new_field['fieldid'];
				$field['custom_key'] = '';

				/* Update POST field to ensure front-end display is up-to-date. */
				$_gaddon_posted_settings['custom_fields'][ $index ]['key']        = $new_field['fieldid'];
				$_gaddon_posted_settings['custom_fields'][ $index ]['custom_key'] = '';

				/* Push to new custom fields array to update the UI. */
				$this->_new_custom_fields[] = array(
					'label' => $custom_key,
					'value' => $new_field['fieldid'],
				);

			}

		}

		return $settings;

	}

	/**
	 * Checks validity of ActiveCampaign API credentials and initializes API if valid.
	 *
	 * @return bool|null
	 */
	public function initialize_api() {

		if ( ! is_null( $this->api ) ) {
			return true;
		}

		/* Load the ActiveCampaign API library. */
		require_once 'includes/class-gf-activecampaign-api.php';

		/* Get the plugin settings */
		$settings = $this->get_plugin_settings();

		/* If any of the account information fields are empty, return null. */
		if ( rgblank( $settings['api_url'] ) || rgblank( $settings['api_key'] ) ) {
			return null;
		}

		// Test API URL.
		$valid_api_url = $this->has_valid_api_url( $settings['api_url'] );
		if ( ! $valid_api_url ) {
			return false;
		}

		$this->log_debug( __METHOD__ . "(): Validating API info for {$settings['api_url']} / {$settings['api_key']}." );

		$activecampaign = new GF_ActiveCampaign_API( $settings['api_url'], $settings['api_key'] );

		try {

			/* Run API test. */
			$activecampaign->auth_test();

			/* Log that test passed. */
			$this->log_debug( __METHOD__ . '(): API credentials are valid.' );

			/* Assign ActiveCampaign object to the class. */
			$this->api = $activecampaign;

			return true;

		} catch ( Exception $e ) {

			/* Log that test failed. */
			$this->log_error( __METHOD__ . '(): API credentials are invalid; ' . $e->getMessage() );

			return false;

		}

	}

	/**
	 * Checks if API URL is valid.
	 *
	 * @param string $api_url The API URL.
	 *
	 * @return bool|null
	 */
	public function has_valid_api_url( $api_url ) {

		/* If no API URL is set, return null. */
		if ( rgblank( $api_url ) ) {
			return null;
		}

		$this->log_debug( __METHOD__ . "(): Validating API url {$api_url}." );

		/* Setup request URL. */
		$request_url = untrailingslashit( $api_url ) . '/admin/api.php?api_action=list_view&api_output=json';

		/* Setup API request. */
		$response = wp_remote_get( $request_url );

		/* If there was a failure on the request, return false. */
		if ( is_a( $response, 'WP_Error' ) ) {
			return false;
		}

		/* Return validity based on content type. */
		return ( strpos( $response['headers']['content-type'], 'application/json' ) !== false );

	}


	// # TO 1.2 MIGRATION ----------------------------------------------------------------------------------------------

	/**
	 * Checks if a previous version was installed and if the tags setting needs migrating from field map to input field.
	 *
	 * @param string $previous_version The version number of the previously installed version.
	 */
	public function upgrade( $previous_version ) {

		$previous_is_pre_tags_change = ! empty( $previous_version ) && version_compare( $previous_version, '1.2', '<' );

		if ( $previous_is_pre_tags_change ) {

			$feeds = $this->get_feeds();

			foreach ( $feeds as &$feed ) {
				$merge_tag = '';

				if ( ! empty( $feed['meta']['fields_tags'] ) ) {

					if ( is_numeric( $feed['meta']['fields_tags'] ) ) {

						$form             = GFAPI::get_form( $feed['form_id'] );
						$field            = GFFormsModel::get_field( $form, $feed['meta']['fields_tags'] );
						$field_merge_tags = GFCommon::get_field_merge_tags( $field );

						if ( $field->id == $feed['meta']['fields_tags'] ) {

							$merge_tag = $field_merge_tags[0]['tag'];

						} else {

							foreach ( $field_merge_tags as $field_merge_tag ) {

								if ( strpos( $field_merge_tag['tag'], $feed['meta']['fields_tags'] ) !== false ) {

									$merge_tag = $field_merge_tag['tag'];

								}

							}

						}

					} else {

						if ( $feed['meta']['fields_tags'] == 'date_created' ) {

							$merge_tag = '{date_mdy}';

						} else {

							$merge_tag = '{' . $feed['meta']['fields_tags'] . '}';

						}

					}

				}

				$feed['meta']['tags'] = $merge_tag;
				unset( $feed['meta']['fields_tags'] );

				$this->update_feed_meta( $feed['id'], $feed['meta'] );

			}

		}

	}

}