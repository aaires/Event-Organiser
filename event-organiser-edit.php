<?php
/**
 * Functions for Event CPT editing / creating page 
 * @since 1.0.0
 */
/**
 * Initialises the plug-ins metaboxs on Event CPT
 * @since 1.0.0
 * @ignore
 */
function _eventorganiser_event_metaboxes_init(){
	
	// add a meta box to event post types.
	add_meta_box( 'eventorganiser_detail', __( 'Event Details', 'eventorganiser' ), '_eventorganiser_details_metabox', 'event', 'normal', 'high' );
	
	//Repurposes author metabox as organiser
	$post_type_object = get_post_type_object( 'event' );
	if( post_type_supports( 'event', 'author' ) ) {
		if ( is_super_admin() || current_user_can( $post_type_object->cap->edit_others_posts ) ){
			remove_meta_box( 'authordiv', 'event', 'core' );
			add_meta_box( 'authordiv',  __( 'Organiser', 'eventorganiser' ), 'post_author_meta_box', 'event', 'normal', 'core' );
		}
	}
}
add_action( 'add_meta_boxes_event', '_eventorganiser_event_metaboxes_init' );


/**
 * Sets up the event data metabox
* This allows user to enter date / time, reoccurrence and venue data for the event
 * @ignore
 * @since 1.0.0
 */
