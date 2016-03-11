<?php
/**
Plugin Name: Freifunk Metadata Shortcodes
Plugin URI: http://mschuette.name/
Description: Defines shortcodes to display Freifunk metadata
Version: 0.4
Author: Martin Schuette
Author URI: http://mschuette.name/
*/

define( 'FF_META_DEFAULT_CACHETIME', 15 );
define( 'FF_META_DEFAULT_DIR', 'https://raw.githubusercontent.com/freifunk/directory.api.freifunk.net/master/directory.json' );
define( 'FF_META_DEFAULT_CITY', 'hamburg' );

/**
 * class to fetch and cache data from external URLs
 * returns either an array from decoded JSON data, or WP_Error
 */
class FF_Meta_Externaldata
{
	public function get( $url ) {
		//error_log( "FF_Meta_Externaldata::get( $url )" );
		/* gets metadata from URL, handles caching,
		* hashed because cache keys should be <= 40 chars */
		$cachekey  = 'ff_metadata_'.hash( 'crc32', $url );
		$cachetime = get_option(
			'ff_meta_cachetime', FF_META_DEFAULT_CACHETIME
		) * MINUTE_IN_SECONDS;

		// Caching
		if ( WP_DEBUG || ( false === ( $data = get_transient( $cachekey ) ) ) ) {
			$args          = array( 'sslverify' => false );
			$http_response = wp_remote_get( $url, $args );
			if ( is_wp_error( $http_response ) ) {
				$error_msg = sprintf(
						'Unable to retrieve URL %s, error: %s',
						$url, $http_response->get_error_message()
					);
				error_log( $error_msg, 4 );
				return $http_response;
			} else {
				$json = wp_remote_retrieve_body( $http_response );
				$data = json_decode( $json, $assoc = true );
				set_transient( $cachekey, $data, $cachetime );
			}
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
	private $ed;

	function __construct( $ext_data_service = null ) {
		if ( is_null( $ext_data_service ) ) {
			$this->ed = new FF_Meta_Externaldata();
		} else {
			$this->ed = $ext_data_service;
		}
		$data = $this->ed->get( FF_META_DEFAULT_DIR );
		if ( is_wp_error( $data ) ) {
			$this->directory = array();
		} else {
			$this->directory = $data;
		}
	}

	function get_url_by_city( $city ) {
		if ( array_key_exists( $city, $this->directory ) ) {
			return $this->directory[$city];
		} else {
			return false;
		}
	}

	// get one big array of all known community data
	function get_all_data() {
		$all_locs = array();
		foreach ( $this->directory as $tmp_city => $url ) {
			$tmp_meta = $this->ed->get( $url );
			if ( ! is_wp_error( $tmp_meta )	) {
				$all_locs[$tmp_city] = $tmp_meta;
			}
		}
		return $all_locs;
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
		$this->name   = ( isset( $loc['address'] ) && isset( $loc['address']['Name'] ) )
						? $loc['address']['Name']    : '';
		$this->street = ( isset( $loc['address'] ) && isset( $loc['address']['Street'] ) )
						? $loc['address']['Street']  : '';
		$this->zip    = ( isset( $loc['address'] ) && isset( $loc['address']['Zipcode'] ) )
						? $loc['address']['Zipcode'] : '';
		$this->city   = isset( $loc['city'] ) ? $loc['city'] : '';
		$this->lon    = isset( $loc['lon'] )  ? $loc['lon']  : '';
		$this->lat    = isset( $loc['lat'] )  ? $loc['lat']  : '';
	}

	/**
	 * Alternative constructor from city name
	 */
	static function make_from_city( $city, $ext_data_service = null ) {
		if ( is_null( $ext_data_service ) ) {
			$ed = new FF_Meta_Externaldata();
		} else {
			$ed = $ext_data_service;
		}
		$directory = new FF_Directory( $ed );

		if ( false === ( $url = $directory->get_url_by_city( $city ) ) ) {
			return '<!-- FF Meta Error: cannot get directory.json, '.
					" or no URL for '$city' -->\n";
		}
		if ( false === ( $metadata = $ed->get( $url ) ) ) {
			return "<!-- FF Meta Error: cannot get metadata from $url -->\n";
		}
		return new FF_Community( $metadata );
	}

	function format_address() {
		if ( empty( $this->name ) || empty( $this->street ) || empty( $this->zip ) ) {
			return '';
		}
		// TODO: style address + map as single box
		// TODO: once it is "ready" package openlayers.js into the plugin
		//      ( cf. http://docs.openlayers.org/library/deploying.html )
		// TODO: handle missing values ( i.e. only name & city )
		return sprintf(
			'<p>%s<br/>%s<br/>%s %s</p>',
			$this->name, $this->street, $this->zip, $this->city
		);
	}
}

/**
 * main class for whole plugin
 */
class FF_Meta
{
	private $dir;
	private $ed;

	function reinit_external_data_service( $ext_data_service = null ) {
		if ( is_null( $ext_data_service ) ) {
			$this->ed = new FF_Meta_Externaldata();
		} else {
			$this->ed = $ext_data_service;
		}
		$this->dir = new FF_Directory( $this->ed );
	}

	function __construct( $ext_data_service = null ) {
		if ( is_null( $ext_data_service ) ) {
			$this->ed = new FF_Meta_Externaldata();
		} else {
			$this->ed = $ext_data_service;
		}
		$this->dir = new FF_Directory( $this->ed );
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
		if ( ! shortcode_exists( 'ff_list' ) ) {
			add_shortcode( 'ff_list',     array( $this, 'shortcode_handler' ) );
		}

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		register_uninstall_hook( __FILE__, array( 'ff_meta', 'uninstall_hook' ) );
	}

	private function aux_get_all_locations_json() {
		if ( WP_DEBUG || ( false === ( $json_locs = get_transient( 'FF_metadata_json_locs' ) ) ) ) {
			$all_locs  = array();
			$comm_list = $this->dir->get_all_data();
			foreach ( $comm_list as $entry ) {
				if ( isset( $entry['location'] )
					&& isset( $entry['location']['lat'] )
					&& isset( $entry['location']['lon'] )
				) {
					$all_locs[$entry['location']['city']] = array(
						'lat'  => $entry['location']['lat'],
						'lon'  => $entry['location']['lon'],
					);
				}
			}
			$json_locs = json_encode( $all_locs );
			$cachetime = get_option( 'FF_meta_cachetime', FF_META_DEFAULT_CACHETIME ) * MINUTE_IN_SECONDS;
			set_transient( 'FF_metadata_json_locs', $json_locs, $cachetime );
		}
		return $json_locs;
	}

	function output_ff_state( $citydata ) {
		if ( isset( $citydata['state'] ) && isset( $citydata['state']['nodes'] ) ) {
			return sprintf( '%s', $citydata['state']['nodes'] );
		} else {
			return '';
		}
	}

	function output_ff_location( $citydata ) {
		// normal per-city code
		$loc       = new FF_Community( $citydata );
		$outstr    = $loc->format_address();
		$json_locs = $this->aux_get_all_locations_json();

		if ( ! empty( $loc->name ) && ! empty( $loc->name ) ) {
			$icon_url = plugin_dir_url( __FILE__ ) . 'freifunk_marker.png';
			$loccity  = $loc->city;
			$outstr  .= <<<EOT
  <div id="mapdiv_${loccity}" style="width: 75%; height: 15em;"></div>

  <style type="text/css"> <!--
  /* There seems to be a bug in OpenLayers' style.css ( ? ).
   * Original bottom:4.5em is far too high. */
  #OpenLayers_Control_Attribution_7 { bottom: 3px; }
  --></style>

  <script src="http://www.openlayers.org/api/OpenLayers.js"></script>
  <script>
	map = new OpenLayers.Map( "mapdiv_${loccity}" );
	map.addLayer( new OpenLayers.Layer.OSM() );

	var lonLat = new OpenLayers.LonLat( $loc->lon, $loc->lat )
		  .transform(
			new OpenLayers.Projection( "EPSG:4326" ), // transform from WGS 1984
			map.getProjectionObject() // to Spherical Mercator Projection
		  );

	var markers = new OpenLayers.Layer.Markers( "Markers" );
	map.addLayer( markers );

	markers.addMarker( new OpenLayers.Marker( lonLat ) );

	var size = new OpenLayers.Size( 20,16 );
	var offset = new OpenLayers.Pixel( 0, -( size.h/2 ) );
	var icon = new OpenLayers.Icon( '$icon_url',size,offset );

	var ff_loc = $json_locs;
	delete ff_loc["$loccity"];
	for ( key in ff_loc ) {
		markers.addMarker( new OpenLayers.Marker(
			new OpenLayers.LonLat( ff_loc[key]['lon'], ff_loc[key]['lat'] )
			.transform( new OpenLayers.Projection( "EPSG:4326" ),map.getProjectionObject() ),
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
		if ( ! isset( $citydata['services'] ) ) {
			return '';
		}
		$services = $citydata['services'];
		$outstr   = '<table><th>Dienst</th><th>Beschreibung</th><th>Freifunk URI</th><th>Internet URI</th>';
                foreach ( $services as $service ) {
                        $internalUri = isset($service['internalUri']) ? $service['internalUri'] : '';
                        $externalUri = isset($service['externalUri']) ? $service['externalUri'] : '';
                        $outstr .= sprintf(
                                '<tr><td>%s</td><td>%s</td><td><a href="%s">%s</a></td><td><a href="%s">%s</a></td></tr>',
                                $service['serviceName'], $service['serviceDescription'],
                                $internalUri, $internalUri,
                                $externalUri, $externalUri
                        );
                }
                $outstr .= '</table>';
		return $outstr;
	}

	function output_ff_contact( $citydata ) {
		if ( ! isset( $citydata['contact'] ) ) {
			return '';
		}
		$contact = $citydata['contact'];
		$outstr  = '<p>';
		// Output -- rather ugly but the data is not uniform,
		// some fields are URIs, some are usernames, ...
		if ( ! empty( $contact['email'] ) ) {
			$outstr .= sprintf(
				'E-Mail: <a href=\"mailto:%s\">%s</a><br />',
				$contact['email'], $contact['email']
			);
		}
		if ( ! empty( $contact['ml'] ) ) {
			$outstr .= sprintf(
				'Mailingliste: <a href=\"mailto:%s\">%s</a><br />',
				$contact['ml'], $contact['ml']
			);
		}
		if ( ! empty( $contact['irc'] ) ) {
			$outstr .= sprintf(
				'IRC: <a href=\"%s\">%s</a><br />',
				$contact['irc'], $contact['irc']
			);
		}
		if ( ! empty( $contact['twitter'] ) ) {
			// catch username instead of URI
			if ( $contact['twitter'][0] === '@' ) {
				$twitter_url    = 'http://twitter.com/' . ltrim( $contact['twitter'], '@' );
				$twitter_handle = $contact['twitter'];
			} else {
				$twitter_url    = $contact['twitter'];
				$twitter_handle = '@' . substr(
					$contact['twitter'], strrpos( $contact['twitter'], '/' ) + 1
				);
			}
			$outstr .= sprintf(
				'Twitter: <a href=\"%s\">%s</a><br />',
				$twitter_url, $twitter_handle
			);
		}
		if ( ! empty( $contact['facebook'] ) ) {
			$outstr .= sprintf(
				'Facebook: <a href=\"%s\">%s</a><br />',
				$contact['facebook'], $contact['facebook']
			);
		}
		if ( ! empty( $contact['googleplus'] ) ) {
			$outstr .= sprintf(
				'G+: <a href=\"%s\">%s</a><br />',
				$contact['googleplus'], $contact['googleplus']
			);
		}
		if ( ! empty( $contact['jabber'] ) ) {
			$outstr .= sprintf(
				'XMPP: <a href=\"xmpp:%s\">%s</a><br />',
				$contact['jabber'], $contact['jabber']
			);
		}
		$outstr .= '</p>';
		return $outstr;
	}

	function output_ff_list() {
		$comm_list = $this->dir->get_all_data();
		$outstr    = '<table>';
		$outstr   .= '<tr><th>Name</th><th>Stadt</th><th>Knoten</th></tr>';
		foreach ( $comm_list as $handle => $entry ) {
			$outstr .= sprintf(
				'<tr><td><a href="%s">%s</a></td><td>%s</td><td>%s</td></tr>',
				esc_url( $entry['url'] ),
				isset( $entry['name'] )             ? esc_html( $entry['name'] )             : esc_html($handle),
				isset( $entry['location']['city'] ) ? esc_html( $entry['location']['city'] ) : 'n/a',
				isset( $entry['state']['nodes'] )   ? esc_html( $entry['state']['nodes'] )   : 'n/a'
			);
		}
		$outstr .= '</table>';
		return $outstr;
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

		$ed = new FF_Meta_Externaldata();
		if ( false === ( $metadata = $this->ed->get( $cityurl ) ) ) {
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
			'FF Meta Plugin',         // page title
			'FF Meta',                // menu title
			'manage_options',         // req'd capability
			'ff_meta_plugin',         // menu slug
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
			'ff_meta_section-one',    // ID
			'Section One',            // Title
			array( 'FF_Meta', 'section_one_callback' ), // callback to fill
			'ff_meta_plugin'          // page to display on
		);
		add_settings_field(
			'ff_meta_city',                          // ID
			'Default community',                     // Title
			array( 'FF_Meta', 'city_callback' ),     // callback to fill field
			'ff_meta_plugin',                        // menu page=slug to display field on
			'ff_meta_section-one',                   // section to display the field in
			array( 'label_for' => 'ff_meta_city_id' ) // ID of input element
		);
		add_settings_field(
			'ff_meta_cachetime',                      // ID
			'Cache time',                             // Title
			array( 'FF_Meta', 'cachetime_callback' ), // callback to fill field
			'ff_meta_plugin',                         // menu page=slug to display field on
			'ff_meta_section-one',                    // section to display the field in
			array( 'label_for' => 'ff_meta_cachetime_id' )  // ID of input element
		);
	}

	function section_one_callback() {
		echo 'This Plugin provides shortcodes to display information'
			.' from the Freifunk meta.json.';
	}

	function cachetime_callback() {
		$time = get_option( 'ff_meta_cachetime', FF_META_DEFAULT_CACHETIME );
		echo '<input type="number" name="ff_meta_cachetime" '
			.'id="ff_meta_cachetime_id" class="small-text code" value="'
			. esc_attr( $time ) . ' /> minutes'
			.'<p class="description">Data from external URLs is cached'
			.' for this number of minutes.</p>';
	}

	function city_callback() {
		$ed = new FF_Meta_Externaldata();
		if ( false === ( $directory = $this->ed->get( FF_META_DEFAULT_DIR ) ) ) {
			// TODO: error handling
			return;
		}
		$default_city = get_option( 'ff_meta_city', FF_META_DEFAULT_CITY );

		echo "<select name='ff_meta_city' id='ff_meta_city_id' size='1'>";
		foreach ( array_keys( $directory ) as $city ) {
			$prettycity = ucwords( str_replace( array( '_', '-' ), ' ', $city ) );
			$selected   = selected( $default_city, $city );
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $city ), $selected, esc_str( $prettycity )
			);
		}
		echo '</select>';
		echo '<p class="description">This is the default city parameter.</p>';
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

$ffmeta = new FF_Meta();
$ffmeta->register_stuff();
$GLOBALS['wp-plugin-ffmeta'] = $ffmeta;
