<?php
/**
Plugin Name: Freifunk Metadata Shortcodes
Plugin URI: http://mschuette.name/
Description: Defines shortcodes to display Freifunk metadata
Version: 0.4dev
Author: Martin Schuette
Author URI: http://mschuette.name/
*/

define( 'FF_META_DEFAULT_CACHETIME', 15 );
define( 'FF_META_DEFAULT_DIR', 'https://raw.githubusercontent.com/freifunk/directory.api.freifunk.net/master/directory.json' );
define( 'FF_META_DEFAULT_CITY', 'hamburg' );

/**
 * class to fetch and cache data from external URLs
 */
class FF_Meta_Externaldata
{
	public function get( $url ) {
		/* gets metadata from URL, handles caching */
		$cachekey  = 'ff_metadata_'.hash( 'crc32', $url );
		$cachetime = get_option( 'ff_meta_cachetime', FF_META_DEFAULT_CACHETIME ) * MINUTE_IN_SECONDS;

		// Caching
		if ( false === ( $data = get_transient( $cachekey ) ) ) {
			$http_response = wp_remote_get( $url );
			$json = wp_remote_retrieve_body( $http_response );
			$data = json_decode( $json, $assoc = true );
			set_transient( $cachekey, $data, $cachetime );
		}
		return $data;
	}
}

/**
 * holds the community directory
 */
class FF_Directory
{
	private $directory;

	function __construct() {
		$ed = new FF_Meta_Externaldata();
		$this->directory = $ed->get( FF_META_DEFAULT_DIR );
	}

	function get_url_by_city( $city ) {
		$val = $this->directory[$city];

		if ( empty( $val ) ) {
			return false;
		} else {
			return $val;
		}
	}
}

/**
 * OO interface to handle a single community/city
 */
class FF_Community
{
	public $name;
	public $street;
	public $zip;
	public $city;
	public $lon;
	public $lat;

	/**
	 * Default constructor from metadata
	 */
	function __construct( $metadata ) {
		$loc = $metadata['location'];
		$this->name   = ( isset( $loc['address'] ) && isset( $loc['address']['Name'] ) )    ? $loc['address']['Name']    : '';
		$this->street = ( isset( $loc['address'] ) && isset( $loc['address']['Street'] ) )  ? $loc['address']['Street']  : '';
		$this->zip    = ( isset( $loc['address'] ) && isset( $loc['address']['Zipcode'] ) ) ? $loc['address']['Zipcode'] : '';
		$this->city   = isset( $loc['city'] ) ? $loc['city'] : '';
		$this->lon    = isset( $loc['lon'] )  ? $loc['lon']  : '';
		$this->lat    = isset( $loc['lat'] )  ? $loc['lat']  : '';
	}

	/**
	 * Alternative constructor from city name
	 */
	static function make_from_city( $city ) {
		// TODO: test
		if ( false === ( $url = $this->dir->get_url_by_city( $city ) ) ) {
			return "<!-- FF Meta Error: cannot get directory.json, or no URL for '$city' -->\n";
		}
		if ( false === ( $metadata = FF_Meta_Externaldata::get( $url ) ) ) {
			return "<!-- FF Meta Error: cannot get metadata from $url -->\n";
		}
		return new FF_Community( $metadata );
	}

	function format_address() {
		if ( empty( $this->name ) || empty( $this->street ) || empty( $this->zip ) ) {
			return '';
		}
		// TODO: style address + map as single box
		// TODO: once it is "ready" package openlayers.js into the plugin ( cf. http://docs.openlayers.org/library/deploying.html )
		// TODO: handle missing values ( i.e. only name & city )
		return '<p>' . sprintf( '%s<br/>%s<br/>%s %s', $this->name, $this->street, $this->zip, $this->city ) . '</p>';
	}
}

/**
 * main class for whole plugin
 */
class FF_Meta
{
	private $dir;

