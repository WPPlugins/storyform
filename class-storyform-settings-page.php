<?php
/**
 * Handles setting up the Admin Settings > Storyform Settings page, which takes an application key.
 *
 */
class Storyform_Settings_Page
{

	/**
	 * Start up
	 */
	public function __construct()
	{
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
		if( ! function_exists( 'wpcom_vip_get_resized_attachment_url' ) ){
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		}
	}

	/**
	 * Add options page
	 */
	public function add_plugin_page()
	{
		// This page will be under "Settings"
		add_options_page(
			'Settings Admin',
			'Storyform',
			'manage_options',
			'storyform-setting-admin',
			array( $this, 'create_admin_page' )
		);
	}

	/**
	 * Options page callback
	 */
	public function create_admin_page()
	{
		?>
	<div class="wrap">
		<?php screen_icon(); ?>
		<h2>Storyform Settings</h2>

		<form method="post" action="options.php">
			<?php
			// This prints out all hidden setting fields
			settings_fields( 'storyform_option_group' );
			do_settings_sections( 'storyform-setting-admin' );
			submit_button();
			?>
		</form>
	</div>
	<?php
	}

	public function admin_enqueue_scripts() {
	    if ( isset( $_GET['page'] ) && $_GET['page'] == 'storyform-setting-admin' ) {
	        wp_enqueue_media();

	        wp_enqueue_style( 'wp-color-picker' );
	        wp_enqueue_script( 'wp-color-picker' );

	        wp_register_script( 'storyform_navbar', Storyform_Api::get_instance()->get_static_version_directory() . '/js/navbar.js' );
	        wp_enqueue_script( 'storyform_navbar' );

	        wp_register_script( 'storyform_settings_page', plugins_url( 'settings-page/settings-page.js', __FILE__ ) );
	        wp_enqueue_script( 'storyform_settings_page' );

	        wp_register_style( 'storyform_navbar', Storyform_Api::get_instance()->get_static_version_directory() . '/css/navbar.css' );
	        wp_enqueue_style( 'storyform_navbar' );

	    	wp_register_style( 'storyform_settings_page', plugins_url( 'settings-page/settings-page.css', __FILE__ ) );
	        wp_enqueue_style( 'storyform_settings_page' );

	    }
	}

	/**
	 * Register and add settings
	 */
	public function page_init()
	{
		register_setting(
			'storyform_option_group', // Option group
			'storyform_settings', // Option name
			array( $this, 'sanitize' ) // Sanitize
		);

		add_settings_section(
			'storyform_section_id', // ID
			'Application Settings', // Title
			array( $this, 'print_section_info' ), // Callback
			'storyform-setting-admin' // Page
		);

		add_settings_field(
			'storyform_application_key', // ID
			'Storyform application key', // Title
			array( $this, 'storyform_application_key_callback' ), // Callback
			'storyform-setting-admin', // Page
			'storyform_section_id' // Section
		);

		add_settings_section(
			'storyform_section_navigation', // ID
			'Navigation', // Title
			array( $this, 'print_section_navigation' ), // Callback
			'storyform-setting-admin' // Page
		);

		add_settings_section(
			'storyform_advanced_section_id', // ID
			'Advanced Settings', // Title
			array( $this, 'print_advanced_section_info' ), // Callback
			'storyform-setting-admin' // Page
		);

		// In VIP you don't actually generate multiple images, its done on the fly
		if( ! function_exists( 'wpcom_vip_get_resized_attachment_url' ) ){
			add_settings_field(
				'storyform_add_image_sizes', // ID
				'Generate additional image sizes', // Title
				array( $this, 'storyform_add_image_sizes_callback' ), // Callback
				'storyform-setting-admin', // Page
				'storyform_advanced_section_id' // Section
			);
		}


		add_settings_field(
			'storyform_selected_scripts', // ID
			'Include these scripts on Storyform posts (removes all others)', // Title
			array( $this, 'storyform_selected_scripts_callback' ), // Callback
			'storyform-setting-admin', // Page
			'storyform_advanced_section_id' // Section
		);

		add_settings_field(
			'storyform_selected_functions', // ID
			'Allow these plugin/theme functions to run on Storyform posts (prevents all others)', // Title
			array( $this, 'storyform_selected_functions_callback' ), // Callback
			'storyform-setting-admin', // Page
			'storyform_advanced_section_id' // Section
		);

	}

