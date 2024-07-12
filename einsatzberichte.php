<?php
/*
* Plugin Name: Einsatzberichte OÖ
* Description: Einsatzberichte wurde für eine einfache Verknüpfung und Datenabfrage vom LFK-Intranet erstellt. Verknüpfe deine Beiträge mit einer LFK Einsatz-ID und lasse die aktuellsten Einsätze im Widget anzeigen. Oder verwende den Shortcode [eib_jahresuebersicht] in einem Beitrag oder auf einer Seite um eine Jahresübersicht anzuzeigen.
* Version: 0.2.0
* Author: Matthias Schaffer
* Author URI: https://matthiasschaffer.com/
* License: GPL2
* License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

/*
* Namespace: eib_
* Slug: einsatzberichte-oo
*/
if ( ! defined( 'WPINC' ) ) {
	exit( 'Das Plugin Einsatzberichte kann nicht direkt aufgerufen werden.' );
}

date_default_timezone_set( get_option( 'timezone_string' ) );

register_activation_hook( __FILE__, 'eib_activation' );
register_uninstall_hook( __FILE__, 'eib_uninstall' );

if (!defined("EIB_API_HOSTNAME")) {
	define("EIB_API_HOSTNAME", "https://einsatzinfo.matthiasschaffer.com/");
}

if (!defined("EIB_PLUGIN_VERSION")) {
	$plugin_data = get_file_data(__FILE__, array('Version' => 'Version'), false);
	$plugin_version = $plugin_data['Version'];
	define("EIB_PLUGIN_VERSION", $plugin_version);
}


function eib_activation() {
}//end eib_activation()


function eib_uninstall() {
	delete_option( 'eib_feuerwehr' );

}//end eib_uninstall()


function placeAdj( $place ) {
	$array    = explode( ' ', mb_strtolower( $place ) );
	$between  = [
		'am',
		'im',
		'bei',
		'an',
		'der',
		'ob',
	];
	$newArray = [];
	foreach ( $array as &$slice ) {
		$newArray[] = ( in_array( $slice, $between ) ) ? $slice : ucfirst( $slice );
	}

	return implode( ' ', $newArray );

}//end placeAdj()

function eib_api_call ($action, $parameters=[]) {
	$cache_name = (!empty($parameters["cache_name"]) ? $parameters["cache_name"] : $action);
	$cached = get_transient($cache_name);
	if (!empty($cached)) {
		return (is_serialized($cached) ? unserialize($cached) : $cached);
	}

	$expiration = (!empty($parameters["expiration"]) ? $parameters["expiration"] : 180);
	$args = ["user-agent" => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ).'; eib_v'.EIB_PLUGIN_VERSION];
	switch (strtolower($action)) {
		case "get_lfd":
			$url = EIB_API_HOSTNAME.'api.php?action=lfd';
			$response = wp_remote_retrieve_body( wp_remote_get($url, $args) );
			$return = json_decode($response, true);
			break;
		case "get_map":
			$map = (!empty($parameters["map"]) ? $parameters["map"] : "");
			$url = EIB_API_HOSTNAME.'showmap.php?show=lfk&map=' . urlencode( esc_attr($map) );
			$response = wp_remote_retrieve_body( wp_remote_get($url, $args) );
			$return = base64_encode($response);
			break;
		case "get_ff_lfd":
			$ffname = (!empty($parameters["ffname"]) ? $parameters["ffname"] : get_option( 'eib_feuerwehr', '' ));
			if (empty($ffname)) {
				return [];
			}
			$url = EIB_API_HOSTNAME.'api.php?action=lfd&ffname=' . urlencode( esc_attr($ffname) );
			$response = wp_remote_retrieve_body( wp_remote_get($url, $args) );
			$return = json_decode($response, true);
			break;
		case "get_ff_einsaetze":
			$ffname = (!empty($parameters["ffname"]) ? $parameters["ffname"] : get_option( 'eib_feuerwehr', '' ));
			if (empty($ffname)) {
				return [];
			}
			$url = EIB_API_HOSTNAME.'search.php?by=ffname&query=' . urlencode( esc_attr($ffname) ) . '&json';
			$response = wp_remote_retrieve_body( wp_remote_get($url, $args) );
			$return = json_decode($response, true);
			break;
		case "get_ffs":
			$url = EIB_API_HOSTNAME.'ffs.json';
			$response = wp_remote_retrieve_body( wp_remote_get($url, $args) );
			$return = json_decode($response, true);
			break;
		default:
			return false;
	}

	set_transient($cache_name, serialize($return), $expiration);

	return $return;
}//end eib_api_call()

function eib_jahresuebersicht_ressources( $hook ) {
	wp_enqueue_style( 'eib-app-css', plugins_url( '/css/app.css', __FILE__ ) );

}//end eib_jahresuebersicht_ressources()
add_action( 'wp_enqueue_scripts', 'eib_jahresuebersicht_ressources' );