	function __construct() {
		$this->dir = new FF_Directory();
	}

	function register_stuff() {
		if ( ! shortcode_exists( 'ff_state' ) ) {
			add_shortcode( 'ff_state',    array( $this, 'shortcode_handler' ) );
		}
		if ( ! shortcode_exists( 'ff_services' ) ) {
			add_shortcode( 'ff_services', array( $this, 'shortcode_handler' ) );
		}
		if ( ! shortcode_exists( 'ff_contact' ) ) {
			add_shortcode( 'ff_contact',  array( $this, 'shortcode_handler' ) );
		}
		if ( ! shortcode_exists( 'ff_location' ) ) {
			add_shortcode( 'ff_location', array( $this, 'shortcode_handler' ) );
		}

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		register_uninstall_hook( __FILE__, array( 'ff_meta', 'uninstall_hook' ) );
	}

	function output_ff_state( $citydata ) {
		$state = $citydata['state'];
		return sprintf( '%s', $state['nodes'] );
	}

	function aux_get_all_locations() {
		// gather all location data
		if ( false === ( $json_locs = get_transient( 'FF_metadata_json_locs' ) ) ) {
			$all_locs   = array();
			$arr_select = array( 'lat' => 1, 'lon' => 1 );
			foreach ( $this->dir as $tmp_city => $url ) {
				try {
					$tmp_meta = FF_Meta_Externaldata::get( $url );
					if ( ! empty( $tmp_meta['location'] ) ) {
						$tmp_loc = array_intersect_key( $tmp_meta['location'], $arr_select );
						$all_locs[$tmp_city] = $tmp_loc;
					}
				} catch ( Exception $e ) {
					// pass
				}
			}
			$json_locs = json_encode( $all_locs );
			$cachetime = get_option( 'FF_meta_cachetime', FF_META_DEFAULT_CACHETIME ) * MINUTE_IN_SECONDS;
			set_transient( 'FF_metadata_json_locs', $json_locs, $cachetime );
		}
		return $json_locs;
	}

	function output_ff_location( $citydata ) {
		// normal per-city code
		$loc       = new FF_Community( $citydata );
		$outstr    = $loc->format_address();
		$json_locs = $this->aux_get_all_locations();

		if ( ! empty( $loc_name ) && ! empty( $loc_name ) ) {
			$icon_url = plugin_dir_url( __FILE__ ) . 'freifunk_marker.png';
			$outstr  .= <<<EOT
  <div id="mapdiv_$loc_city" style="width: 75%; height: 15em;"></div>

  <style type="text/css"> <!--
  /* There seems to be a bug in OpenLayers' style.css ( ? ). Original bottom:4.5em is far too high. */
  #OpenLayers_Control_Attribution_7 { bottom: 3px; }
  --></style>

  <script src="http://www.openlayers.org/api/OpenLayers.js"></script>
  <script>
	map = new OpenLayers.Map( "mapdiv_$loc->city" );
	map.addLayer( new OpenLayers.Layer.OSM( ) );

	var lonLat = new OpenLayers.LonLat(  $loc->lon, $loc->lat  )
		  .transform(
			new OpenLayers.Projection( "EPSG:4326" ), // transform from WGS 1984
			map.getProjectionObject() // to Spherical Mercator Projection
		   );

	var markers = new OpenLayers.Layer.Markers(  "Markers"  );
	map.addLayer( markers );

	markers.addMarker( new OpenLayers.Marker(lonLat ) );

	var size = new OpenLayers.Size( 20,16 );
	var offset = new OpenLayers.Pixel( 0, -(size.h/2 ) );
	var icon = new OpenLayers.Icon( '$icon_url',size,offset );

	var ff_loc = $json_locs;
	delete ff_loc["$city"];
	for ( key in ff_loc ) {
		markers.addMarker( new OpenLayers.Marker(
			new OpenLayers.LonLat(  ff_loc[key]['lon'], ff_loc[key]['lat']  )
			.transform( new OpenLayers.Projection("EPSG:4326" ),map.getProjectionObject() ),
			icon.clone()
		 ) );
	}

	var zoom=12;
	map.setCenter ( lonLat, zoom );
  </script>
EOT;
		}
		return $outstr;
	}