	/**
	 * Sanitize each setting field as needed
	 *
	 * @param array $input Contains all settings fields as array keys
	 */
	public function sanitize( $input )
	{
		$new_input = array();
		if( isset( $input['storyform_application_key'] ) ) {
			$new_input['storyform_application_key'] =  sanitize_text_field( $input['storyform_application_key'] );
		}

		if( isset( $input['storyform_navigation_width'] ) ) {
			$new_input['storyform_navigation_width'] = sanitize_text_field( $input['storyform_navigation_width'] );
		}

		if( isset( $input['storyform_navigation_logo'] ) ) {
			$new_input['storyform_navigation_logo'] = sanitize_text_field( $input['storyform_navigation_logo'] );
		}

		if( isset( $input['storyform_navigation_links'] ) ) {
			$new_input['storyform_navigation_links'] = sanitize_text_field( $input['storyform_navigation_links'] );
		}

		if( isset( $input['storyform_navigation_side'] ) ) {
			$new_input['storyform_navigation_side'] = sanitize_text_field( $input['storyform_navigation_side'] );
		}

		if( isset( $input['storyform_navigation_title'] ) ) {
			$new_input['storyform_navigation_title'] = TRUE;
		} else {
			$new_input['storyform_navigation_title'] = FALSE;
		}

		if( isset( $input['storyform_navigation_controls'] ) ) {
			$new_input['storyform_navigation_controls'] = array();
			foreach( $input['storyform_navigation_controls'] as $key => $value ){
				array_push( $new_input['storyform_navigation_controls'], sanitize_text_field( $key ) );
			}
		} else {
			$new_input['storyform_navigation_controls'] = array();
		}

		if( isset( $input['storyform_navigation_bg_color'] ) ) {
			$new_input['storyform_navigation_bg_color'] = sanitize_text_field( $input['storyform_navigation_bg_color'] );
		}

		if( isset( $input['storyform_navigation_fg_color'] ) ) {
			$new_input['storyform_navigation_fg_color'] = sanitize_text_field( $input['storyform_navigation_fg_color'] );
		}

		if( isset( $input['storyform_navigation_border_bottom_width'] ) ) {
			$new_input['storyform_navigation_border_bottom_width'] = intval( $input['storyform_navigation_border_bottom_width'] );
		}

		// If its not set we know the user unchecked it
		if( isset( $input['storyform_add_image_sizes'] ) ) {
			$new_input['storyform_add_image_sizes'] = TRUE;
		} else {
			$new_input['storyform_add_image_sizes'] = FALSE;
		}

		// Sanitize all script names
		if( isset( $input['storyform_selected_scripts'] ) && gettype( $input['storyform_selected_scripts'] ) == 'array' ) {
			$new_input['storyform_selected_scripts'] = array();
			foreach( $input['storyform_selected_scripts'] as $script ){
				array_push( $new_input['storyform_selected_scripts'], sanitize_text_field( $script ) );
			}
		} else {
			$new_input['storyform_selected_scripts'] = array();
		}

		// Sanitize all function names
		if( isset( $input['storyform_selected_functions'] ) && gettype( $input['storyform_selected_functions'] ) == 'array' ) {
			$new_input['storyform_selected_functions'] = array();
			foreach( $input['storyform_selected_functions'] as $function ){
				array_push( $new_input['storyform_selected_functions'], sanitize_text_field( $function ) );
			}
		} else {
			$new_input['storyform_selected_functions'] = array();
		}

		return $new_input;
	}

	/**
	 * Print the Section text
	 */
	public function print_section_info()
	{
	}