/*
	Jahresübersicht Endpoint
-------------------------- */
	add_filter(
		'init',
		function ( $template ) {
			if ( isset( $_GET['eib_year'] ) ) {
				$hyear = intval( $_GET['eib_year'] );
				if ( esc_attr( get_option( 'eib_feuerwehr', '' ) ) == '' ) {
					exit( '<p>Kein Feuerwehrname gesetzt! Navigiere zu "EIB-Konfig" um den Namen zu hinterlegen.</p>' );
				}

				global $wpdb;
				$result = $wpdb->get_results( 'SELECT `post_id`,`meta_value` FROM `' . $wpdb->prefix . "postmeta` WHERE `meta_key`='eib_enum'" );
				$enums  = [];
				foreach ( $result as &$item ) {
					$enums[ $item->meta_value ] = $item->post_id;
				}

				$arttext  = [
					'brand'    => 'Brandeinsatz',
					'tee'      => 'Technischer Einsatz',
					'unwetter' => 'Unwettereinsatz',
					'person'   => 'Personenrettung',
					'sonstige' => 'Sonstiger Einsatz',
				];
				$json     = eib_api_call("get_ff_einsaetze");

				$num_einsaetze = 0;
				$array         = [
					'art' => [
						'BRAND'    => 0,
						'TEE'      => 0,
						'PERSON'   => 0,
						'UNWETTER' => 0,
						'SONSTIGE' => 0,
					],
				];
				if ( get_option( 'eib_jahres_stats', 0 ) == 1 ) {
					echo '<div style="text-align: center; position: relative;"><canvas id="einsatzart"></canvas>' . '<div id="count_einsaetze" style="position: absolute; top: 50%; margin-top: -15px; left: 50%; font-size: 30px; line-height: 30px; margin-left: -50px; width: 100px; text-align: center;">00</div>' . '<div style="position: absolute; top: 50%; margin-top: 15px; left: 50%; font-size: 15px; line-height: 15px; margin-left: -50px; width: 100px; text-align: center;">Einsätze<br>gesamt</div></div>';
				}

				echo "<table class='responsive'>";
				echo '<tr><th></th><th>Datum</th><th>Einsatzmeldung</th><th>Ort</th><th></th></tr>';
				foreach ( $json as $enum => $item ) {
					if ( date( 'Y', strtotime( $item['zeit'] ) ) != $hyear ) {
						continue;
					}

					$num_einsaetze++;
					$array['art'][ $item['einsatzart'] ]++;
					echo '<tr>';
					$alarmstufe = ( array_key_exists( 'alarmstufe', $item ) ) ? $item['alarmstufe'] : '';
					echo '<td><span class="fa-stack" title="Alarmstufe ' . $alarmstufe . ' / ' . $arttext[ strtolower( $item['einsatzart'] ) ] . '">' . '<i class="fa fa-circle fa-stack-2x ' . strtoupper( $item['einsatzart'] ) . '"></i><strong class="fa-stack-1x ' . strtoupper( $item['einsatzart'] ) . '-TEXT">' . $item['alarmstufe'] . '</strong></span></td>';
					echo '<td>' . date( 'd.m.Y H:i:s', strtotime( $item['zeit'] ) ) . '</td>';
					echo '<td>' . $item['subtyp'] . '</td>';
					$earea = ( empty( $item['adresse']['earea'] ) ) ? $item['adresse']['default'] : $item['adresse']['earea'];
					echo '<td>' . placeAdj( $earea ) . '</td>';
					if ( array_key_exists( $enum, $enums ) ) {
						echo '<td><a href="' . get_permalink( $enums[ $enum ] ) . '" title="' . get_the_title( $enums[ $enum ] ) . '">Bericht lesen</a>';
						/*
							if(get_children(array('post_parent' => $enums[$enum], 'post_type' => 'attachment', 'post_mime_type' => 'image'))) // Show picture icon if post has media
						echo ' <i class="fa fa-picture-o" aria-hidden="true"></i>';*/
						echo '</td>';
					} else {
						echo '<td></td>';
					}

					echo '</tr>';
				}//end foreach

				if ( $num_einsaetze == 0 ) {
					echo '<tr><td colspan=5>Keine Einsätze gefunden.</td></tr>';
				}

				echo '</table>';
				if ( get_option( 'eib_jahres_stats', 0 ) == 1 ) {
					?>
			<script>
				var eart = document.getElementById('einsatzart').getContext('2d');
				var myDoughnutChart = new Chart(eart, {
					type: 'doughnut',
					data: {
						labels: ["BRAND", "TEE", "PERSON", "UNWETTER", "SONSTIGE"],
						datasets: [{
							label: 'Anzahl von Einsätzen',
							data: <?php echo json_encode( array_values( $array['art'] ) ); ?>,
							backgroundColor: [
								'rgba(255, 0, 0, 1)',
								'rgba(0, 0, 255, 1)',
								'rgba(255, 255, 0, 1)',
								'rgba(0, 255, 0, 1)',
								'rgba(0, 0, 0, 1)'
							]
						}]
					},
					options: {
						plugins: {
							labels: {
									render: 'value',
								fontSize: 14,
								fontStyle: 'bold',
								fontColor: '#999'
							  }
						},
						responsive: true
					}
				});
				document.getElementById("count_einsaetze").innerHTML = "<?php echo $num_einsaetze; ?>";
			</script>
					<?php
				}//end if

				die;
			}//end if
		}
	);

	/*
		METABOX erstellen
	-------------------------- */
	function eib_create_metabox() {
		add_meta_box(
			'eib_enum_div',
			// Metabox ID
			'Einsatzberichte',
			// Title to display
			'eib_render_enum',
			// Function to call that contains the metabox content
			'post',
			// Post type to display metabox on
			'normal',
			// Where to put it (normal = main colum, side = sidebar, etc.)
			'default'
			// Priority relative to other metaboxes
		);

	}//end eib_create_metabox()


	add_action( 'add_meta_boxes', 'eib_create_metabox' );


	function eib_admin_scripts( $hook ) {
		wp_enqueue_style( 'select2_css', plugins_url( 'css/select2.min.css', __FILE__ ) );
		wp_enqueue_style( 'fa-4-7-0_css', plugins_url( 'font-awesome/css/font-awesome.min.css', __FILE__ ) );
		wp_enqueue_script( 'select2_js', plugins_url( 'js/select2.min.js', __FILE__ ), [ 'jquery' ] );

	}//end eib_admin_scripts()


	add_action( 'admin_enqueue_scripts', 'eib_admin_scripts' );


	function eib_render_enum() {
		// Variables
		global $post;
		// Get the current post data
		$saved_enum = get_post_meta( $post->ID, 'eib_enum', true );
		// Get the saved values
		?>
	
			<fieldset>
				<div>
					<label for="eib_enum">
						<?php
						// This runs the text through a translation and echoes it (for internationalization)
						_e( 'Einsatz vom LFK verbinden', 'eib' );
						?>
					</label>
					<br><br>
					<?php
					if ( esc_attr( get_option( 'eib_feuerwehr', '' ) ) == '' ) {
						echo '<p>Kein Feuerwehrname gesetzt! Navigiere zu "EIB-Konfig" um den Namen zu hinterlegen.</p>';
					} else {
						global $wpdb;
						$result = $wpdb->get_results( 'SELECT `meta_value` FROM `' . $wpdb->prefix . "postmeta` WHERE `meta_key`='eib_enum'" );
						$enums  = [];
						foreach ( $result as &$item ) {
							$enums[] = $item->meta_value;
						}
						?>
							<select id="eib_enum" name="eib_enum" style="width: 100%;">
								<option></option>
						<?php
						$artcolor = [
							'brand'    => 'red',
							'tee'      => 'blue',
							'unwetter' => 'green',
							'person'   => 'yellow',
							'sonstige' => 'black',
						];
						$json     = eib_api_call("get_ff_einsaetze");
						foreach ( $json as $enum => $item ) {
							if ( $saved_enum != '' && $saved_enum == $enum ) {
								echo "<option data-color='" . $artcolor[ strtolower( $item['einsatzart'] ) ] . "' data-datetime='" . date( 'd.m.Y H:i', strtotime( $item['zeit'] ) ) . "' value='$enum' selected>" . $item['subtyp'] . '</option>';
							} else {
								if ( in_array( $enum, $enums ) ) {
									continue;
								}

								echo "<option data-color='" . $artcolor[ strtolower( $item['einsatzart'] ) ] . "' data-datetime='" . date( 'd.m.Y H:i', strtotime( $item['zeit'] ) ) . "' value='$enum'>" . $item['subtyp'] . '</option>';
							}
						}
						?>
							</select>
							<p><small>Einsätze für <strong><?php echo esc_attr( get_option( 'eib_feuerwehr', '' ) ); ?></strong> geladen. Änderungen unter "EIB-Konfig".</small></p>
						<?php
						if ( esc_attr( get_option( 'eib_ekm_link', 1 ) ) == 1 ) {
							?>
							<p><a class="button" href="" target="_blank" id="EKMButton" disabled>EKM für diesen Einsatz erstellen <span class="dashicons dashicons-external"></span></a></p>
							<?php
						}
						?>
						<?php
					}//end if
					?>
				</div>
			</fieldset>
			<script>
				jQuery(function() {
					function formatEnum (option) {
					  if (!option.id) {
						return option.text;
					  }
					  var color = jQuery(option.element).data("color");
					  var datetime = jQuery(option.element).data("datetime");
					  var $option = jQuery(
						'<span style="border-left: 3px solid '+color+'; padding-left: 3px;">' + option.text + ' <small style="color: #444;">'+datetime+'</small></span>'
					  );
					  return $option;
					}
					jQuery("#eib_enum").select2({
						placeholder: "Einsatz auswählen",
						allowClear: true,
						inputTooShort: function () {return "Mehr Buchstaben eingeben...";},
						noResults: function () {return 'Keine Übereinstimmungen gefunden';},
						templateResult: formatEnum
					});
					let url = "https://ekm.matthiasschaffer.com/?ffid=<?php echo urlencode( esc_attr( get_option( 'eib_feuerwehr', '' ) ) ); ?>&enum=";
					<?php
					if ( $saved_enum != '' ) {
						echo 'jQuery("#EKMButton").removeAttr("disabled").attr("href", url+"' . $saved_enum . '");';
					}
					?>
					jQuery("#eib_enum").on('select2:select', function (e) {
						jQuery("#EKMButton").removeAttr("disabled").attr("href", url+this.value);
					});
				});
			</script>
		<?php
		// Security field
		// This validates that submission came from the
		// actual dashboard and not the front end or
		// a remote server.
		wp_nonce_field( 'eib_form_metabox_nonce', 'eib_form_metabox_process' );

	}//end eib_render_enum()


	function eib_save_metabox( $post_id, $post ) {
		// Verify that our security field exists. If not, bail.
		if ( ! isset( $_POST['eib_form_metabox_process'] ) ) {
			return;
		}

		// Verify data came from edit/dashboard screen
		if ( ! wp_verify_nonce( $_POST['eib_form_metabox_process'], 'eib_form_metabox_nonce' ) ) {
			return $post->ID;
		}

		// Verify user has permission to edit post
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $post->ID;
		}

		// Check that our custom fields are being passed along
		// This is the `name` value array. We can grab all
		// of the fields and their values at once.
		if ( ! isset( $_POST['eib_enum'] ) ) {
			return $post->ID;
		}

		/*
		 * Sanitize the submitted data
		 * This keeps malicious code out of our database.
		 * `wp_filter_post_kses` strips our dangerous server values
		 * and allows through anything you can include a post.
		 */
		$sanitized = wp_filter_post_kses( $_POST['eib_enum'] );
		if ( $sanitized == '' ) {
			delete_post_meta( $post->ID, 'eib_enum' );
		} else {
			update_post_meta( $post->ID, 'eib_enum', $sanitized );
		}

	}//end eib_save_metabox()


	add_action( 'save_post', 'eib_save_metabox', 1, 2 );

	/*
		ADMIN MENU
	-------------------------- */
	add_action( 'admin_menu', 'eib_create_menu' );


	function eib_create_menu() {
		add_menu_page( 'Einsatzberichte Einstellungen', 'EIB-Konfig', 'administrator', __FILE__, 'eib_settings_page' );

		add_action( 'admin_init', 'register_eib_settings' );

	}//end eib_create_menu()


	/*
		KONFIGURATIONS SEITE
	-------------------------- */
	function register_eib_settings() {
		register_setting( 'eib-settings-group', 'eib_feuerwehr', 'text' );
		register_setting( 'eib-settings-group', 'eib_jahres_stats', 'intval' );
		register_setting( 'eib-settings-group', 'eib_ekm_link', 'intval' );

	}//end register_eib_settings()


	function eib_settings_page() {

		// Reset Cache if eib_feuerwehr was changed
		delete_transient("get_ff_einsaetze");
		delete_transient("get_ff_lfd");

		?>
<div class="wrap">
<h1>Einsatzberichte Einstellungen</h1>

<form method="post" action="options.php">
		<?php settings_fields( 'eib-settings-group' ); ?>
		<?php do_settings_sections( 'eib-settings-group' ); ?>
	<table class="form-table">
		<tr valign="top">
		<th scope="row">Feuerwehrname</th>
		<td>
			<!--<input type="text" name="eib_feuerwehr" value="<?php echo esc_attr( get_option( 'eib_feuerwehr', '' ) ); ?>" />-->
			<select id="eib_feuerwehr" name="eib_feuerwehr" style="width: 100%;">
						<option></option>
						<?php
						$json     = eib_api_call("get_ffs");
						foreach ( $json as $ffid => $wehr ) {
							if ( esc_attr( get_option( 'eib_feuerwehr', '' ) ) == $ffid ) {
								echo '<option value ="'.$ffid.'" selected>' . $wehr . '</option>';
							} else {
								echo '<option value ="'.$ffid.'">' . $wehr . '</option>';
							}
						}
						?>
					</select>
		</td>
		</tr>
		<tr valign="top">
		<th scope="row">Statistiken bei der Jahresübersicht anzeigen</th>
		<td><input type="checkbox" name="eib_jahres_stats" value="1" <?php echo ( get_option( 'eib_jahres_stats', 0 ) == 1 ) ? 'checked' : ''; ?> /></td>
		</tr>
		<tr valign="top">
		<th scope="row">Link zur EKM anzeigen</th>
		<td><input type="checkbox" name="eib_ekm_link" value="1" <?php echo ( get_option( 'eib_ekm_link', 1 ) == 1 ) ? 'checked' : ''; ?> /></td>
		</tr>
	</table>
	
		<?php submit_button(); ?>

</form>
</div>
<script>
	jQuery(function() {
		jQuery("#eib_feuerwehr").select2({
			placeholder: "Feuerwehr auswählen",
			allowClear: true,
			inputTooShort: function () {return "Mehr Buchstaben eingeben...";},
		  noResults: function () {return 'Keine Übereinstimmungen gefunden';}
		});
	});
</script>
		<?php

	}//end eib_settings_page()


	/*
		WIDGET "Letzte Einsätze"
	-------------------------- */
	// Creating the widget
	class eib_widget_last extends WP_Widget {



		function __construct() {
			parent::__construct(
			// Base ID of your widget
				'eib_widget_last',
				// Widget name will appear in UI
				__( 'Einsatzberichte OÖ', 'eib_widget_last' ),
				// Widget description
				[ 'description' => __( 'Einsätze werden vom LFK OÖ abgerufen und mit verknüpften Einsatzberichten angezeigt.', 'eib_widget_last' ) ]
			);

		}//end __construct()


		// Creating widget front-end
		public function widget( $args, $instance ) {
			$defaults = [
				'title'            => 'Letzten Eins&auml;tze',
				'anzahl'           => 3,
				'zeigeDatum'       => true,
				'zeigeZeit'        => true,
				'zeigeOhneBericht' => true,
				'zeigeIcon'        => true,
				'zeigeMitFarben'   => true,
			];
			$instance = wp_parse_args( $instance, $defaults );
			$title    = apply_filters( 'widget_title', $instance['title'] );

			echo $args['before_widget'];
			if ( ! empty( $title ) ) {
				echo $args['before_title'] . $title . $args['after_title'];
			}

			if ( esc_attr( get_option( 'eib_feuerwehr', '' ) ) == '' ) {
				echo '<p>Kein Feuerwehrname gesetzt! Navigiere als Administrator zu "EIB-Konfig" um den Namen zu hinterlegen.</p>';
			} else {
				global $wpdb;
				$result = $wpdb->get_results( 'SELECT `post_id`,`meta_value` FROM `' . $wpdb->prefix . "postmeta` WHERE `meta_key`='eib_enum'" );
				$enums  = [];
				foreach ( $result as &$item ) {
					$enums[ $item->meta_value ] = $item->post_id;
				}

				$artcolor = [
					'brand'    => 'red',
					'tee'      => 'blue',
					'unwetter' => 'green',
					'person'   => 'yellow',
					'sonstige' => 'black',
				];
				$json     = eib_api_call("get_ff_einsaetze");
				$num      = 0;
				if ( count( $json ) == 0 ) {
					echo '<p>Keine Einsätze gefunden.</p>';
				} else {
					echo '<ul>';
					foreach ( $json as $enum => $item ) {
						if ( $num >= $instance['anzahl'] ) {
							break;
						}

						if ( ! $instance['zeigeOhneBericht'] ) {
							if ( ! array_key_exists( $enum, $enums ) ) {
								continue;
							}
						}

						echo '<li ';
						if ( $instance['zeigeMitFarben'] ) {
									echo "style='border-left: 3px solid " . $artcolor[ strtolower( $item['einsatzart'] ) ] . "; padding-left: 3px;'";
						}

						if ( ! array_key_exists( $enum, $enums ) ) {
							 echo "title='Kein Bericht vorhanden.'";
						}

						echo '>';
						if ( array_key_exists( $enum, $enums ) ) {
							   echo "<a href='" . get_permalink( $enums[ $enum ] ) . "'>";
						}

						echo '<strong>' . $item['subtyp'] . '</strong>';
						if ( array_key_exists( $enum, $enums ) && $instance['zeigeIcon'] ) {
								echo " <i class='fa fa-link' aria-hidden='true'></i>";
						}

						if ( $item['status'] == 'offen' ) {
							  echo "<br><span class='blink_text'>Laufender Einsatz!</span>";
						}

						if ( $instance['zeigeDatum'] ) {
							echo '<br>' . date( 'd.m.Y H:i', strtotime( $item['zeit'] ) );
						}

						if ( array_key_exists( $enum, $enums ) ) {
							  echo '</a>';
						}

						echo '</li>';

						$num++;
					}//end foreach

					echo '</ul>';
				}//end if
			}//end if

			echo $args['after_widget'];

		}//end widget()


		// Widget Backend
		public function form( $instance ) {
			 $title           = ( isset( $instance['title'] ) ) ? $instance['title'] : 'Letzte Eins&auml;tze';
			$anzahl           = $instance['anzahl'];
			$zeigeDatum       = $instance['zeigeDatum'];
			$zeigeOhneBericht = $instance['zeigeOhneBericht'];
			$zeigeIcon        = $instance['zeigeIcon'];
			$zeigeMitFarben   = $instance['zeigeMitFarben'];
			?>
			<p>
				<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Titel:' ); ?></label> 
				<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id( 'anzahl' ); ?>"><?php _e( 'Anzahl der angezeigten Einsätze:' ); ?></label> 
				<input class="widefat" id="<?php echo $this->get_field_id( 'anzahl' ); ?>" name="<?php echo $this->get_field_name( 'anzahl' ); ?>" type="number" value="<?php echo esc_attr( $anzahl ); ?>" min="1" step="1"/>
			</p>
			<p>
				<label for="<?php echo $this->get_field_id( 'zeigeDatum' ); ?>"><?php _e( 'Zeige Datum und Uhrzeit an:' ); ?></label> 
				<input class="widefat" id="<?php echo $this->get_field_id( 'zeigeDatum' ); ?>" name="<?php echo $this->get_field_name( 'zeigeDatum' ); ?>" type="checkbox" <?php echo checked( $zeigeDatum, 'on', false ); ?>/>
			</p>
			<p>
				<label for="<?php echo $this->get_field_id( 'zeigeOhneBericht' ); ?>"><?php _e( 'Zeige Einsätze ohne Bericht an:' ); ?></label> 
				<input class="widefat" id="<?php echo $this->get_field_id( 'zeigeOhneBericht' ); ?>" name="<?php echo $this->get_field_name( 'zeigeOhneBericht' ); ?>" type="checkbox" <?php echo checked( $zeigeOhneBericht, 'on', false ); ?>/>
			</p>
			<p>
				<label for="<?php echo $this->get_field_id( 'zeigeIcon' ); ?>"><?php _e( 'Zeige Icon wenn ein Bericht vorhanden ist:' ); ?></label> 
				<input class="widefat" id="<?php echo $this->get_field_id( 'zeigeIcon' ); ?>" name="<?php echo $this->get_field_name( 'zeigeIcon' ); ?>" type="checkbox" <?php echo checked( $zeigeIcon, 'on', false ); ?>/>
			</p>
			<p>
				<label for="<?php echo $this->get_field_id( 'zeigeMitFarben' ); ?>"><?php _e( 'Zeige Einsatztyp als Farbe an:' ); ?></label> 
				<input class="widefat" id="<?php echo $this->get_field_id( 'zeigeMitFarben' ); ?>" name="<?php echo $this->get_field_name( 'zeigeMitFarben' ); ?>" type="checkbox" <?php echo checked( $zeigeMitFarben, 'on', false ); ?>/>
			</p>
			<?php

		}//end form()


		// Updating widget replacing old instances with new
		public function update( $new_instance, $old_instance ) {
			$instance                     = [];
			$instance['title']            = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
			$instance['anzahl']           = ( ! empty( $new_instance['anzahl'] ) ) ? strip_tags( $new_instance['anzahl'] ) : '';
			$instance['zeigeDatum']       = ( ! empty( $new_instance['zeigeDatum'] ) ) ? strip_tags( $new_instance['zeigeDatum'] ) : '';
			$instance['zeigeOhneBericht'] = ( ! empty( $new_instance['zeigeOhneBericht'] ) ) ? strip_tags( $new_instance['zeigeOhneBericht'] ) : '';
			$instance['zeigeIcon']        = ( ! empty( $new_instance['zeigeIcon'] ) ) ? strip_tags( $new_instance['zeigeIcon'] ) : '';
			$instance['zeigeMitFarben']   = ( ! empty( $new_instance['zeigeMitFarben'] ) ) ? strip_tags( $new_instance['zeigeMitFarben'] ) : '';
			return $instance;

		}//end update()


	}//end class

	/*
		WIDGET "Statistiken"
	-------------------------- */

	// Creating the widget
	class eib_widget_stats extends WP_Widget {



		function __construct() {
			parent::__construct(
			// Base ID of your widget
				'eib_widget_stats',
				// Widget name will appear in UI
				__( 'Einsatzberichte Statistiken', 'eib_widget_stats' ),
				// Widget description
				[ 'description' => __( 'Statistiken von deiner Feuerwehr', 'eib_widget_stats' ) ]
			);

		}//end __construct()


		// Creating widget front-end
		public function widget( $args, $instance ) {
			$defaults = [ 'title' => 'Einsatzstatistiken {year}' ];
			$instance = wp_parse_args( $instance, $defaults );
			$title    = apply_filters( 'widget_title', $instance['title'] );

			echo $args['before_widget'];
			if ( ! empty( $title ) ) {
				echo $args['before_title'] . str_replace( [ '{year}' ], [ date( 'Y' ) ], $title ) . $args['after_title'];
			}

			if ( esc_attr( get_option( 'eib_feuerwehr', '' ) ) == '' ) {
				echo '<p>Kein Feuerwehrname gesetzt! Navigiere als Administrator zu "EIB-Konfig" um den Namen zu hinterlegen.</p>';
			} else {
				$artcolor = [
					'brand'    => 'red',
					'tee'      => 'blue',
					'unwetter' => 'green',
					'person'   => 'yellow',
					'sonstige' => 'black',
				];
				$json  = eib_api_call("get_ff_einsaetze");
				$num   = 0;
				$array = [
					'art' => [
						'PERSON'   => 0,
						'TEE'      => 0,
						'BRAND'    => 0,
						'UNWETTER' => 0,
						'SONSTIGE' => 0,
					],
				];
				if ( count( $json ) == 0 ) {
					 echo '<p>Keine Einsätze gefunden.</p>';
				} else {
					foreach ( $json as $enum => $item ) {
						if ( date( 'Y' ) != date( 'Y', strtotime( $item['zeit'] ) ) ) {
							continue;
						}

						  $array['art'][ $item['einsatzart'] ]++;
						  $num++;
					}

					if ( $num == 0 ) {
						 echo '<p>Dieses Jahr sind noch keine Einsätze vorhanden.</p>';
					} else {
						echo ( $num == 1 ) ? '<p><strong>Ein Einsatz' : "<p><strong>$num Einsätze";
						echo '</strong> davon...<br>';
						echo ( $array['art']['TEE'] === 1 ) ? '<strong>ein</strong> technischer Einsatz<br>' : '<strong>' . $array['art']['TEE'] . '</strong> technische Einsätze<br>';
						echo ( $array['art']['BRAND'] === 1 ) ? '<strong>ein</strong> Brandeinsatz<br>' : '<strong>' . $array['art']['BRAND'] . '</strong> Brandeinsätze<br>';
						echo ( $array['art']['PERSON'] === 1 ) ? '<strong>eine</strong> Personenrettung<br>' : '<strong>' . $array['art']['PERSON'] . '</strong> Personenrettungen<br>';
						echo ( $array['art']['UNWETTER'] === 1 ) ? '<strong>ein</strong> Unwettereinsatz<br>' : '<strong>' . $array['art']['UNWETTER'] . '</strong> Unwettereinsätze<br>';
						echo ( $array['art']['SONSTIGE'] === 1 ) ? '<strong>ein</strong> sonstiger Einsatz<br>' : '<strong>' . $array['art']['UNWETTER'] . '</strong> sonstige Einsätze<br>';
					}
				}//end if
			}//end if

			echo $args['after_widget'];

		}//end widget()


		// Widget Backend
		public function form( $instance ) {
			 $title = $instance['title'];
			?>
				<p>
					<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Titel:' ); ?></label> 
					<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
					<small>{year} wird durch das aktuelle Jahr ersetzt</small>
				</p>
			<?php

		}//end form()


		public function update( $new_instance, $old_instance ) {
			$instance          = [];
			$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
			return $instance;

		}//end update()


	}//end class

	/*
		WIDGET "Einsatzkarte"
	-------------------------- */

	// Creating the widget
	class eib_widget_map extends WP_Widget {



		function __construct() {
			parent::__construct(
			// Base ID of your widget
				'eib_widget_map',
				// Widget name will appear in UI
				__( 'Einsatzkarte OÖ', 'eib_widget_map' ),
				// Widget description
				[ 'description' => __( 'Laufende Einsatzkarte von OÖ mit optionaler Unwetterkarte im Hintergrund.', 'eib_widget_map' ) ]
			);

		}//end __construct()


		// Creating widget front-end
		public function widget( $args, $instance ) {
			$defaults = [
				'title' => 'Laufende Einsätze in OÖ',
				'map'   => 'unwetter',
				'size'  => '570',
			];
			$instance = wp_parse_args( $instance, $defaults );
			$title    = apply_filters( 'widget_title', $instance['title'] );
			$map      = $instance['map'];
			$size     = $instance['size'];

			echo $args['before_widget'];
			if ( ! empty( $title ) ) {
				echo $args['before_title'] . $title . $args['after_title'];
			}
			if (empty($map)) {
				$map = '';
			}

			$base64 = eib_api_call("get_map", ["map" => $map, "cache_name" => "get_map_".$map]);

			echo '<img src="data:image/png;base64,'.$base64.'" width="' . $size . 'px">';
			echo $args['after_widget'];

		}//end widget()


		// Widget Backend
		public function form( $instance ) {
			 $title = $instance['title'];
			$map    = $instance['map'];
			$size   = $instance['size'];
			?>
			<p>
				<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Titel:' ); ?></label> 
				<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id( 'map' ); ?>"><?php _e( 'Kartenhintergrund' ); ?></label>
				<select  id="<?php echo $this->get_field_id( 'map' ); ?>" name="<?php echo $this->get_field_name( 'map' ); ?>">
			<?php
			 $backings = [
				 'default' => 'weißer Hintergrund',
				 'all'     => 'Unwetterkarte',
			 ];
			 foreach ( $backings as $key => $value ) {
				 if ( $key == esc_attr( $map ) ) {
					  echo "<option value='$key' selected>$value</option>";
				 } else {
					 echo "<option value='$key'>$value</option>";
				 }
			 }
				?>
				</select>
			</p>
			<p>
				<label for="<?php echo $this->get_field_id( 'size' ); ?>"><?php _e( 'Breite (in Pixel):' ); ?></label> 
				<input class="widefat" id="<?php echo $this->get_field_id( 'size' ); ?>" name="<?php echo $this->get_field_name( 'size' ); ?>" type="number" value="<?php echo esc_attr( $size ); ?>" />
			</p>
			<?php

		}//end form()


		// Updating widget replacing old instances with new
		public function update( $new_instance, $old_instance ) {
			$instance          = [];
			$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
			$instance['map']   = ( ! empty( $new_instance['map'] ) ) ? strip_tags( $new_instance['map'] ) : '';
			$instance['size']  = ( ! empty( $new_instance['size'] ) ) ? strip_tags( $new_instance['size'] ) : '';
			return $instance;

		}//end update()


	}//end class
	
	/*
		WIDGET "Laufende Einsätze"
	-------------------------- */

	// Creating the widget
	class eib_widget_lfd extends WP_Widget {



		function __construct() {
			parent::__construct(
			// Base ID of your widget
				'eib_widget_lfd',
				// Widget name will appear in UI
				__( 'Einsatzberichte Laufende Einsätze in OÖ', 'eib_widget_lfd' ),
				// Widget description
				[ 'description' => __( 'Laufende Einsätze werden vom LFK OÖ abgerufen und dargestellt.', 'eib_widget_lfd' ) ]
			);

		}//end __construct()


		// Creating widget front-end
		public function widget( $args, $instance ) {
			$defaults = [
				'title'            => 'Laufende Eins&auml;tze',
				'anzahl'           => 5,
				'zeigeDatum'       => true,
				'zeigeAnzahlFeuerwehren'   => true,
				'linkEinsatzinfo' => true,
				'zeigeMitFarben'   => true,
			];
			$instance = wp_parse_args( $instance, $defaults );
			$title    = apply_filters( 'widget_title', $instance['title'] );

			echo $args['before_widget'];
			if ( ! empty( $title ) ) {
				echo $args['before_title'] . $title . $args['after_title'];
			}

			$artcolor = [
				'brand'    => 'red',
				'tee'      => 'blue',
				'unwetter' => 'green',
				'person'   => 'yellow',
				'sonstige' => 'black',
			];
			$json     = eib_api_call("get_lfd");
			$num      = 0;
			if ( !$json["offen"] ) {
				echo '<p>Keine laufenden Einsätze in Oberösterreich.</p>';
			} else {
				echo '<ul>';
				foreach ( $json["einsaetze"] as $item ) {
					if ( $num >= $instance['anzahl'] ) {
						break;
					}

					$enum = $item["num"];

					echo '<li ';
					if ( $instance['zeigeMitFarben'] ) {
						echo "style='border-left: 3px solid " . $artcolor[ strtolower( $item['einsatzart'] ) ] . "; padding-left: 3px;'";
					}

					echo '>';
					if ( $instance['linkEinsatzinfo'] ) {
						   echo "<a href='".EIB_API_HOSTNAME."details/".$enum."' target=\"_blank\">";
					}

					echo '<strong>' . $item['subtyp'] . '</strong>';

					if ( $instance['zeigeDatum'] ) {
						echo '<br>' . date( 'd.m.Y H:i', strtotime( $item['zeit'] ) );
					}

					if ( $instance['zeigeAnzahlFeuerwehren'] ) {
						$cnt_feuerwehren = count($item["wehr"]);
						echo '<br>' . ($cnt_feuerwehren == 1 ? "Eine Feuerwehr" : $cnt_feuerwehren." Feuerwehren");
					}

					if ( $instance['linkEinsatzinfo'] ) {
						  echo '</a>';
					}

					echo '</li>';

					$num++;
				}//end foreach

				echo '</ul>';
			}//end if

			echo $args['after_widget'];

		}//end widget()


		// Widget Backend
		public function form( $instance ) {
			 $title           = ( isset( $instance['title'] ) ) ? $instance['title'] : 'Laufende Eins&auml;tze';
			$anzahl           = $instance['anzahl'];
			$zeigeDatum       = $instance['zeigeDatum'];
			$zeigeAnzahlFeuerwehren = $instance['zeigeAnzahlFeuerwehren'];
			$linkEinsatzinfo  = $instance['linkEinsatzinfo'];
			$zeigeMitFarben   = $instance['zeigeMitFarben'];
			?>
			<p>
				<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Titel:' ); ?></label> 
				<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id( 'anzahl' ); ?>"><?php _e( 'Anzahl der angezeigten Einsätze:' ); ?></label> 
				<input class="widefat" id="<?php echo $this->get_field_id( 'anzahl' ); ?>" name="<?php echo $this->get_field_name( 'anzahl' ); ?>" type="number" value="<?php echo esc_attr( $anzahl ); ?>" min="1" step="1"/>
			</p>
			<p>
				<label for="<?php echo $this->get_field_id( 'zeigeDatum' ); ?>"><?php _e( 'Zeige Datum und Uhrzeit an:' ); ?></label> 
				<input class="widefat" id="<?php echo $this->get_field_id( 'zeigeDatum' ); ?>" name="<?php echo $this->get_field_name( 'zeigeDatum' ); ?>" type="checkbox" <?php echo checked( $zeigeDatum, 'on', false ); ?>/>
			</p>
			<p>
				<label for="<?php echo $this->get_field_id( 'zeigeAnzahlFeuerwehren' ); ?>"><?php _e( 'Zeige Anzahl der Feuerwehren an:' ); ?></label> 
				<input class="widefat" id="<?php echo $this->get_field_id( 'zeigeAnzahlFeuerwehren' ); ?>" name="<?php echo $this->get_field_name( 'zeigeAnzahlFeuerwehren' ); ?>" type="checkbox" <?php echo checked( $zeigeAnzahlFeuerwehren, 'on', false ); ?>/>
			</p>
			<p>
				<label for="<?php echo $this->get_field_id( 'linkEinsatzinfo' ); ?>"><?php _e( 'Verlinke Einsatz zu einsatzinfo.cloud:' ); ?></label> 
				<input class="widefat" id="<?php echo $this->get_field_id( 'linkEinsatzinfo' ); ?>" name="<?php echo $this->get_field_name( 'linkEinsatzinfo' ); ?>" type="checkbox" <?php echo checked( $linkEinsatzinfo, 'on', false ); ?>/>
			</p>
			<p>
				<label for="<?php echo $this->get_field_id( 'zeigeMitFarben' ); ?>"><?php _e( 'Zeige Einsatztyp als Farbe an:' ); ?></label> 
				<input class="widefat" id="<?php echo $this->get_field_id( 'zeigeMitFarben' ); ?>" name="<?php echo $this->get_field_name( 'zeigeMitFarben' ); ?>" type="checkbox" <?php echo checked( $zeigeMitFarben, 'on', false ); ?>/>
			</p>
			<?php

		}//end form()


		// Updating widget replacing old instances with new
		public function update( $new_instance, $old_instance ) {
			$instance                     = [];
			$instance['title']            = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
			$instance['anzahl']           = ( ! empty( $new_instance['anzahl'] ) ) ? strip_tags( $new_instance['anzahl'] ) : '';
			$instance['zeigeDatum']       = ( ! empty( $new_instance['zeigeDatum'] ) ) ? strip_tags( $new_instance['zeigeDatum'] ) : '';
			$instance['zeigeAnzahlFeuerwehren'] = ( ! empty( $new_instance['zeigeAnzahlFeuerwehren'] ) ) ? strip_tags( $new_instance['zeigeAnzahlFeuerwehren'] ) : '';
			$instance['linkEinsatzinfo']  = ( ! empty( $new_instance['linkEinsatzinfo'] ) ) ? strip_tags( $new_instance['linkEinsatzinfo'] ) : '';
			$instance['zeigeMitFarben']   = ( ! empty( $new_instance['zeigeMitFarben'] ) ) ? strip_tags( $new_instance['zeigeMitFarben'] ) : '';
			return $instance;

		}//end update()


	}//end class

	/* WORDPRESS LADE WIDGETS */
	
	function eib_load_widget() {
		register_widget( 'eib_widget_last' );
		register_widget( 'eib_widget_lfd' );
		register_widget( 'eib_widget_stats' );
		register_widget( 'eib_widget_map' );

	}//end eib_load_widget()
	add_action( 'widgets_init', 'eib_load_widget' );

	/*
		JAHRESUEBERSICHT
	-------------------------- */
	// Shortcode: [eib_jahresuebersicht]
	add_shortcode( 'eib_jahresuebersicht', 'jahresuebersicht' );

	function eib_jahresuebersicht_register_scripts( $hook ) {
		wp_register_style( 'fa-4-7-0_css', plugins_url( 'font-awesome/css/font-awesome.min.css', __FILE__ ) );
		wp_register_style( 'eib-app-css', plugins_url( '/css/app.css', __FILE__ ) );
		wp_register_script( 'ChartJS-min-js', plugins_url( '/js/Chart.min.js', __FILE__ ), [ 'jquery' ], true, true );
		wp_register_script( 'ChartJS-plugin-labels-min-js', plugins_url( '/js/Chart-plugin-labels.min.js', __FILE__ ), [ 'jquery' ], true, true );
		wp_register_script( 'eib-jahresuebersicht-js', plugins_url( '/js/jahresuebersicht.js', __FILE__ ), [ 'jquery' ], true, true );

	}//end eib_jahresuebersicht_register_scripts()
	add_action( 'init', 'eib_jahresuebersicht_register_scripts' );

	function jahresuebersicht( $atts ) {
		wp_enqueue_style( 'fa-4-7-0_css' );
		wp_enqueue_style( 'eib-app-css' );
		wp_enqueue_script( 'ChartJS-min-js', [ 'jquery' ], true, true );
		wp_enqueue_script( 'ChartJS-plugin-labels-min-js', [ 'jquery' ], true, true );
		wp_enqueue_script( 'eib-jahresuebersicht-js', [ 'jquery' ], true, true );
		wp_localize_script(
			'eib-jahresuebersicht-js',
			'ajax_object',
			[
				'getYear'     => '',
				'plugin_root' => plugins_url( '/', __FILE__ ),
			]
		);

		if ( esc_attr( get_option( 'eib_feuerwehr', '' ) ) == '' ) {
			return '<p>Kein Feuerwehrname gesetzt! Navigiere als Administrator zu "EIB-Konfig" um den Namen zu hinterlegen.</p>';
		}

		$json = eib_api_call("get_ff_einsaetze");
		$years    = [];
		foreach ( $json as &$item ) {
			$years[] = date( 'Y', strtotime( $item['zeit'] ) );
		}

		$years   = array_unique( $years );
		$options = '';
		foreach ( $years as &$year ) {
			$options .= '<option>' . $year . '</option>';
		}

		$html = '<div id="eib_jahresuebersicht_preloader" class="preloader"></div>
		<div>
			<label for="eib_jahresuebersicht_select">Jahresauswahl:</label>
			<select id="eib_jahresuebersicht_select" style="width: 100%;">
			' . $options . '
			</select>
		</div>
		<div id="eib_jahresuebersicht_container"></div>';
		return $html;

	}//end jahresuebersicht()
?>