	function output_ff_services( $citydata ) {
		$outstr = '<ul>';
		if ( isset( $citydata['services'] ) ) {
			$services = $citydata['services'];
			foreach ( $services as $service ) {
				$outstr .= sprintf( '<li>%s (%s ): <a href="%s">%s</a></li>', $service['serviceName'], $service['serviceDescription'], $service['internalUri'], $service['internalUri'] );
			}
		}
		$outstr .= '</ul>';
		return $outstr;
	}

	function output_ff_contact( $citydata ) {
		$outstr  = '<p>';
		$contact = $citydata['contact'];
		// Output -- rather ugly but the data is not uniform, some fields are URIs, some are usernames, ...
		if ( ! empty( $contact['email'] ) ) {
			$outstr .= sprintf( "E-Mail: <a href=\"mailto:%s\">%s</a><br />\n", $contact['email'], $contact['email'] );
		}
		if ( ! empty( $contact['ml'] ) ) {
			$outstr .= sprintf( "Mailingliste: <a href=\"mailto:%s\">%s</a><br />\n", $contact['ml'], $contact['ml'] );
		}
		if ( ! empty( $contact['irc'] ) ) {
			$outstr .= sprintf( "IRC: <a href=\"%s\">%s</a><br />\n", $contact['irc'], $contact['irc'] );
		}
		if ( ! empty( $contact['twitter'] ) ) {
			// catch username instead of URI
			if ( $contact['twitter'][0] === '@' ) {
				$twitter_url    = 'http://twitter.com/' . ltrim( $contact['twitter'], '@' );
				$twitter_handle = $contact['twitter'];
			} else {
				$twitter_url    = $contact['twitter'];
				$twitter_handle = '@' . substr( $contact['twitter'], strrpos( $contact['twitter'], '/' ) + 1 );
			}
			$outstr .= sprintf( "Twitter: <a href=\"%s\">%s</a><br />\n", $twitter_url, $twitter_handle );
		}
		if ( ! empty( $contact['facebook'] ) ) {
			$outstr .= sprintf( "Facebook: <a href=\"%s\">%s</a><br />\n", $contact['facebook'], $contact['facebook'] );
		}
		if ( ! empty( $contact['googleplus'] ) ) {
			$outstr .= sprintf( "G+: <a href=\"%s\">%s</a><br />\n", $contact['googleplus'], $contact['googleplus'] );
		}
		if ( ! empty( $contact['jabber'] ) ) {
			$outstr .= sprintf( "XMPP: <a href=\"xmpp:%s\">%s</a><br />\n", $contact['jabber'], $contact['jabber'] );
		}
		$outstr .= '</p>';
		return $outstr;
	}

	function output_ff_list() {
		return 'here be some ff_list';
	}
	
	function shortcode_handler( $atts, $content, $shortcode ) {
		// $atts[0] holds the city name, if given
		if ( empty( $atts[0] ) ) {
			$city = get_option( 'FF_meta_city', FF_META_DEFAULT_CITY );
		} else {
			$city = $atts[0];
		}

		if ( false === ( $cityurl = $this->dir->get_url_by_city( $city ) ) ) {
			return "<!-- FF Meta Error: cannot get directory.json, or no URL for '$city' -->\n";
		}

		if ( false === ( $metadata = FF_Meta_Externaldata::get( $cityurl ) ) ) {
			return "<!-- FF Meta Error: cannot get metadata from $cityurl -->\n";
		}

		$outstr = "<div class=\"ff $shortcode\">";
		switch ( $shortcode ) {
			case 'ff_state':
				$outstr .= $this->output_ff_state( $metadata );
				break;
			case 'ff_location':
				$outstr .= $this->output_ff_location( $metadata );
				break;
			case 'ff_services':
				$outstr .= $this->output_ff_services( $metadata );
				break;
			case 'ff_contact':
				$outstr .= $this->output_ff_contact( $metadata );
				break;
			case 'ff_list':
				$outstr .= $this->output_ff_list();
				break;
			default:
				$outstr .= '';
				break;
		}
		$outstr .= '</div>';
		return $outstr;
	}