function _eventorganiser_details_metabox( $post ) {

	global $wp_locale;	

	//Sets the format as php understands it, and textual.
	$phpFormat = eventorganiser_get_option( 'dateformat' );
	if ( 'd-m-Y' == $phpFormat ) {
		$format = 'dd &ndash; mm &ndash; yyyy'; //Human form
	} elseif ( 'Y-m-d' == $phpFormat ) {
		$format = 'yyyy-mm-dd'; //Human form
	} else {
		$format = 'mm-dd-yyyy'; //Human form
	}

	$is24        = eventorganiser_blog_is_24();
	$time_format = $is24 ? 'H:i' : 'g:ia';

	//Get the starting day of the week
	$start_day = intval( get_option( 'start_of_week' ) );
	$ical_days = array( 'SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA' );

	//Retrieve event details
	$schedule_arr = eo_get_event_schedule( $post->ID );

	$schedule      = $schedule_arr['schedule'];
	$start         = $schedule_arr['start'];
	$end           = $schedule_arr['end'];
	$all_day       = $schedule_arr['all_day'];
	$frequency     = $schedule_arr['frequency'];
	$schedule_meta = $schedule_arr['schedule_meta'];
	$occurs_by     = $schedule_arr['occurs_by'];
	$until         = $schedule_arr['until'];
	$include       = $schedule_arr['include'];
	$exclude       = $schedule_arr['exclude'];

	$venues   = eo_get_venues();
	$venue_id = (int) eo_get_venue( $post->ID );

	//$sche_once is used to disable date editing unless the user specifically requests it.
	//But a new event might be recurring (via filter), and we don't want to 'lock' new events.
	//See https://wordpress.org/support/topic/wrong-default-in-input-element
	$sche_once = ( 'once' == $schedule || ! empty( get_current_screen()->action ) );

	if ( ! $sche_once ) {
		$notices = sprintf( 
			'<label for="eo-event-recurrring-notice">%s</label>',
			__( 'This is a reoccurring event. Check to edit this event and its reoccurrences', 'eventorganiser' )
		) 
		.' <input type="checkbox" id="eo-event-recurrring-notice" name="eo_input[AlterRe]" value="yes">';
	} else {
		$notices = '';
	}

	//Start of meta box
	/**
	 * Filters the notice at the top of the event details metabox.
	 *
	 * @param string  $notices The message text.
	 * @param WP_Post $post    The corresponding event (post).
	 */
	$notices = apply_filters( 'eventorganiser_event_metabox_notice', $notices, $post );
	if ( $notices ) {
		echo '<div class="updated below-h2"><p>'.$notices.'</p></div>';
	}
	
	$date_desc = sprintf( __( 'Enter date in %s format', 'eventorganiser' ), $format );
	$time_desc = $is24 ? __( 'Enter time in 24-hour hh colon mm format', 'eventorganiser' ) : __( 'Enter time in 12-hour hh colon mm am or pm format', 'eventorganiser' );
	?>
	<div class="eo-grid <?php echo ( $sche_once ? 'onetime': 'reoccurence' );?>">
	
 		<div class="eo-grid-row">
	 		<div class="eo-grid-4">
				<span class="eo-label" id="eo-start-datetime-label">
					<?php esc_html_e( 'Start Date/Time', 'eventorganiser' ).':'; ?> 
				</span>
 			</div>
	 		<div class="eo-grid-8 event-date" role="group" aria-labelledby="eo-start-datetime-label">
	 		
	 			<label for="eo-start-date" class="screen-reader-text"><?php esc_html_e( 'Start Date', 'eventorganiser' ); ?></label>
				<input id="eo-start-date" aria-describedby="eo-start-date-desc" class="ui-widget-content ui-corner-all" name="eo_input[StartDate]" size="10" maxlength="10" <?php disabled( ! $sche_once );?> value="<?php echo $start->format( $phpFormat ); ?>"/>
				<span id="eo-start-date-desc" class="screen-reader-text"><?php echo  $date_desc;?></span>
				
				<label for="eo-start-time" class="screen-reader-text"><?php esc_html_e( 'Start Time', 'eventorganiser' ); ?></label>
				<?php
				printf(
					'<input id="eo-start-time" aria-describedby="eo-start-time-desc" name="eo_input[StartTime]" class="eo_time ui-widget-content ui-corner-all" size="6" maxlength="8" %s value="%s"/>',
					disabled( ( ! $sche_once ) || $all_day, true, false ),
					eo_format_datetime( $start, $time_format )
				);
				?>
				<span id="eo-start-time-desc" class="screen-reader-text"><?php echo esc_html( $time_desc );?></span>
 			</div>
 		</div>
 		
		<div class="eo-grid-row">
	 		<div class="eo-grid-4">
				<span class="eo-label" id="eo-end-datetime-label">
					<?php esc_html_e( 'End Date/Time', 'eventorganiser' ).':'; ?> 
				</span>
 			</div>
	 		<div class="eo-grid-8 event-date" role="group" aria-labelledby="eo-end-datetime-label">
	 		
	 			<label for="eo-end-date" class="screen-reader-text"><?php esc_html_e( 'End Date', 'eventorganiser' ); ?></label>
				<input id="eo-end-date" aria-describedby="eo-end-date-desc" class="ui-widget-content ui-corner-all" name="eo_input[EndDate]" size="10" maxlength="10" <?php disabled( ! $sche_once );?>  value="<?php echo $end->format( $phpFormat ); ?>"/>
				<span id="eo-end-date-desc" class="screen-reader-text"><?php echo esc_html( $date_desc );?></span>
				
				<label for="eo-end-time" class="screen-reader-text"><?php esc_html_e( 'End Time', 'eventorganiser' ); ?></label>
				<?php
				printf(
					'<input id="eo-end-time" aria-describedby="eo-end-time-desc" name="eo_input[FinishTime]" class="eo_time ui-widget-content ui-corner-all" size="6" maxlength="8" %s value="%s"/>',
					disabled( ( ! $sche_once ) || $all_day, true, false ),
					eo_format_datetime( $end, $time_format )
				);
				?>
				<span id="eo-end-time-desc" class="screen-reader-text"><?php echo esc_html( $time_desc );?></span>	
				<label for="eo_allday">
					<input type="checkbox" id="eo_allday"  <?php checked( $all_day ); ?> name="eo_input[allday]"  <?php  disabled( ! $sche_once );?> value="1"/>
					<?php esc_html_e( 'All day', 'eventorganiser' );?>
				</label>
 			</div>
 		</div>
 		
		<div class="eo-grid-row event-date">
	 		<div class="eo-grid-4">
				<label for="eo-event-recurrence"><?php esc_html_e( 'Reoccurence', 'eventorganiser' ).':'; ?> </label>
 			</div>
	 		<div class="eo-grid-8 event-date">
				<?php
				$reoccurrence_schedules = array(
					'once' => __( 'none', 'eventorganiser' ), 'daily' => __( 'daily', 'eventorganiser' ), 'weekly' => __( 'weekly', 'eventorganiser' ),
					'monthly' => __( 'monthly', 'eventorganiser' ), 'yearly' => __( 'yearly', 'eventorganiser' ), 'custom' => __( 'custom', 'eventorganiser' ),
				);
				?>
				<select id="eo-event-recurrence" name="eo_input[schedule]">
					<?php foreach ( $reoccurrence_schedules as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value )?>" <?php selected( $schedule, $value );?>><?php echo esc_html( $label );?></option>
					<?php endforeach; ?>
				</select>
 			</div>
 		</div>
 		
 		<div class="eo-grid-row event-date reocurrence_row">
	 		<div class="eo-grid-4"></div>
	 		<div class="eo-grid-8 event-date">
				<p>
					<?php esc_html_e( 'Repeat every', 'eventorganiser' );?>
					<label for="eo-recurrence-frequency" class="screen-reader-text"><?php esc_html_e( 'Recurrence frequency', 'eventorganiser' );?></label> 
					<input type="number" id="eo-recurrence-frequency" <?php  disabled( ! $sche_once || $all_day );?> class="ui-widget-content ui-corner-all" name="eo_input[event_frequency]"  min="1" max="365" maxlength="4" size="4" value="<?php echo intval( $frequency );?>" /> 
					<span id="recpan"></span>				
				</p>

				<div id="dayofweekrepeat">
				
					<span id="eo-days-of-week-label" class="screen-reader-text"><?php esc_html_e( 'Repeat on days of week:', 'eventorganiser' );?></span>
					<span class="eo-days-of-week-text"><?php esc_html_e( 'on', 'eventorganiser' );?></span>
					<ul class="eo-days-of-week" role="group" aria-labelledby="eo-days-of-week-label">	
						<?php
						for ( $i = 0; $i <= 6; $i++ ) :
							$d = ($start_day + $i) % 7;
							$ical_d = $ical_days[$d];
							$day = $wp_locale->weekday_abbrev[$wp_locale->weekday[$d]];
							$fullday = $wp_locale->weekday[$d];
							$schedule_days = ( is_array( $schedule_meta ) ? $schedule_meta : array() );
							?>
							<li>
								<input type="checkbox" id="day-<?php echo $day;?>"  <?php checked( in_array( $ical_d, $schedule_days ), true ); ?>  value="<?php echo esc_attr( $ical_d )?>" class="daysofweek" name="eo_input[days][]" disabled="disabled" />
								<label for="day-<?php echo $day;?>" > <abbr title="<?php echo $fullday; ?>"><?php echo $day;?></abbr></label>
							</li>
							<?php
						endfor;
						?>
					</ul>
				</div>

				<div id="dayofmonthrepeat">
					<span id="eo-days-of-month-label" class="screen-reader-text"><?php esc_html_e( 'Select whether to repeat monthly by date or day:', 'eventorganiser' );?></span>
					<div class="eo-days-of-month" role="group" aria-labelledby="eo-days-of-month-label">	
						<label for="bymonthday" >	
							<input type="radio" id="bymonthday" disabled="disabled" name="eo_input[schedule_meta]" <?php checked( $occurs_by, 'BYMONTHDAY' ); ?> value="BYMONTHDAY=" /> 
							<?php esc_html_e( 'date of month', 'eventorganiser' );?>
						</label>
						<label for="byday" >
							<input type="radio" id="byday" disabled="disabled" name="eo_input[schedule_meta]"  <?php checked( 'BYMONTHDAY' != $occurs_by, true ); ?> value="BYDAY=" />
							<?php esc_html_e( 'day of week', 'eventorganiser' );?>
						</label>
					</div>
				</div>

				<div class="reoccurrence_label">
					<?php esc_html_e( 'until', 'eventorganiser' );?>
					<label id="eo-repeat-until-label" for="recend" class="screen-reader-text"><?php esc_html_e( 'Repeat this event until:', 'eventorganiser' );?></label> 
					<input <?php  disabled( ( ! $sche_once ) || $all_day ); ?> class="ui-widget-content ui-corner-all" name="eo_input[schedule_end]" id="recend" size="10" maxlength="10" disabled="disabled" value="<?php echo $until->format( $phpFormat ); ?>"/>
				</div>

				<p id="event_summary" role="status" aria-live="polite"></p>
				
 			</div>
 		</div>

		<div id="eo_occurrence_picker_row" class="eo-grid-row event-date">
	 		<div class="eo-grid-4">
				<?php esc_html_e( 'Include/Exclude occurrences:', 'eventorganiser' ); ?>
 			</div>
	 		<div class="eo-grid-8 event-date">
				<?php submit_button( __( 'Show dates', 'eventorganiser' ), 'hide-if-no-js eo_occurrence_toggle button small', 'eo_date_toggle', false ); ?>
						
				<div id="eo_occurrence_datepicker"></div>
				<?php
				if ( ! empty( $include ) ) {
					$include_str = array_map( 'eo_format_datetime', $include, array_fill( 0, count( $include ), 'Y-m-d' ) );
					$include_str = esc_attr( sanitize_text_field( implode( ',', $include_str ) ) );
				} else {
					$include_str = '';
				}?>
				<input type="hidden" name="eo_input[include]" id="eo_occurrence_includes" value="<?php echo $include_str; ?>"/>

				<?php
				if ( ! empty($exclude) ) {
					$exclude_str = array_map( 'eo_format_datetime', $exclude, array_fill( 0, count( $exclude ), 'Y-m-d' ) );
					$exclude_str = esc_attr( sanitize_text_field( implode( ',', $exclude_str ) ) );
				} else {
					$exclude_str = '';
				}?>
				<input type="hidden" name="eo_input[exclude]" id="eo_occurrence_excludes" value="<?php echo $exclude_str; ?>"/>
	 		
 			</div>
 		</div>

		<?php
		$tax = get_taxonomy( 'event-venue' );
		if ( taxonomy_exists( 'event-venue' ) ) : ?>	
		
		<div class="eo-grid-row eo-venue-combobox-select">
	 		<div class="eo-grid-4">
				<label for="venue_select"><?php echo esc_html( $tax->labels->singular_name_colon ); ?></label>
 			</div>
	 		<div class="eo-grid-8">
				<select size="50" id="venue_select" name="eo_input[event-venue]">
					<option><?php _e( 'Select a venue', 'eventorganiser' );?></option>
					<?php foreach ( $venues as $venue ) : ?>
						<option <?php  selected( $venue->term_id, $venue_id );?> value="<?php echo intval( $venue->term_id );?>"><?php echo esc_html( $venue->name ); ?></option>
					<?php endforeach;?>
				</select>
 			</div>
 		</div>
 
 		<!-- Add New Venue --> 
 		<div class="eo-grid-row eo-add-new-venue">
	 		<div class="eo-grid-4">
				<label for="eo_venue_name"><?php esc_html_e( 'Venue Name', 'eventorganiser' ); ?></label>
 			</div>
	 		<div class="eo-grid-8">
				<input type="text" name="eo_venue[name]" id="eo_venue_name"  value=""/>
 			</div>			
			
			<?php
			$address_fields = _eventorganiser_get_venue_address_fields();
			foreach ( $address_fields as $key => $label ) {
				printf(
					'<div class="eo-grid-4">
						<label for="eo_venue_add-%2$s">%1$s:</label>
					</div>
					<div class="eo-grid-8">
						<input type="text" name="eo_venue[%2$s]" class="eo_addressInput" id="eo_venue_add-%2$s"  value=""/>
					</div>',
					$label,
					esc_attr( trim( $key, '_' ) )/* Keys are prefixed by '_' */
				);
			}
			?>
			
			<div class="eo-grid-4"></div>
	 		<div class="eo-grid-8 event-date">
				<a class="button eo-add-new-venue-cancel" href="#"><?php esc_html_e( 'Cancel','eventorganiser' );?> </a>
 			</div>
 		</div>
				
		<div class="eo-grid-row venue_row <?php if ( ! $venue_id ) { echo 'novenue'; }?>">
	 		<div class="eo-grid-4"></div>
	 		<div class="eo-grid-8">
				<div id="eventorganiser_venue_meta" style="display:none;">
					<input type="hidden" id="eo_venue_Lat" name="eo_venue[latitude]" value="<?php eo_venue_lat( $venue_id );?>" />
					<input type="hidden" id="eo_venue_Lng" name="eo_venue[longtitude]" value="<?php eo_venue_lng( $venue_id ); ?>" />
				</div>
					
				<div id="venuemap" class="ui-widget-content ui-corner-all gmap3"></div>
				<div class="clear"></div>
 			</div>
 		</div>
		<?php endif; //endif venue's supported ?>
		
	</div>
	<?php

	// create a custom nonce for submit verification later
	wp_nonce_field( 'eventorganiser_event_update_'.get_the_ID().'_'.get_current_blog_id(), '_eononce' );
}