	/**
	 * Print the Section text
	 */
	public function print_section_navigation()
	{
		
		$options = Storyform_Options::get_instance();
		$width = $options->get_navigation_width();
		$width_min = $width === 'minimized' ? 'checked' : '';
		$width_full = $width === 'full' ? 'checked' : '';

		$logo = $options->get_navigation_logo();
		$title = $options->get_navigation_title() ? 'checked' : '';
		$links = $options->get_navigation_links();
		$links_horiz = $links === 'horizontal' ? 'checked' : '';
		$links_vert = $links === 'vertical' ? 'checked' : '';

		$side = $options->get_navigation_side();
		$side_left = $side === 'left' ? 'checked' : '';
		$side_right = $side === 'right' ? 'checked' : '';

		$controls = $options->get_navigation_controls();
		$bg_color = $options->get_navigation_bg_color();
		$fg_color = $options->get_navigation_fg_color();

		$bb_width = $options->get_navigation_border_bottom_width();

	?>
	<div class="storyform-navigation clearfix">
		<div class="storyform-navigation-settings clearfix">
			<div class="storyform-navigation-group storyform-logo-color">	
				<div class="storyform-input-label">Logo</div>
				<div class="storyform-input-group">
					<input type="text" id="storyform-navigation-logo" name="storyform_settings[storyform_navigation_logo]" value="<?php echo $logo ?>" />
					<a href="#" class="button storyform-select-logo" data-uploader-title="Select logo" data-uploader-button-text="Select" title="Add Media">Select logo</a>
				</div>
				
				<div class="storyform-input-label">Colors</div>
				<div class="storyform-input-group storyform-navigation-color">
					<label><span>Background</span> <input type="text" class="storyform-color-picker" name="storyform_settings[storyform_navigation_bg_color]" value="<?php echo $bg_color ?>" /></label>
					<br />
					<label><span>Foreground</span> <input type="text" class="storyform-color-picker" name="storyform_settings[storyform_navigation_fg_color]" value="<?php echo $fg_color ?>" /></label>
				</div>
			</div>
			
			<div class="storyform-navigation-group">	
				<div class="storyform-input-label">Width</div>
				<div class="storyform-input-group">
					<label><input type="radio" id="storyform-navigation-width-minimized" class="storyform-navigation-width" name="storyform_settings[storyform_navigation_width]" value="minimized" <?php echo $width_min ?> />Minimized</label>
					<label><input type="radio" id="storyform-navigation-width-full" class="storyform-navigation-width" name="storyform_settings[storyform_navigation_width]" value="full" <?php echo $width_full ?> />Full width</label>
				</div>
	
				<div class="storyform-input-label">Menu</div>
				<div class="storyform-input-group">
					<div class="storyform-input-group-menu">
						<a href="<?php echo admin_url( 'nav-menus.php?action=locations' ) ?>">Choose or add custom menu</a>
					</div>
					<div class="storyform-input-group-menu">
						<label><input type="radio" name="storyform_settings[storyform_navigation_links]" value="horizontal" <?php echo $links_horiz ?> />Horizontal</label>
						<label><input type="radio" name="storyform_settings[storyform_navigation_links]" value="vertical" <?php echo $links_vert ?> />Vertical</label>
					</div>
					<div class="storyform-input-group-menu">
						<input type="radio" id="storyform-navigation-side-left" class="storyform-navigation-side" name="storyform_settings[storyform_navigation_side]" value="left" <?php echo $side_left ?> /><label for="storyform-navigation-side-left">Left</label>
						<input type="radio" id="storyform-navigation-side-left" class="storyform-navigation-side" name="storyform_settings[storyform_navigation_side]" value="right" <?php echo $side_right ?> /><label for="storyform-navigation-side-right">Right</label>
					</div>
				</div>
			</div>
				
			<div class="storyform-navigation-group">	
				<div class="storyform-input-label">Border bottom</div>
				<div class="storyform-input-group">
					<select class="storyform-navigation-border" name="storyform_settings[storyform_navigation_border_bottom_width]">
						<option value="0" <?php if( $bb_width == 0 ) { echo 'selected'; } ?>>None</option>
						<option value="1" <?php if( $bb_width == 1 ) { echo 'selected'; } ?>>1px</option>
						<option value="2" <?php if( $bb_width == 2 ) { echo 'selected'; } ?>>2px</option>
						<option value="3" <?php if( $bb_width == 3 ) { echo 'selected'; } ?>>3px</option>
						<option value="4" <?php if( $bb_width == 4 ) { echo 'selected'; } ?>>4px</option>
						<option value="5" <?php if( $bb_width == 5 ) { echo 'selected'; } ?>>5px</option>
					</select>
				</div>

				<div class="storyform-input-label">Post title</div>
				<div class="storyform-input-group">
					<input type="checkbox" id="storyform-navigation-title" class="storyform-navigation-title" name="storyform_settings[storyform_navigation_title]" <?php echo $title ?> /><label for="storyform-navigation-title">Display post title</label>
				</div>
				
				<div class="storyform-input-label">Controls</div>
				<div class="storyform-input-group">
					<label><input type="checkbox" name="storyform_settings[storyform_navigation_controls][facebook]" <?php echo in_array( 'facebook', $controls ) ? 'checked' : '' ?> />Facebook</label>
					<label><input type="checkbox" name="storyform_settings[storyform_navigation_controls][twitter]" <?php echo in_array( 'twitter', $controls ) ? 'checked' : '' ?> />Twitter</label>
					<label><input type="checkbox" name="storyform_settings[storyform_navigation_controls][gplus]" <?php echo in_array( 'gplus', $controls ) ? 'checked' : '' ?> />Google Plus</label>
					<label><input type="checkbox" name="storyform_settings[storyform_navigation_controls][fullscreen]" <?php echo in_array( 'fullscreen', $controls ) ? 'checked' : '' ?> />Fullscreen</label>
				</div>
			</div>
			<div class="storyform-navigation-submit">
				<input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes and Preview">
			</div>
		</div>
		
		<div class="storyform-navigation-preview">
			<div class="storyform-browser">
				<div class="storyform-browser-head">
					<div class="storyform-b-btns">
						<div class="storyform-red"></div>
						<div class="storyform-yellow"></div>
						<div class="storyform-green"></div>
					</div>
					<div class="storyform-b-tabs">
						<div class="storyform-b-tab">
						<svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="132.247px" height="20px" viewBox="0 0 132.247 20" enable-background="new 0 0 132.247 20" xml:space="preserve"><path fill="#E6E6E5" d="M124.968,2.534C124.487,1.09,123.337,0,121.328,0H10.919C8.91,0,7.76,1.142,7.279,2.534L2.426,17.878C2.009,19.089,0,20,0,20h132.247c0,0-2.01-0.797-2.427-2.122L124.968,2.534z"/></svg>
						</div>
					</div>
				</div>
				<div class="storyform-browser-body nav-left">
					<?php require( dirname( __FILE__ ) . '/theme/navbar.php' ); ?>
					<div class="primary-content"></div>
				</div>
			</div>
		</div>
	</div>
	<?php
	}