	function admin_menu() {
		// Options Page:
		add_options_page(
			'FF Meta Plugin',                   // page title
			'FF Meta',                          // menu title
			'manage_options',                   // req'd capability
			'ff_meta_plugin',                   // menu slug
			array( 'FF_meta', 'options_page' ) // callback function
		);
	}

	function admin_init() {
		register_setting(
			'ff_meta_settings-group', // group name
			'ff_meta_cachetime'       // option name
		);
		register_setting(
			'ff_meta_settings-group', // group name
			'ff_meta_city'            // option name
		);
		add_settings_section(
			'ff_meta_section-one',                       // ID
			'Section One',                               // Title
			array( 'FF_Meta', 'section_one_callback' ), // callback to fill
			'ff_meta_plugin'                             // page to display on
		);
		add_settings_field(
			'ff_meta_city',	                           // ID
			'Default community',                       // Title
			array( 'FF_Meta', 'city_callback' ),      // callback to fill field
			'ff_meta_plugin',                          // menu page=slug to display field on
			'ff_meta_section-one',                     // section to display the field in
			array( 'label_for' => 'ff_meta_city_id' )  // ID of input element
		);
		add_settings_field(
			'ff_meta_cachetime',                            // ID
			'Cache time',                                   // Title
			array( 'FF_Meta', 'cachetime_callback' ),      // callback to fill field
			'ff_meta_plugin',                               // menu page=slug to display field on
			'ff_meta_section-one',                          // section to display the field in
			array( 'label_for' => 'ff_meta_cachetime_id' )  // ID of input element
		);
	}

	function section_one_callback() {
		echo 'This Plugin provides shortcodes to display information from the Freifunk meta.json.';
	}

	function cachetime_callback() {
		$time = get_option( 'ff_meta_cachetime', FF_META_DEFAULT_CACHETIME );
		echo "<input type='number' name='ff_meta_cachetime' id='ff_meta_cachetime_id'"
			." class='small-text code' value='". intval( $time ) . ' /> minutes'.
			"<p class='description'>Data from external URLs is cached for this number of minutes.</p>";
	}

	function city_callback() {
		if ( false === ( $directory = FF_Meta_Externaldata::get( FF_META_DEFAULT_DIR ) ) ) {
			// TODO: error handling
			return;
		}
		$default_city = get_option( 'ff_meta_city', FF_META_DEFAULT_CITY );

		echo "<select name='ff_meta_city' id='ff_meta_city_id' size='1'>";
		foreach ( array_keys( $directory ) as $city ) {
			$prettycity = ucwords( str_replace( array( '_', '-' ), ' ', $city ) );
			$selected   = selected( $default_city, $city );
			echo "<option value='" . esc attr( $city ) . "' $selected>"
				. esc_html( $prettycity ) . '</option>';
		}
		echo '</select>';
		echo "<p class='description'>This is the default city parameter.</p>";
	}

	function options_page() {
		?>
		<div class="wrap">
			<h2>Freifunk Meta Plugin Options</h2>
			<form action="options.php" method="POST">
				<?php settings_fields( 'ff_meta_settings-group' ); ?>
				<?php do_settings_sections( 'ff_meta_plugin' ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	function uninstall_hook() {
		delete_option( 'ff_meta_city' );
		delete_option( 'ff_meta_cachetime' );
	}
}

$ffmeta = new FF_Meta;
$ffmeta->register_stuff();