/**
 * Saves the event data posted from the event metabox.
 * Hooked to the 'save_post' action
 * 
 * @since 1.0.0
 *
 * @param int $post_id the event post ID
 * @return int $post_id the event post ID
 */
function eventorganiser_details_save( $post_id ) {

	//make sure data came from our meta box
	if ( !isset( $_POST['_eononce'] ) || !wp_verify_nonce( $_POST['_eononce'], 'eventorganiser_event_update_'.$post_id.'_'.get_current_blog_id() ) ) return;

	//verify this is not an auto save routine. 
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	
	//authentication checks
	if ( !current_user_can( 'edit_event', $post_id ) ) return;

	//Collect raw data
	$raw_data = ( isset( $_POST['eo_input'] ) ? $_POST['eo_input'] : array() );
	$raw_data = wp_parse_args( $raw_data, array(
		'StartDate' => '', 'EndDate' => '', 'StartTime' => '00:00', 'FinishTime' => '23:59', 'schedule' => 'once', 'event_frequency' => 1,
		'schedule_end' => '', 'allday' => 0, 'schedule_meta' => '', 'days' => array(), 'include' => '', 'exclude' => '', ) );

	//var_dump( $_POST );
	//var_dump( $raw_data );
	//exit;
	//Update venue
	$venue_id = !empty( $raw_data['event-venue'] ) ? intval( $raw_data['event-venue'] ) : null;

	//Maybe create a new venue
	if ( empty( $venue_id ) && !empty( $_POST['eo_venue']) && current_user_can( 'manage_venues' ) ){
		$venue = $_POST['eo_venue'];
		if ( !empty( $venue['name'] ) ){
			$new_venue = eo_insert_venue( $venue['name'], $venue );
			if ( !is_wp_error( $new_venue ) ){
				$venue_id = $new_venue['term_id'];
			}else{
				if( $new_venue->get_error_code() == 'term_exists' ){
					$venue_id = eo_get_venue( $event_id );
				}
			}
		}
	}

	//Set venue
	$r = wp_set_post_terms( $post_id, array( $venue_id ), 'event-venue', false );


	//If reocurring, but not editing occurrences, can abort here, but trigger hook.
	if ( eo_reoccurs( $post_id ) && ( !isset( $raw_data['AlterRe'] ) || 'yes' != $raw_data['AlterRe'] ) ){
	   /**
 		* Triggered after an event has been updated.
 		*
 		* @param int $post_id The ID of the event
 		*/
		do_action( 'eventorganiser_save_event', $post_id );//Need this to update cache
		return;
	}

	//Check dates
	$date_format = eventorganiser_get_option( 'dateformat' );
	$is24 = eventorganiser_blog_is_24();
	$time_format = $is24 ? 'H:i' : 'g:ia';
	$datetime_format = $date_format . ' ' . $time_format;
	
	//Set times for all day events
	$all_day = intval( $raw_data['allday'] );
	if ( $all_day ){
		$raw_data['StartTime']  = $is24 ? '00:00' : '12:00am';
		$raw_data['FinishTime'] = $is24 ? '23:59' : '11:59pm';
	}
	
	$start = eo_check_datetime( $datetime_format, trim( $raw_data['StartDate'] ) . ' ' . trim( $raw_data['StartTime'] ) );
	$end   = eo_check_datetime( $datetime_format, trim( $raw_data['EndDate'] ) . ' ' . trim( $raw_data['FinishTime'] ) );
	$until = eo_check_datetime( $datetime_format, trim( $raw_data['schedule_end'] ) . ' ' . trim( $raw_data['StartTime'] ) );
	
	//Collect schedule meta
	$schedule = $raw_data['schedule'];
	if ( 'weekly' == $schedule ){
		$schedule_meta = $raw_data['days'];
		$occurs_by = '';
	} elseif (  'monthly' == $schedule ){
		$schedule_meta = $raw_data['schedule_meta'];
		$occurs_by = trim( $schedule_meta, '=' );
	} else {
		$schedule_meta = '';
		$occurs_by     = '';
	}

	//Collect include/exclude 
	$in_ex         = array();
	$orig_schedule = eo_get_event_schedule( $post_id );
	foreach ( array( 'include', 'exclude' ) as $key ):
		
		$in_ex[$key] = array();
		$arr         = explode( ',', sanitize_text_field( $raw_data[$key] ) );
		
		if ( !empty( $arr ) ){
			foreach ( $arr as $date ){
				if( $date_obj = eo_check_datetime( 'Y-m-d', trim( $date ) ) ){
					$date_obj->setTime( $start->format('H'), $start->format('i') );
					$in_ex[$key][] = $date_obj;
				}
			}

			/* see https://github.com/stephenharris/Event-Organiser/issues/260
			if( $orig = array_uintersect( $orig_schedule[$key], $in_ex[$key], '_eventorganiser_compare_dates' ) ){
				$in_ex[$key] = array_merge( $orig, $in_ex[$key] );
				$in_ex[$key] = _eventorganiser_remove_duplicates( $in_ex[$key] );
			}*/
		}
	endforeach;
	
	$event_data = array(
		'start'         => $start,
		'end'           => $end,
		'all_day'       => $all_day,
		'schedule'      => $schedule,
		'frequency'     => (int) $raw_data['event_frequency'],
		'until'         => $until,
		'schedule_meta' => $schedule_meta,
		'occurs_by'     => $occurs_by,
		'include'       => $in_ex['include'],
		'exclude'       => $in_ex['exclude'],
	);

	$response = eo_update_event( $post_id, $event_data );	

	if ( is_wp_error( $response ) ){
		global $EO_Errors;
		$code = $response->get_error_code();
		$message = $response->get_error_message( $code );
		$errors[$post_id][] = __( 'Event dates were not saved.', 'eventorganiser' );
		$errors[$post_id][] = $message;
		$EO_Errors->add( 'eo_error',$message );
		update_option( 'eo_notice', $errors );
	}

	return;
}
add_action( 'save_post', 'eventorganiser_details_save' );

/**
 * Display custom error or alert messages on events CPT page
 *
 * @ignore
 * @since 1.0.0
 */
add_action( 'admin_notices', '_eventorganiser_event_edit_admin_notice', 0 );
function _eventorganiser_event_edit_admin_notice(){

	global $post;

	$notice = get_option( 'eo_notice' );
	if ( empty( $notice ) || empty( $post->ID )  ) return '';

	foreach ( $notice as $pid => $messages ){
		if ( $post->ID == $pid ){
			printf( '<div id="message" class="error"> <p> %s </p> </div>', implode( ' </p> <p> ', $messages ) );

			//make sure to remove notice after its displayed so its only displayed when needed.
			unset( $notice[0] );
			unset( $notice[$pid] );
			update_option( 'eo_notice', $notice );
        	}
	}	
}