	/**
	 * Print the Section text
	 */
	public function print_advanced_section_info()
	{
		print '<a href="#" class="storyform_toggle_advanced" data-hide-text="Hide advanced settings" data-show-text="Show advanced settings"></a>';
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function storyform_application_key_callback()
	{
		$key = Storyform_Options::get_instance()->get_application_key();
		printf(
			'<input type="text" id="storyform_application_key" name="storyform_settings[storyform_application_key]" value="%s" /><br />
			<label><small>Sign up at <a target="_blank	" href="http://storyform.co/#signup">Storyform</a></small></label>',
			$key ? esc_attr( $key ) : ''
		);
	}

	/**
	 *  Display whether to add additional image sizes (for different screen sizes). Default to checked.
	 */
	public function storyform_add_image_sizes_callback()
	{
		$add_image_sizes = Storyform_Options::get_instance()->get_add_image_sizes();
		printf(
			'<input type="checkbox" id="storyform_add_image_sizes" name="storyform_settings[storyform_add_image_sizes]" %s />
			<label><small>Adds a 1366x768 and 1920x1080 image candidate for newly uploaded media.</small></label>',
			$add_image_sizes ? 'checked' : ''
		);
	}

	/**
	 *  Display whether to allow certain scripts to be inserted in Storyform posts.
	 */
	public function storyform_selected_scripts_callback()
	{
		global $wp_scripts;

		$instance = Storyform_Options::get_instance();
		$all_scripts = $instance->get_all_scripts();
		$selected_scripts = $instance->get_selected_scripts();

		if( !count( $all_scripts ) ){
			print( "<small>There are no scripts to allow. Be sure to view a Storyform post to populate new scripts.</small>" );
			return;
		}

		foreach( $all_scripts as $handle ){
			$selected = in_array( $handle, $selected_scripts );
			$script = $wp_scripts->query( $handle );
			$description = $this->get_script_description( $handle, $script );
			printf( '
				<div>
				<label>
					<input type="checkbox" id="storyform_selected_scripts_%s" name="storyform_settings[storyform_selected_scripts][]" value="%s" %s/>
					%s
				</label>
				</div>', 
				esc_attr( $handle ),
				esc_attr( $handle ),
				$selected ? 'checked' : '',
				$description 
			);
		}
	}

	/**
	 *  Returns HTML for a single script definition
	 */
	protected function get_script_description( $handle, $script ){
		$description = "<strong>" . esc_attr( $handle );
		$description .= $script ? " " . esc_attr( $script->ver ) : '';
		$description .= ($script && $script->src ? " (" . esc_attr( $script->src ) . ") " : '') . "</strong>";
		$description .= $script && count( $script->deps) ? " Dependencies: " . esc_attr( join( ", ", $script->deps ) ) : "" ;
		return $description;
	}

	/**
	 *  Display whether to allow certain plugin or theme functions to be called for Storyform posts.
	 */
	public function storyform_selected_functions_callback()
	{
		$instance = Storyform_Options::get_instance();
		$all_functions = $instance->get_all_functions();
		$selected_functions = $instance->get_selected_functions();

		$functions = array();
		foreach( $all_functions as $name ){
			if( in_array( $name, array_keys( Storyform::get_default_functions() ) ) ) {
				continue;
			}
			$selected = in_array( $name, $selected_functions );
			$dir = $this->get_plugin_dir_for_function_name( $name );
			array_push( $functions, array( 'name' => $name, 'dir' => $dir, 'selected' => $selected ) );
		}

		usort( $functions, function($a, $b){
			if ($a['dir'] === $b['dir']) {
		        return 0;
		    }
		    return ($a['dir'] < $b['dir']) ? -1 : 1;
		});

		if( !count( $functions ) ){
			print( "<small>There are no functions to allow. Be sure to view a Storyform post to populate new functions.</small>" );
			return;
		}

		foreach ($functions as $function) {
			$description = $function['dir'] ? "<strong>" . $function['dir'] . ":</strong> " . $function['name'] : $function['name'];

			printf( '
				<div>
				<label>
					<input type="checkbox" id="storyform_selected_functions_%s" name="storyform_settings[storyform_selected_functions][]" value="%s" %s/>
					%s
				</label>
				</div>', 
				esc_attr( $function['name'] ),
				esc_attr( $function['name'] ),
				$function['selected'] ? 'checked' : '',
				$description 
			);
		}
	}

	protected function get_plugin_dir_for_function_name( $id ){
		global $wp_filter;

		if( !class_exists( 'ReflectionFunction' ) ||  !class_exists( 'ReflectionObject' )) {
			return FALSE;
		}

		$object_parts = explode( ":", $id );
		try {
			if( count( $object_parts ) > 1 ) {
				$refFunc = new ReflectionClass( $object_parts[0] );
			} else {
				$refFunc = new ReflectionFunction( $id );	
			}
		} catch (Exception $e){
			return FALSE;
		}

		$filename = $refFunc->getFileName();
		$dir = dirname( $filename );
		$plugin_dir = dirname( plugin_basename( $filename ) );

		// If the directory wasn't shortened then its a WordPress file
		if( $dir === "/" . $plugin_dir ){
			return 'WordPress';
		}
		
		return $plugin_dir;
	}

}