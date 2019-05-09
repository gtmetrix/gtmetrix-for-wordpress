<?php
/*
  Plugin Name: GTmetrix for WordPress
  Plugin URI: https://gtmetrix.com/gtmetrix-for-wordpress-plugin.html
  Description: GTmetrix can help you develop a faster, more efficient, and all-around improved website experience for your users. Your users will love you for it.
  Version: 0.4.4
  Author: GTmetrix
  Author URI: https://gtmetrix.com/

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

class GTmetrix_For_WordPress {

    public function __construct() {

        include_once(dirname( __FILE__ ) . '/widget.php');

        register_activation_hook( __FILE__, array( &$this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( &$this, 'deactivate' ) );
        add_action( 'init', array( &$this, 'register_post_types' ) );
        add_action( 'admin_init', array( &$this, 'register_settings' ) );
        add_action( 'admin_init', array( &$this, 'system_check' ), 0 );
        add_action( 'admin_menu', array( &$this, 'add_menu_items' ) );
        add_action( 'admin_print_styles', array( &$this, 'admin_styles' ) );
        add_action( 'admin_notices', array( &$this, 'admin_notices' ) );
        add_action( 'admin_bar_menu', array( &$this, 'add_to_toolbar' ), 999 );
        add_action( 'wp_dashboard_setup', array( &$this, 'add_dashboard_widget' ) );
        add_action( 'gfw_hourly_event', array( &$this, 'scheduled_events' ) );
        add_action( 'gfw_daily_event', array( &$this, 'scheduled_events' ) );
        add_action( 'gfw_weekly_event', array( &$this, 'scheduled_events' ) );
        add_action( 'gfw_monthly_event', array( &$this, 'scheduled_events' ) );
        add_action( 'wp_ajax_autocomplete', array( &$this, 'autocomplete_callback' ) );
        add_action( 'wp_ajax_save_report', array( &$this, 'save_report_callback' ) );
        add_action( 'wp_ajax_expand_report', array( &$this, 'expand_report_callback' ) );
        add_action( 'wp_ajax_report_graph', array( &$this, 'report_graph_callback' ) );
        add_action( 'wp_ajax_reset', array( &$this, 'reset_callback' ) );
        add_action( 'widgets_init', array( &$this, 'gfw_widget_init' ) );
        add_filter( 'cron_schedules', array( &$this, 'add_intervals' ) );
        add_filter( 'plugin_row_meta', array( &$this, 'plugin_links' ), 10, 2 );

        $options = get_option( 'gfw_options' );
        define( 'GFW_WP_VERSION', '3.3.1' );
        define( 'GFW_VERSION', '0.4.2' );
        define( 'GFW_USER_AGENT', 'GTmetrix_WordPress/' . GFW_VERSION . ' (+https://gtmetrix.com/gtmetrix-for-wordpress-plugin.html)' );
        define( 'GFW_TIMEZONE', get_option( 'timezone_string' ) ? get_option( 'timezone_string' ) : date_default_timezone_get() );
        define( 'GFW_AUTHORIZED', isset( $options['authorized'] ) && $options['authorized'] ? true : false );
        define( 'GFW_URL', plugins_url( '/', __FILE__ ) );
        define( 'GFW_TESTS', get_admin_url() . 'admin.php?page=gfw_tests' );
        define( 'GFW_SETTINGS', get_admin_url() . 'admin.php?page=gfw_settings' );
        define( 'GFW_SCHEDULE', get_admin_url() . 'admin.php?page=gfw_schedule' );
        define( 'GFW_TRIES', 3 );
        define( 'GFW_FRONT', isset( $options['front_url'] ) && 'site' == $options['front_url'] ? get_home_url( null, '' ) : get_site_url( null, '' ) );
        define( 'GFW_GA_CAMPAIGN', '?utm_source=wordpress&utm_medium=GTmetrix-v' . GFW_VERSION . '&utm_campaign=' . urlencode(get_option('blogname')) );
    }

    public function add_to_toolbar( $wp_admin_bar ) {
        $options = get_option( 'gfw_options' );
        if ( GFW_AUTHORIZED && !is_admin() && current_user_can( 'access_gtmetrix' ) && isset( $options['toolbar_link'] ) && $options['toolbar_link'] ) {
            $wp_admin_bar->add_node( array(
                'id' => 'gfw',
                'title' => 'GTmetrix',
            ) );
            $wp_admin_bar->add_menu( array(
                'parent' => 'gfw',
                'id' => 'gfw-test',
                'title' => 'Test this page',
                'href' => GFW_TESTS . '&url=' . $_SERVER['REQUEST_URI']
            ) );
        }
    }

    public function activate() {
        wp_schedule_event( mktime( date( 'H' ) + 1, 0, 0 ), 'hourly', 'gfw_hourly_event', array( 'hourly' ) );
        wp_schedule_event( mktime( date( 'H' ) + 1, 0, 0 ), 'daily', 'gfw_daily_event', array( 'daily' ) );
        wp_schedule_event( mktime( date( 'H' ) + 1, 0, 0 ), 'weekly', 'gfw_weekly_event', array( 'weekly' ) );
        wp_schedule_event( mktime( date( 'H' ) + 1, 0, 0 ), 'monthly', 'gfw_monthly_event', array( 'monthly' ) );

        $role = get_role( 'administrator' );
        $role->add_cap( 'access_gtmetrix' );

        $options = get_option( 'gfw_options' );
        $options['widget_pagespeed'] = isset( $options['widget_pagespeed'] ) ? $options['widget_pagespeed'] : 1;
        $options['widget_yslow'] = isset( $options['widget_yslow'] ) ? $options['widget_yslow'] : 1;
        $options['widget_scores'] = isset( $options['widget_scores'] ) ? $options['widget_scores'] : 1;
        $options['widget_link'] = isset( $options['widget_link'] ) ? $options['widget_link'] : 1;
        $options['widget_css'] = isset( $options['widget_css'] ) ? $options['widget_css'] : 1;
        $options['front_url'] = isset( $options['front_url'] ) ? $options['front_url'] : 'wp';
        update_option( 'gfw_options', $options );
    }

    public function deactivate() {
        wp_clear_scheduled_hook( 'gfw_hourly_event', array( 'hourly' ) );
        wp_clear_scheduled_hook( 'gfw_daily_event', array( 'daily' ) );
        wp_clear_scheduled_hook( 'gfw_weekly_event', array( 'weekly' ) );
        wp_clear_scheduled_hook( 'gfw_monthly_event', array( 'monthly' ) );
    }

    public function system_check() {
        global $wp_version;
        $plugin = plugin_basename( __FILE__ );
        if ( is_plugin_active( $plugin ) ) {
            if ( version_compare( $wp_version, GFW_WP_VERSION, '<' ) ) {
                $message = '<p>GTmetrix for WordPress requires WordPress ' . GFW_WP_VERSION . ' or higher. ';
            } elseif ( !function_exists( 'curl_init' ) ) {
                $message = '<p>GTmetrix for WordPress requires cURL to be enabled. ';
            }
            if ( isset( $message ) ) {
                deactivate_plugins( $plugin );
                wp_die( $message . 'Deactivating Plugin.</p><p>Back to <a href="' . admin_url() . '">WordPress admin</a>.</p>' );
            }
        }
    }

    public function plugin_links( $links, $file ) {
        if ( $file == plugin_basename( __FILE__ ) ) {
            return array_merge( $links, array( sprintf( '<a href="%1$s">%2$s</a>', GFW_SETTINGS, 'Settings' ) ) );
        }
        return $links;
    }

    public function add_intervals( $schedules ) {
        $schedules['hourly'] = array( 'interval' => 3600, 'display' => 'Hourly' );
        $schedules['weekly'] = array( 'interval' => 604800, 'display' => 'Weekly' );
        $schedules['monthly'] = array( 'interval' => 2635200, 'display' => 'Monthly' );
        return $schedules;
    }

    public function scheduled_events( $recurrence ) {
        if ( GFW_AUTHORIZED ) {
            $args = array(
                'post_type' => 'gfw_event',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'gfw_recurrence',
                        'value' => $recurrence
                    ),
                ),
            );
            $query = new WP_Query( $args );
            while ( $query->have_posts() ) {
                $query->next_post();
                $event_id = $query->post->ID;
                $event_custom = get_post_custom( $event_id );
// As well as testing those events with a gfw_status of 1, we also need to test where gfw_status does not exist (those set pre version 0.4)
                if ( !isset( $event_custom['gfw_status'][0] ) || (isset( $event_custom['gfw_status'][0] ) && (1 == $event_custom['gfw_status'][0])) ) {

                    $parameters = array( );
                    foreach ( $event_custom as $meta_key => $meta_value ) {
                        $parameters[$meta_key] = $meta_value[0];
                    }
                    $report = $this->run_test( $parameters );
                    $last_report_id = $this->save_report( array_merge( $parameters, $report ), $event_id );

                    date_default_timezone_set( GFW_TIMEZONE );
                    update_post_meta( $event_id, 'gfw_last_report', date( 'Y-m-d H:i:s' ) );
                    update_post_meta( $event_id, 'gfw_last_report_id', $last_report_id );
                    if ( isset( $report['error'] ) ) {
                        $gfw_event_error = get_post_meta( $event_id, 'gfw_event_error', true );
                        if ( GFW_TRIES == ++$gfw_event_error ) {
                            update_post_meta( $event_id, 'gfw_status', 3 );
                        }
                        update_post_meta( $event_id, 'gfw_event_error', $gfw_event_error );
                    } else {
                        update_post_meta( $event_id, 'gfw_event_error', 0 );
                    }


                    if ( isset( $event_custom['gfw_notifications'] ) && !isset( $report['error'] ) ) {
                        $email_content = array( );
                        foreach ( unserialize( $event_custom['gfw_notifications'][0] ) as $key => $value ) {
                            switch ( $key ) {
                                case 'pagespeed_score':
                                    if ( $report[$key] < $value ) {
                                        $pagespeed_grade_condition = $this->score_to_grade( $value );
                                        $pagespeed_grade = $this->score_to_grade( $report[$key] );
                                        $email_content[] = '<p>The PageSpeed grade has fallen below ' . $pagespeed_grade_condition['grade'] . '.</p><p><span style="font-size:12px; color:#666666; font-style:italic">The URL is currently scoring ' . $pagespeed_grade['grade'] . ' (' . $report[$key] . '%).</p>';
                                    }
                                    break;
                                case 'yslow_score':
                                    if ( $report[$key] < $value ) {
                                        $yslow_grade_condition = $this->score_to_grade( $value );
                                        $yslow_grade = $this->score_to_grade( $report[$key] );
                                        $email_content[] = '<p>The YSlow grade has fallen below ' . $yslow_grade_condition['grade'] . '.</p><p><span style="font-size:12px; color:#666666; font-style:italic">The URL is currently scoring ' . $yslow_grade['grade'] . ' (' . $report[$key] . '%).</p>';
                                    }
                                    break;
                                case 'page_load_time':
                                    if ( $report[$key] > $value ) {
                                        $email_content[] = '<p>The total page load time has climbed above  ' . $value / 1000 . ' seconds.</p><p><span style="font-size:12px; color:#666666; font-style:italic">The URL is currently taking ' . number_format( (( int ) $report[$key]) / 1000, 2 ) . ' seconds.</p>';
                                    }
                                    break;
                                case 'page_bytes':
                                    if ( $report[$key] > $value ) {
                                        $email_content[] = '<p>The total page size has climbed above  ' . size_format( $value, 2 ) . '.</p><p><span style="font-size:12px; color:#666666; font-style:italic">The URL is currently ' . size_format( $report[$key], 2 ) . '.</p>';
                                    }
                                    break;
                            }
                        }

                        if ( !empty( $email_content ) ) {
                            $message_date = date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
                            $settings = admin_url() . 'admin.php?page=gfw_schedule';

                            $message = <<<HERE
<table width="100%" cellpadding="10" cellspacing="0" bgcolor="#ececec">
    <tr>
        <td valign="top" align="center">
            <table width="550" cellpadding="0" cellspacing="0">
                <tr>
                    <td>
                        <a href="https://gtmetrix.com/"><img src="https://gtmetrix.com/static/images/email-header.png" width="550" height="107" border="0" alt="Analyze your site at GTmetrix" /></a>
                    </td>
                </tr>
            </table>
            <table width="550" cellpadding="20" cellspacing="0" bgcolor="#ffffff" style="font-size:12px; color:#000000; line-height:150%; font-family:Arial">
                <tr>
                    <td valign="top">
                        <p>
                            <span style="font-size:20px; font-weight:bold; color:#6397cb; line-height:110%">GTmetrix for WordPress Notification</span><br />
                        </p>
                        <table width="510" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="font-size:12px;" >
                            <tr>
                                <td valign="top">
                                    <img src="{$report['report_url']}/screenshot.jpg" style="margin-right: 10px;" />
                                </td>
                                <td valign="top">
                                    <p><span style="font-size:12px; color:#666666; font-style:italic">{$parameters['gfw_url']}</span></p>
                                    <p>$message_date</p>
                                    <hr style="border:0; border-top:1px solid #d7d7d7; height: 0;" />
HERE;
                            $message .= implode( $email_content );
                            $message .= <<<HERE
                                <p><a href="{$report['report_url']}">View detailed report</a></p>
                                </td>
                                </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td>
                        <hr style="border:0; border-top:1px solid #d7d7d7; height: 0;" />
                        <p style="font-size:11px; color:#8a8a8a; line-height:100%;">This email was sent to you by the GTmetrix for WordPress plugin. You can opt out of further alerts by modifying the plugin's <a href="$settings">settings</a> on your WordPress installation.</p>
                    </td>
                </tr>
                <tr>
                <tr>
                    <td style="border-top:30px solid #4d88c3;" valign="top">
                        <span style="font-size:11px; color:#8a8a8a; line-height:100%;">Copyright (c) 2012 GTmetrix. All rights reserved.</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
HERE;
                            $options = get_option( 'gfw_options' );
                            add_filter( 'wp_mail_content_type', create_function( '', 'return "text/html";' ) );
                            wp_mail( 'admin_email' == $options['notifications_email'] ? get_option( 'admin_email' ) : $options['api_username'], 'GTmetrix for WordPress notification from ' . get_home_url( null, '', 'http' ), $message );
                        }
                    }
                }
            }
        }
    }

    public function add_dashboard_widget() {
        $options = get_option( 'gfw_options' );
        if ( isset( $options['dashboard_widget'] ) && $options['dashboard_widget'] && GFW_AUTHORIZED && current_user_can( 'access_gtmetrix' ) ) {
            wp_add_dashboard_widget( 'gfw_dashboard_widget', 'GTmetrix for WordPress Latest Front Page Score', array( &$this, 'dashboard_widget' ) );
        }
    }

    public function dashboard_widget() {
        $this->front_score( true );
    }

    public function add_menu_items() {
        if ( GFW_AUTHORIZED ) {
            add_menu_page( 'GTmetrix', 'GTmetrix', 'access_gtmetrix', 'gfw_tests', array( $this, 'tests_page' ), 'none' );
            $this->tests_page_hook = add_submenu_page( 'gfw_tests', 'Tests', 'Tests', 'access_gtmetrix', 'gfw_tests', array( $this, 'tests_page' ) );
            $this->schedule_page_hook = add_submenu_page( 'gfw_tests', 'Schedule', 'Schedule', 'access_gtmetrix', 'gfw_schedule', array( $this, 'schedule_page' ) );
            $this->settings_page_hook = add_submenu_page( 'gfw_tests', 'Settings', 'Settings', 'access_gtmetrix', 'gfw_settings', array( $this, 'settings_page' ) );
            add_action( 'load-' . $this->tests_page_hook, array( &$this, 'page_loading' ) );
            add_action( 'load-' . $this->schedule_page_hook, array( &$this, 'page_loading' ) );
        } else {
            $this->settings_page_hook = add_menu_page( 'GTmetrix', 'GTmetrix', 'access_gtmetrix', 'gfw_settings', array( $this, 'settings_page' ), 'none' );
        }
        add_action( 'load-' . $this->settings_page_hook, array( &$this, 'page_loading' ) );
    }

    public function admin_notices() {
        if ( !GFW_AUTHORIZED ) {
            echo $this->set_notice( '<strong>GTmetrix for WordPress is almost ready.</strong> You must <a href="' . GFW_SETTINGS . '">enter your GTmetrix API key</a> for it to work.' );
        }

        $notice = get_transient( 'admin_notice' );
        if ( $notice ) {
            echo $this->set_notice( $notice );
            delete_transient( 'admin_notice' );
        }
    }

    public function register_settings() {
        register_setting( 'gfw_options_group', 'gfw_options', array( &$this, 'sanitize_settings' ) );
        add_settings_section( 'authentication_section', '', array( &$this, 'section_text' ), 'gfw_settings' );
        add_settings_field( 'api_username', 'GTmetrix Account Email', array( &$this, 'set_api_username' ), 'gfw_settings', 'authentication_section' );
        add_settings_field( 'api_key', 'API Key', array( &$this, 'set_api_key' ), 'gfw_settings', 'authentication_section' );
        if ( GFW_AUTHORIZED ) {
            add_settings_section( 'options_section', '', array( &$this, 'section_text' ), 'gfw_settings' );
            add_settings_field( 'dashboard_widget', 'Show dashboard widget', array( &$this, 'set_dashboard_widget' ), 'gfw_settings', 'options_section' );
            add_settings_field( 'toolbar_link', 'Show GTmetrix on Toolbar', array( &$this, 'set_toolbar_link' ), 'gfw_settings', 'options_section' );
            add_settings_field( 'default_adblock', 'Default Adblock status', array( &$this, 'set_default_adblock' ), 'gfw_settings', 'options_section' );
            add_settings_field( 'default_location', 'Default location', array( &$this, 'set_default_location' ), 'gfw_settings', 'options_section' );
            add_settings_field( 'notifications_email', 'Alerts Email', array( &$this, 'set_notifications_email' ), 'gfw_settings', 'options_section' );
            add_settings_field( 'front_url', 'Front page URL', array( &$this, 'set_front_url' ), 'gfw_settings', 'options_section' );
            add_settings_section( 'widget_section', '', array( &$this, 'section_text' ), 'gfw_settings' );
            add_settings_field( 'widget_pagespeed', 'Show PageSpeed grade', array( &$this, 'set_widget_pagespeed' ), 'gfw_settings', 'widget_section' );
            add_settings_field( 'widget_yslow', 'Show YSlow grade', array( &$this, 'set_widget_yslow' ), 'gfw_settings', 'widget_section' );
            add_settings_field( 'widget_scores', 'Show scores (percentages)', array( &$this, 'set_widget_scores' ), 'gfw_settings', 'widget_section' );
            add_settings_field( 'widget_link', 'Show link to GTmetrix', array( &$this, 'set_widget_link' ), 'gfw_settings', 'widget_section' );
            add_settings_field( 'widget_css', 'Use GTmetrix CSS', array( &$this, 'set_widget_css' ), 'gfw_settings', 'widget_section' );
            add_settings_section( 'reset_section', '', array( &$this, 'section_text' ), 'gfw_settings' );
            add_settings_field( 'reset', 'Reset', array( &$this, 'set_reset' ), 'gfw_settings', 'reset_section' );
        }
    }

    public function set_api_username() {
        $options = get_option( 'gfw_options' );
        echo '<input type="text" name="gfw_options[api_username]" id="api_username" value="' . (isset( $options['api_username'] ) ? $options['api_username'] : '') . '" />';
    }

    public function set_api_key() {
        $options = get_option( 'gfw_options' );
        echo '<input type="text" name="gfw_options[api_key]" id="api_key" value="' . (isset( $options['api_key'] ) ? $options['api_key'] : '') . '" />';
    }

    public function set_default_location() {
        $options = get_option( 'gfw_options' );
        echo '<p><select name="gfw_options[default_location]" id="default_location">';
        foreach ( $options['locations'] as $location ) {
            echo '<option value="' . $location['id'] . '" ' . selected( $options['default_location'], $location['id'], false ) . '>' . $location['name'] . '</option>';
        }
        echo '</select><br /><span class="description">Test Server Region (scheduled tests will override this setting)</span></p>';
    }

    public function set_notifications_email() {
        $options = get_option( 'gfw_options' );
        echo '<p><select name="gfw_options[notifications_email]" id="notifications_email">';
        foreach ( array( 'api_username' => 'GTmetrix email (' . $options['api_username'] . ')', 'admin_email' => 'Admin email (' . get_option( 'admin_email' ) . ')' ) as $key => $value ) {
            echo '<option value="' . $key . '" ' . selected( $options['notifications_email'], $key, false ) . '>' . $value . '</option>';
        }
        echo '</select></p>';
    }

    public function set_dashboard_widget() {
        $options = get_option( 'gfw_options' );
        echo '<input type="hidden" name="gfw_options[dashboard_widget]" value="0" />';
        echo '<input type="checkbox" name="gfw_options[dashboard_widget]" id="dashboard_widget" value="1" ' . checked( $options['dashboard_widget'], 1, false ) . ' />';
    }

    public function set_toolbar_link() {
        $options = get_option( 'gfw_options' );
        $options['toolbar_link'] = isset( $options['toolbar_link'] ) ? $options['toolbar_link'] : 0;
        echo '<input type="hidden" name="gfw_options[toolbar_link]" value="0" />';
        echo '<input type="checkbox" name="gfw_options[toolbar_link]" id="toolbar_link" value="1" ' . checked( $options['toolbar_link'], 1, false ) . ' />';
    }

    public function set_default_adblock() {
        $options = get_option( 'gfw_options' );
        echo '<input type="hidden" name="gfw_options[default_adblock]" value="0" />';
        echo '<input type="checkbox" name="gfw_options[default_adblock]" id="default_adblock" value="1" ' . checked( $options['default_adblock'], 1, false ) . ' /> <span class="description">Turning on AdBlock can help you see the difference Ad networks make on your blog</span>';
    }

    public function set_front_url() {
        $options = get_option( 'gfw_options' );
        echo '<p><select name="gfw_options[front_url]" id="front_url">';
        foreach ( array( 'wp' => 'WordPress Address (' . site_url() . ')', 'site' => 'Site Address (' . home_url() . ')' ) as $key => $value ) {
            echo '<option value="' . $key . '" ' . selected( $options['front_url'], $key, false ) . '>' . $value . '</option>';
        }
        echo '</select></p>';
    }

    public function set_reset() {
        echo '<p class="description">This will flush all GTmetrix records from the WordPress database!</p>';
        echo '<input type="button" value="Reset" class="button-primary" id="gfw-reset" />';
    }

    public function set_widget_pagespeed() {
        $options = get_option( 'gfw_options' );
        echo '<input type="hidden" name="gfw_options[widget_pagespeed]" value="0" />';
        echo '<input type="checkbox" name="gfw_options[widget_pagespeed]" id="widget_pagespeed" value="1" ' . checked( $options['widget_pagespeed'], 1, false ) . ' />';
    }

    public function set_widget_yslow() {
        $options = get_option( 'gfw_options' );
        echo '<input type="hidden" name="gfw_options[widget_yslow]" value="0" />';
        echo '<input type="checkbox" name="gfw_options[widget_yslow]" id="widget_yslow" value="1" ' . checked( $options['widget_yslow'], 1, false ) . ' />';
    }

    public function set_widget_scores() {
        $options = get_option( 'gfw_options' );
        echo '<input type="hidden" name="gfw_options[widget_scores]" value="0" />';
        echo '<input type="checkbox" name="gfw_options[widget_scores]" id="widget_scores" value="1" ' . checked( $options['widget_scores'], 1, false ) . ' />';
    }

    public function set_widget_link() {
        $options = get_option( 'gfw_options' );
        echo '<input type="hidden" name="gfw_options[widget_link]" value="0" />';
        echo '<input type="checkbox" name="gfw_options[widget_link]" id="widget_link" value="1" ' . checked( $options['widget_link'], 1, false ) . ' />';
    }

    public function set_widget_css() {
        $options = get_option( 'gfw_options' );
        echo '<input type="hidden" name="gfw_options[widget_css]" value="0" />';
        echo '<input type="checkbox" name="gfw_options[widget_css]" id="widget_css" value="1" ' . checked( $options['widget_css'], 1, false ) . ' />';
    }

    public function section_text() {
        // Placeholder for settings section (which is required for some reason)
    }

    public function page_loading() {
        $screen = get_current_screen();
        wp_enqueue_script( 'common' );
        wp_enqueue_script( 'wp-lists' );
        wp_enqueue_script( 'postbox' );
        wp_enqueue_script( 'jquery-ui-tooltip' );
        wp_enqueue_script( 'gfw-script', GFW_URL . 'gtmetrix-for-wordpress.js', array( 'jquery-ui-autocomplete', 'jquery-ui-dialog' ), GFW_VERSION, true );
        wp_localize_script( 'gfw-script', 'gfwObject', array( 'gfwnonce' => wp_create_nonce( 'gfwnonce' ) ) );

        if ( GFW_AUTHORIZED ) {
            add_meta_box( 'gfw-credits-meta-box', 'API Credits', array( &$this, 'credits_meta_box' ), $this->tests_page_hook, 'side', 'core' );
            add_meta_box( 'gfw-credits-meta-box', 'API Credits', array( &$this, 'credits_meta_box' ), $this->schedule_page_hook, 'side', 'core' );
            add_meta_box( 'gfw-optimization-meta-box', 'Need optimization help?', array( &$this, 'optimization_meta_box' ), $this->tests_page_hook, 'side', 'core' );
            add_meta_box( 'gfw-optimization-meta-box', 'Need optimization help?', array( &$this, 'optimization_meta_box' ), $this->schedule_page_hook, 'side', 'core' );
            add_meta_box( 'gfw-news-meta-box', 'Latest News', array( &$this, 'news_meta_box' ), $this->tests_page_hook, 'side', 'core' );
            add_meta_box( 'gfw-news-meta-box', 'Latest News', array( &$this, 'news_meta_box' ), $this->schedule_page_hook, 'side', 'core' );
        }
        add_meta_box( 'gfw-optimization-meta-box', 'Need optimization help?', array( &$this, 'optimization_meta_box' ), $this->settings_page_hook, 'side', 'core' );
        add_meta_box( 'gfw-news-meta-box', 'Latest News', array( &$this, 'news_meta_box' ), $this->settings_page_hook, 'side', 'core' );

        if ( method_exists( $screen, 'add_help_tab' ) ) {
            $settings_help = '<p>You will need an account at <a href="https://gtmetrix.com/' . GFW_GA_CAMPAIGN . '" target="_blank">Gtmetrix.com</a> to use GTmetrix for WordPress. Registration is free. Once registered, go to the <a href="https://gtmetrix.com/api/' . GFW_GA_CAMPAIGN . '" target="_blank">API page</a> and generate an API key. Enter this key, along with your registered email address, in the authentication fields below, and you\'re ready to go!</p>';
            $options_help = '<p>You would usually set your <i>default location</i> to the city nearest to your target audience. When you run a test on a URL, the report returned will reflect the experience of a user connecting from this location.</p>';

            $test_help = '<p>To analyze the performance of a page or post on your blog, simply enter it\'s URL. You can even just start to type the title into the box, and an autocomplete facility will try and help you out.</p>';
            $test_help .= '<p>The optional <i>Label</i> is simply used to help you identify a given report in the system.</p>';

            $reports_help = '<p>The Reports section shows summaries of your reports. For even more detailed information, click on the Report\'s URL/label, and the full GTmetrix.com report will open. You can also delete a report.</p>';
            $reports_help .= '<p><b>Note:</b> deleting a report here only removes it from GTmetrix for WordPress - not from your GTmetrix account.<br /><b>Note:</b> if the URL/label is not a link, this means the report is no longer available on GTmetrix.com.</p>';

            $schedule_help = '<p>You can set up your reports to be generated even when you\'re away. Simply run the report as normal (in Reports), then expand the report\'s listing, and click <i>Schedule tests</i>. You will be redirected to this page, where you can choose how often you want this report to run.</p>';
            $schedule_help .= '<p>You can also choose to be sent an email when certain conditions apply. This email can go to either your admin email address or your GTmetrix email address, as defined in settings.</p>';
            $schedule_help .= '<p><b>Note:</b> every test will use up 1 of your API credits on GTmetrix.com<br /><b>Note:</b> scheduled tests use the WP-Cron functionality that is built into WordPress. This means that events are only triggered when your site is visited.</p>';

            switch ( $screen->id ) {

                case 'toplevel_page_gfw_settings':
                case 'gtmetrix_page_gfw_settings':
                    $screen->add_help_tab(
                            array(
                                'title' => 'Authentication',
                                'id' => 'authentication_help_tab',
                                'content' => $settings_help
                            )
                    );
                    $screen->add_help_tab(
                            array(
                                'title' => 'Options',
                                'id' => 'options_help_tab',
                                'content' => $options_help
                            )
                    );
                    break;

                case 'toplevel_page_gfw_tests':
                    wp_enqueue_style( 'smoothness', GFW_URL . 'lib/smoothness/jquery-ui-1.10.2.custom.min.css', GFW_VERSION );
                    $screen->add_help_tab(
                            array(
                                'title' => 'Test',
                                'id' => 'test_help_tab',
                                'content' => $test_help
                            )
                    );
                    $screen->add_help_tab(
                            array(
                                'title' => 'Reports',
                                'id' => 'reports_help_tab',
                                'content' => $reports_help
                            )
                    );
                    break;

                case 'gtmetrix_page_gfw_schedule':
                    wp_enqueue_style( 'smoothness', GFW_URL . 'lib/smoothness/jquery-ui-1.10.2.custom.min.css', GFW_VERSION );
                    wp_enqueue_script( 'flot', GFW_URL . 'lib/flot/jquery.flot.min.js', 'jquery' );
                    wp_enqueue_script( 'flot.resize', GFW_URL . 'lib/flot/jquery.flot.resize.min.js', 'flot' );
                    $screen->add_help_tab(
                            array(
                                'title' => 'Schedule a Test',
                                'id' => 'schedule_help_tab',
                                'content' => $schedule_help
                            )
                    );
                    break;
            }

            $screen->set_help_sidebar( '<p><strong>For more information:</strong></p><p><a href="https://gtmetrix.com/wordpress-optimization-guide.html' . GFW_GA_CAMPAIGN . '" target="_blank">GTmetrix Wordpress Optimization Guide</a></p>' );
        }
    }

    public function schedule_page() {

        global $screen_layout_columns;
        $report_id = isset( $_GET['report_id'] ) ? $_GET['report_id'] : 0;
        $event_id = isset( $_GET['event_id'] ) ? $_GET['event_id'] : 0;
        $delete = isset( $_GET['delete'] ) ? $_GET['delete'] : 0;
        $status = isset( $_GET['status'] ) ? $_GET['status'] : 0;


        if ( 'POST' == $_SERVER['REQUEST_METHOD'] ) {
            $data = $_POST;

            if ( $data['report_id'] ) {

                $custom_fields = get_post_custom( $data['report_id'] );

                $event_id = wp_insert_post( array(
                    'post_type' => 'gfw_event',
                    'post_status' => 'publish',
                    'post_author' => 1
                        ) );

                update_post_meta( $event_id, 'gfw_url', $custom_fields['gfw_url'][0] );
                update_post_meta( $event_id, 'gfw_label', $custom_fields['gfw_label'][0] );
                update_post_meta( $event_id, 'gfw_location', 1 ); // restricted to Vancouver
                update_post_meta( $event_id, 'gfw_adblock', isset( $custom_fields['gfw_adblock'][0] ) ? $custom_fields['gfw_adblock'][0] : 0  );
                update_post_meta( $event_id, 'gfw_event_error', 0 );
            }

            $event_id = $data['event_id'] ? $data['event_id'] : $event_id;

            update_post_meta( $event_id, 'gfw_recurrence', $data['gfw_recurrence'] );
            update_post_meta( $event_id, 'gfw_status', $data['gfw_status'] );

            $notifications = array( );
            if ( isset( $data['gfw_condition'] ) ) {
                foreach ( $data['gfw_condition'] as $key => $value ) {
                    $notifications[$value] = $data[$value][$key];
                }
                update_post_meta( $event_id, 'gfw_notifications', $notifications );
            } else {
                delete_post_meta( $event_id, 'gfw_notifications' );
            }
            echo '<div id="message" class="updated"><p><strong>Schedule updated.</strong></p></div>';
        }

        if ( ($event_id || $report_id) && !isset( $data ) ) {
            add_meta_box( 'schedule-meta-box', 'Schedule a Test', array( &$this, 'schedule_meta_box' ), $this->schedule_page_hook, 'normal', 'core' );
        }

        if ( $delete ) {
            $args = array(
                'post_type' => 'gfw_report',
                'meta_key' => 'gfw_event_id',
                'meta_value' => $delete,
                'posts_per_page' => -1
            );

            $query = new WP_Query( $args );

            while ( $query->have_posts() ) {
                $query->next_post();
                wp_delete_post( $query->post->ID );
            }

            wp_delete_post( $delete );
            echo $this->set_notice( 'Event deleted' );
        }

        if ( $status ) {
            $gfw_status = get_post_meta( $status, 'gfw_status', true );
            if ( 1 == $gfw_status ) {
                update_post_meta( $status, 'gfw_status', 2 );
                echo $this->set_notice( 'Event paused' );
            } else {
                update_post_meta( $status, 'gfw_status', 1 );
                update_post_meta( $status, 'gfw_event_error', 0 );
                echo $this->set_notice( 'Event reactivated' );
            }
        }

        add_meta_box( 'events-meta-box', 'Scheduled Tests', array( &$this, 'events_list' ), $this->schedule_page_hook, 'normal', 'core' );
        ?>

        <div class="wrap gfw">
            <div id="gfw-icon" class="icon32"></div>
            <h2>GTmetrix for WordPress &raquo; Schedule</h2>
            <?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
            <?php wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); ?>
            <div id="poststuff" class="metabox-holder has-right-sidebar">
                <div id="side-info-column" class="inner-sidebar">
                    <?php do_meta_boxes( $this->schedule_page_hook, 'side', 0 ); ?>
                </div>
                <div id="post-body" class="has-sidebar">
                    <div id="post-body-content" class="has-sidebar-content">
                        <?php do_meta_boxes( $this->schedule_page_hook, 'normal', false ); ?>
                    </div>
                </div>
            </div>	
        </div>
        <?php
    }

    protected function set_notice( $message, $class = 'updated' ) {
        return '<div class="' . $class . '"><p>' . $message . '</p></div>';
    }

    public function tests_page() {
        $delete = isset( $_GET['delete'] ) ? $_GET['delete'] : 0;
        if ( $delete ) {
            wp_delete_post( $delete );
            echo $this->set_notice( 'Report deleted' );
        }

        global $screen_layout_columns;
        wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
        wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
        add_meta_box( 'gfw-score-meta-box', 'Latest Front Page Score', array( &$this, 'score_meta_box' ), $this->tests_page_hook, 'normal', 'core' );
        add_meta_box( 'gfw-test-meta-box', 'Test Performance of:', array( &$this, 'test_meta_box' ), $this->tests_page_hook, 'normal', 'core' );
        add_meta_box( 'gfw-reports-meta-box', 'Reports', array( &$this, 'reports_list' ), $this->tests_page_hook, 'normal', 'core' );
        ?>
        <div class="wrap gfw">
            <div id="gfw-icon" class="icon32"></div>
            <h2>GTmetrix for WordPress &raquo; Tests</h2>
            <div id="poststuff" class="metabox-holder has-right-sidebar">
                <div id="side-info-column" class="inner-sidebar">
                    <?php do_meta_boxes( $this->tests_page_hook, 'side', 0 ); ?>
                </div>
                <div id="post-body" class="has-sidebar">
                    <div id="post-body-content" class="has-sidebar-content">
                        <?php do_meta_boxes( $this->tests_page_hook, 'normal', 0 ); ?>
                    </div>
                </div>
            </div>	
        </form>
        <div id="gfw-confirm-delete" class="gfw-dialog" title="Delete this report?">
            <p>Are you sure you want to delete this report?</p>
        </div>
        <div id="gfw-video" class="gfw-dialog">
            <p class="description">To view the page load video at different speeds, use Chrome, Safari or IE9+.</p>
        </div>
        </div>
        <?php
    }

    public function settings_page() {
        global $screen_layout_columns;
        wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
        wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
        add_meta_box( 'authenticate-meta-box', 'Authentication', array( &$this, 'authenticate_meta_box' ), $this->settings_page_hook, 'normal', 'core' );
        if ( GFW_AUTHORIZED ) {
            add_meta_box( 'options-meta-box', 'Options', array( &$this, 'options_meta_box' ), $this->settings_page_hook, 'normal', 'core' );
            add_meta_box( 'widget-meta-box', 'Widget', array( &$this, 'widget_meta_box' ), $this->settings_page_hook, 'normal', 'core' );
            add_meta_box( 'reset-meta-box', 'Reset', array( &$this, 'reset_meta_box' ), $this->settings_page_hook, 'normal', 'core' );
        }
        ?>
        <div class="wrap gfw">
            <div id="gfw-icon" class="icon32"></div>
            <h2>GTmetrix for WordPress  &raquo; Settings</h2>
            <?php settings_errors( 'gfw_options', false ); ?>
            <div id="poststuff" class="metabox-holder has-right-sidebar">
                <div id="side-info-column" class="inner-sidebar">
                    <?php do_meta_boxes( $this->settings_page_hook, 'side', 0 ); ?>
                </div>
                <div id="post-body" class="has-sidebar">
                    <div id="post-body-content" class="has-sidebar-content">
                        <form method="post" action="options.php">
                            <?php
                            wp_nonce_field( 'update-options' );
                            settings_fields( 'gfw_options_group' );
                            do_meta_boxes( $this->settings_page_hook, 'normal', 0 );
                            submit_button( 'Save Changes', 'primary', 'submit', false );
                            ?>
                        </form>
                    </div>
                </div>
            </div>	
        </div>
        <?php
    }

    public function sanitize_settings( $input ) {

        $valid = array( );
        $valid['authorized'] = 0;

        $valid['api_username'] = sanitize_email( $input['api_username'] );
        $valid['api_key'] = $input['api_key'];
        if ( !is_email( $valid['api_username'] ) ) {
            if ( !get_settings_errors( 'gfw_options' ) ) {
                add_settings_error( 'gfw_options', 'api_error', 'GTmetrix Account Email must be a valid email address.' );
            }
        } else {

            if ( !class_exists( 'Services_WTF_Test' ) ) {
                require_once('lib/Services_WTF_Test.php');
            }
            $test = new Services_WTF_Test();
            $test->api_username( $valid['api_username'] );
            $test->api_password( $valid['api_key'] );
            $test->user_agent( GFW_USER_AGENT );
            $locations = $test->locations();

            if ( $test->error() ) {
                if ( !get_settings_errors( 'gfw_options' ) ) {
                    add_settings_error( 'gfw_options', 'api_error', $test->error() );
                }
            } else {
                foreach ( $locations as $location ) {
                    $valid['locations'][$location['id']] = $location;
                }
                $valid['authorized'] = 1;
                if ( !get_settings_errors( 'gfw_options' ) ) {
                    add_settings_error( 'gfw_options', 'settings_updated', 'Settings Saved. Please click on <a href="' . GFW_TESTS . '">Tests</a> to test your WordPress installation.', 'updated' );
                }
            }
        }
        $options = get_option( 'gfw_options' );
        $valid['default_location'] = isset( $input['default_location'] ) ? $input['default_location'] : (isset( $options['default_location'] ) ? $options['default_location'] : 1);
        $valid['default_adblock'] = isset( $input['default_adblock'] ) ? $input['default_adblock'] : (isset( $options['default_adblock'] ) ? $options['default_adblock'] : 0);
        $valid['dashboard_widget'] = isset( $input['dashboard_widget'] ) ? $input['dashboard_widget'] : (isset( $options['dashboard_widget'] ) ? $options['dashboard_widget'] : 1);
        $valid['toolbar_link'] = isset( $input['toolbar_link'] ) ? $input['toolbar_link'] : (isset( $options['toolbar_link'] ) ? $options['toolbar_link'] : 1);
        $valid['notifications_email'] = isset( $input['notifications_email'] ) ? $input['notifications_email'] : (isset( $options['notifications_email'] ) ? $options['notifications_email'] : 'api_username');

        $valid['widget_pagespeed'] = isset( $input['widget_pagespeed'] ) ? $input['widget_pagespeed'] : $options['widget_pagespeed'];
        $valid['widget_yslow'] = isset( $input['widget_yslow'] ) ? $input['widget_yslow'] : $options['widget_yslow'];
        $valid['widget_scores'] = isset( $input['widget_scores'] ) ? $input['widget_scores'] : $options['widget_scores'];
        $valid['widget_link'] = isset( $input['widget_link'] ) ? $input['widget_link'] : $options['widget_link'];
        $valid['widget_css'] = isset( $input['widget_css'] ) ? $input['widget_css'] : $options['widget_css'];
        $valid['front_url'] = isset( $input['front_url'] ) ? $input['front_url'] : $options['front_url'];
        return $valid;
    }

    public function admin_styles() {
        wp_enqueue_style( 'gfw-style', GFW_URL . 'gtmetrix-for-wordpress.css', array( ), GFW_VERSION );
    }

    public function register_post_types() {

        register_post_type( 'gfw_report', array(
            'label' => 'GFW Reports',
            'public' => false,
            'supports' => array( false ),
            'rewrite' => false,
            'can_export' => false
        ) );

        register_post_type( 'gfw_event', array(
            'label' => 'GFW Events',
            'public' => false,
            'supports' => array( false ),
            'rewrite' => false,
            'can_export' => false
        ) );
    }

    public function save_report( $data, $event_id = 0 ) {

        $post_id = wp_insert_post( array(
            'post_type' => 'gfw_report',
            'post_status' => 'publish',
            'post_author' => 1
                ) );

        update_post_meta( $post_id, 'gfw_url', $this->append_http( $data['gfw_url'] ) );
        update_post_meta( $post_id, 'gfw_label', $data['gfw_label'] );
        update_post_meta( $post_id, 'gfw_location', $data['gfw_location'] );
        update_post_meta( $post_id, 'gfw_adblock', isset( $data['gfw_adblock'] ) ? $data['gfw_adblock'] : 0  );
        update_post_meta( $post_id, 'gfw_video', isset( $data['gfw_video'] ) ? $data['gfw_video'] : 0  );
        update_post_meta( $post_id, 'gfw_event_id', $event_id );

        if ( !isset( $data['error'] ) ) {
            update_post_meta( $post_id, 'gtmetrix_test_id', $data['test_id'] );
            update_post_meta( $post_id, 'page_load_time', $data['page_load_time'] );
            update_post_meta( $post_id, 'fully_loaded_time', $data['fully_loaded_time'] );
            update_post_meta( $post_id, 'html_bytes', $data['html_bytes'] );
            update_post_meta( $post_id, 'page_elements', $data['page_elements'] );
            update_post_meta( $post_id, 'report_url', $data['report_url'] );
            update_post_meta( $post_id, 'html_load_time', $data['html_load_time'] );
            update_post_meta( $post_id, 'page_bytes', $data['page_bytes'] );
            update_post_meta( $post_id, 'pagespeed_score', $data['pagespeed_score'] );
            update_post_meta( $post_id, 'yslow_score', $data['yslow_score'] );
        } else {
            update_post_meta( $post_id, 'gtmetrix_test_id', 0 );
            update_post_meta( $post_id, 'gtmetrix_error', $data['error'] );
        }
        return $post_id;
    }

    protected function run_test( $parameters ) {

        $api = $this->api();
        $response = array( );
        delete_transient( 'credit_status' );

        $test_id = $api->test( array(
            'url' => $this->append_http( $parameters['gfw_url'] ),
            'location' => $parameters['gfw_location'],
            'x-metrix-adblock' => isset( $parameters['gfw_adblock'] ) ? $parameters['gfw_adblock'] : 0,
            'x-metrix-video' => isset( $parameters['gfw_video'] ) ? $parameters['gfw_video'] : 0,
                ) );

        if ( $api->error() ) {
            $response['error'] = $api->error();
            return $response;
        }

        $api->get_results();

        if ( $api->error() ) {
            $response['error'] = $api->error();
            return $response;
        }

        if ( $api->completed() ) {
            $response['test_id'] = $test_id;
            return array_merge( $response, $api->results() );
        }
    }

    public function save_report_callback() {
        if ( check_ajax_referer( 'gfwnonce', 'security' ) ) {
            $fields = array( );
            parse_str( $_POST['fields'], $fields );
            $report = $this->run_test( $fields );
            if ( isset( $report['error'] ) ) {
                $response = json_encode( array( 'error' => $this->translate_message( $report['error'] ) ) );
            } else {
                $this->save_report( array_merge( $fields, $report ) );
                set_transient( 'admin_notice', 'Test complete' );
                $response = json_encode( array(
                    'screenshot' => $report['report_url'] . '/screenshot.jpg'
                        ) );
            }
            echo $response;
        }
        die();
    }

    public function autocomplete_callback() {
        $args['s'] = stripslashes( $_GET['term'] );
        $args['pagenum'] = !empty( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
        require(ABSPATH . WPINC . '/class-wp-editor.php');
        $results = _WP_Editors::wp_link_query( $args );
        echo json_encode( $results ) . "\n";
        die();
    }

    public function expand_report_callback() {
        $post = get_post( $_POST['id'] );

        if ( 'gfw_report' == $post->post_type ) {
            $report_id = $post->ID;
        } else {

            $args = array(
                'post_type' => 'gfw_report',
                'posts_per_page' => 1,
                'orderby' => 'post_date',
                'order' => 'DESC',
                'meta_query' => array(
                    array(
                        'key' => 'gfw_event_id',
                        'value' => $post->ID
                    ),
                    array(
                        'key' => 'gtmetrix_test_id',
                        'value' => 0,
                        'compare' => '!='
                    )
                ),
            );
            $query = new WP_Query( $args );
            $report_id = ($query->post_count ? $query->post->ID : 0);
        }

        echo '<div class="gfw-expansion">';
        echo '<div class="gfw-expansion-right">';
        if ( $report_id ) {
            $report = get_post( $report_id );
            $custom_fields = get_post_custom( $report->ID );

            $loaded_time = $custom_fields['page_load_time'][0];
            $loaded_time_text = "Onload time";
            if (isset($custom_fields['fully_loaded_time'][0])) {
                $loaded_time = $custom_fields['fully_loaded_time'][0];
                $loaded_time_text = "Fully loaded time";
            }

            $options = get_option( 'gfw_options' );
            $expired = ($this->gtmetrix_file_exists( $custom_fields['report_url'][0] . '/screenshot.jpg' ) ? false : true);
            ?>
            <div class="gfw-meta">
                <div><b>URL:</b> <?php echo $custom_fields['gfw_url'][0]; ?></div>
                <div><b>Test server region:</b> <?php echo $options['locations'][$custom_fields['gfw_location'][0]]['name']; ?></div>
                <div style="text-align: center"><b>Adblock:</b> <?php echo ($custom_fields['gfw_adblock'][0] ? 'On' : 'Off'); ?></div>
                <div style="text-align: right"><b>Latest successful test:</b> <?php echo date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $report->post_date ) ); ?></div>
            </div>
            <div>
                <table>
                    <tr>
                        <th>PageSpeed score:</th>
                        <td><?php echo $custom_fields['pagespeed_score'][0]; ?></td>
                        <th>YSlow score:</th>
                        <td><?php echo $custom_fields['yslow_score'][0]; ?></td>
                    </tr>
                    <tr>
                        <th><?php echo $loaded_time_text; ?>:</th>
                       <td><?php echo number_format( $loaded_time / 1000, 2 ); ?> seconds</td>
                        <th>Total HTML size:</th>
                        <td><?php echo size_format( $custom_fields['html_bytes'][0], 1 ); ?></td>
                    </tr>
                    <tr>
                        <th>Requests:</th>
                        <td><?php echo $custom_fields['page_elements'][0]; ?></td>
                        <th>HTML load time:</th>
                        <td><?php echo number_format( $custom_fields['html_load_time'][0] / 1000, 2 ); ?> seconds</td>
                    </tr>
                    <tr>
                        <th>Total page size:</th>
                        <td><?php echo size_format( $custom_fields['page_bytes'][0], 2 ); ?></td>
                        <th>&nbsp;</th>
                        <td>&nbsp;</td>
                    </tr>
                </table>
            </div>
            <?php
            if ( 'gfw_event' == $post->post_type ) {
                echo '<div class="graphs">';
                echo '<div><a href="' . $_POST['id'] . '" class="gfw-open-graph gfw-scores-graph" id="gfw-scores-graph">PageSpeed and YSlow scores graph</a></div>';
                echo '<div><a href="' . $_POST['id'] . '" class="gfw-open-graph gfw-times-graph" id="gfw-times-graph">Page load times graph</a></div>';
                echo '<div><a href="' . $_POST['id'] . '" class="gfw-open-graph gfw-sizes-graph" id="gfw-sizes-graph">Page sizes graph</a></div>';
                echo '</div>';
            }
            echo '<div class="actions">';
            if ( 'gfw_report' == $post->post_type ) {
                echo '<div><a href="' . GFW_SCHEDULE . '&report_id=' . $report->ID . '" class="gfw-schedule-icon-large">Schedule tests</a></div>';
            }
            if ( !$expired ) {
                echo '<div><a href="' . $custom_fields['report_url'][0] . '" target="_blank" class="gfw-report-icon">Detailed report</a></div>';
                echo '<div><a href="' . $custom_fields['report_url'][0] . '/pdf?full=1' . '" class="gfw-pdf-icon">Download PDF</a></div>';
                if ( isset( $custom_fields['gfw_video'][0] ) && $custom_fields['gfw_video'][0] ) {
                    echo '<div><a href="' . $custom_fields['report_url'][0] . '/video' . '" class="gfw-video-icon">Video</a></div>';
                }
            }
            echo '</div>';
            echo '</div>';
            echo '<div class="gfw-expansion-left">';
            if ( !$expired ) {
                echo '<img src="' . $custom_fields['report_url'][0] . '/screenshot.jpg' . '" />';
            }
        } else {
            echo '<p>There are currently no successful reports in the database for this event.</p>';
        }
        echo '</div>';
        echo '</div>';
        die();
    }

    public function report_graph_callback() {

        $graph = $_GET['graph'];

        $args = array(
            'post_type' => 'gfw_report',
            'numberposts' => 6,
            'orderby' => 'post_date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => 'gfw_event_id',
                    'value' => $_GET['id']
                ),
                array(
                    'key' => 'gtmetrix_test_id',
                    'value' => 0,
                    'compare' => '!='
                )
            ),
        );
        $query = new WP_Query( $args );
        while ( $query->have_posts() ) {
            $query->next_post();
            $custom_fields = get_post_custom( $query->post->ID );
            $milliseconds = strtotime( $query->post->post_date ) * 1000;
            $pagespeed_scores[] = array( $milliseconds, $custom_fields['pagespeed_score'][0] );
            $yslow_scores[] = array( $milliseconds, $custom_fields['yslow_score'][0] );
            $page_load_times[] = array( $milliseconds, number_format( $custom_fields['page_load_time'][0] / 1000, 1 ) );
            $html_load_times[] = array( $milliseconds, number_format( $custom_fields['html_load_time'][0] / 1000, 1 ) );
            $html_bytes[] = array( $milliseconds, $custom_fields['html_bytes'][0] / 1024 );
            $page_bytes[] = array( $milliseconds, $custom_fields['page_bytes'][0] / 1024 );
        }
        $graph_data = array( );
        switch ( $graph ) {
            case 'gfw-scores-graph':
                $graph_data[] = array( 'label' => 'Pagespeed Score', 'data' => $pagespeed_scores );
                $graph_data[] = array( 'label' => 'YSlow Score', 'data' => $yslow_scores );
                break;
            case 'gfw-times-graph':
                $graph_data[] = array( 'label' => 'Page Load Time', 'data' => $page_load_times );
                $graph_data[] = array( 'label' => 'HTML Load Time', 'data' => $html_load_times );
                break;
            case 'gfw-sizes-graph':
                $graph_data[] = array( 'label' => 'HTML Size', 'data' => $html_bytes );
                $graph_data[] = array( 'label' => 'Total Page Size', 'data' => $page_bytes );
                break;
        }
        echo json_encode( $graph_data );
        die();
    }

    public function reset_callback() {
        if ( check_ajax_referer( 'gfwnonce', 'security' ) ) {


            $args = array(
                'post_type' => 'gfw_report',
                'posts_per_page' => -1
            );

            $query = new WP_Query( $args );

            while ( $query->have_posts() ) {
                $query->next_post();
                wp_delete_post( $query->post->ID );
            }
        }
        die();
    }

    protected function api() {
        $options = get_option( 'gfw_options' );

        if ( !class_exists( 'Services_WTF_Test' ) ) {
            require_once('lib/Services_WTF_Test.php');
        }
        $api = new Services_WTF_Test();
        $api->api_username( $options['api_username'] );
        $api->api_password( $options['api_key'] );
        $api->user_agent( GFW_USER_AGENT );
        return $api;
    }

    public function credits_meta_box() {
        $api = $this->api();
        $status = get_transient( 'credit_status' );

        if ( false === $status ) {
            $status = $api->status();
            set_transient( 'credit_status', $status, 60 * 2 );
        }

        if ( $api->error() ) {
            $response['error'] = $test->error();
            return $response;
        }
        ?>
        <p style="font-weight:bold">API Credits Remaining: <?php echo $status['api_credits']; ?></p>
        <p style="font-style:italic">Next top-up: <?php echo $this->wp_date( $status['api_refill'], true ); ?></p>
        <p>Every test costs 1 API credit, except tests that use video, which cost 5 credits. You are topped up to 20 credits per day. If you need more, you can purchase them from GTmetrix.com.</p>
        <a href="https://gtmetrix.com/pro/<?php echo GFW_GA_CAMPAIGN ?>" target="_blank" class="button-secondary">Get More API Credits</a>
        <?php
    }

    public function optimization_meta_box() {
        ?>
        <p>Have a look at our WordPress Optimization Guide <a target="_blank" href="https://gtmetrix.com/wordpress-optimization-guide.html">WordPress Optimization Guide</a>.</p>
        <p>You can also <a target="_blank" href="https://gtmetrix.com/contact.html?type=optimization-request">contact us</a> for optimization help and we'll put you in the right direction towards a faster website.</p>
        <?php
    }

    public function news_meta_box() {
        $latest_news = get_transient( 'latest_news' );
        if ( false === $latest_news ) {
            $feed = wp_remote_get( 'https://gtmetrix.com/news.xml' );
            if ( 200 == wp_remote_retrieve_response_code( $feed ) ) {
                $xml = simplexml_load_string( $feed['body'] );
                $latest_news = '';
                if ( $xml != '' ) {
                    for ( $i = 0; $i < 5; $i++ ) {
                        $item = $xml->channel->item[$i];
                        $latest_news .= '<p>' . $item->description . '</a><br /><span class="description">' . $this->wp_date( $item->pubDate, true ) . '</span></p>';
                    }
                }
                set_transient( 'latest_news', '<!-- Updated ' . date( 'r' ) . ' -->' . $latest_news, 60 * 2 );
            } else {
                echo '<p>Sorry, feed temporarily unavailable</p>';
            }
        }
        echo $latest_news;
        echo '<a href="https://twitter.com/gtmetrix" target="_blank" class="button-secondary">Follow us on Twitter</a>';
    }

    protected function front_score( $dashboard = false ) {
        $args = array(
            'post_type' => 'gfw_report',
            'posts_per_page' => 1,
            'orderby' => 'post_date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => 'gfw_url',
                    'value' => array( trailingslashit( GFW_FRONT ), untrailingslashit( GFW_FRONT ) ),
                    'compare' => 'IN'
                ),
                array(
                    'key' => 'gtmetrix_test_id',
                    'value' => 0,
                    'compare' => '!='
                )
            ),
        );

        $query = new WP_Query( $args );

        echo '<input type="hidden" id="gfw-front-url" value="' . trailingslashit( GFW_FRONT ) . '" />';

        if ( $query->have_posts() ) {

            while ( $query->have_posts() ) {
                $query->next_post();
                $custom_fields = get_post_custom( $query->post->ID );

                $loaded_time = $custom_fields['page_load_time'][0];
                $loaded_time_text = "Onload time";
                if (isset($custom_fields['fully_loaded_time'][0])) {
                    $loaded_time = $custom_fields['fully_loaded_time'][0];
                    $loaded_time_text = "Fully loaded time";
                }

                $pagespeed_grade = $this->score_to_grade( $custom_fields['pagespeed_score'][0] );
                $yslow_grade = $this->score_to_grade( $custom_fields['yslow_score'][0] );
                $expired = true;
                if ( $this->gtmetrix_file_exists( $custom_fields['report_url'][0] . '/screenshot.jpg' ) ) {
                    $expired = false;
                }
                if ( !$dashboard && !$expired ) {
                    echo '<img src="' . $custom_fields['report_url'][0] . '/screenshot.jpg" style="display: inline-block; margin-right: 10px; border-radius: 8px 8px 8px 8px;" />';
                }
                ?>

                <div class="gfw gfw-latest-report-wrapper">
                    <div class="gfw-box gfw-latest-report">
                        <div class="gfw-latest-report-pagespeed gfw-report-grade-<?php echo $pagespeed_grade['grade']; ?>">
                            <span class="gfw-report-grade"><?php echo $pagespeed_grade['grade']; ?></span>
                            <span class="gfw-report-title">PageSpeed:</span><br>
                            <span class="gfw-report-score">(<?php echo $custom_fields['pagespeed_score'][0]; ?>%)</span>
                        </div>
                        <div class="gfw-latest-report-yslow gfw-report-grade-<?php echo $yslow_grade['grade']; ?>">
                            <span class="gfw-report-grade"><?php echo $yslow_grade['grade']; ?></span>
                            <span class="gfw-report-title">YSlow:</span><br />
                            <span class="gfw-report-score">(<?php echo $custom_fields['yslow_score'][0]; ?>%)</span>
                        </div>
                        <div class="gfw-latest-report-details">
                            <b><?php echo $loaded_time_text; ?>:</b> <?php echo number_format( $loaded_time / 1000, 2 ); ?> seconds<br />
                            <b>Total page size:</b> <?php echo size_format( $custom_fields['page_bytes'][0], 2 ); ?><br />
                            <b>Requests:</b> <?php echo $custom_fields['page_elements'][0]; ?><br />
                        </div>
                    </div>
                    <p>
                        <?php
                        if ( !$expired ) {
                            echo '<a href="' . $custom_fields['report_url'][0] . '" target="_blank" class="gfw-report-icon">Detailed report</a> &nbsp;&nbsp; ';
                        }
                        ?>
                        <a href="<?php echo GFW_SCHEDULE; ?>&report_id=<?php echo $query->post->ID; ?>" class="gfw-schedule-icon-large">Schedule tests</a></p>
                    <p><a href="<?php echo GFW_TESTS; ?>" class="button-primary" id="gfw-test-front">Re-test your Front Page</a></p>
                </div>
                <?php
            }
        } else {
            echo '<h4>Your Front Page (' . GFW_FRONT . ') has not been analyzed yet</h4><p>Your front page is set in the <a href="' . get_admin_url() . 'options-general.php">Settings</a> of your WordPress install.</p><p><a href="' . GFW_TESTS . '" class="button-primary" id="gfw-test-front">Test your Front Page now</a></p>';
        }
    }

    public function score_meta_box() {
        $this->front_score( false );
    }

    public function test_meta_box() {
        $passed_url = isset( $_GET['url'] ) ? GFW_FRONT . $_GET['url'] : '';
        ?>
        <form method="post" id="gfw-parameters">
            <input type="hidden" name="post_type" value="gfw_report" />
            <div id="gfw-scan" class="gfw-dialog" title="Testing with GTmetrix">
                <div id="gfw-screenshot"><img src="<?php echo GFW_URL . 'images/scanner.png'; ?>" alt="" id="gfw-scanner" /><div class="gfw-message"></div></div>
            </div>
            <?php
            wp_nonce_field( plugin_basename( __FILE__ ), 'gfwtestnonce' );
            $options = get_option( 'gfw_options' );
            ?>

            <p><input type="text" id="gfw_url" name="gfw_url" value="<?php echo $passed_url; ?>" placeholder="You can enter a URL (eg. http://yourdomain.com), or start typing the title of your page/post" /><br />
                <span class="gfw-placeholder-alternative description">You can enter a URL (eg. http://yourdomain.com), or start typing the title of your page/post</span></p>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Label</th>
                    <td><input type="text" id="gfw_label" name="gfw_label" value="" /><br />
                        <span class="description">Optionally enter a label for your report</span></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Locations<a class="gfw-help-icon tooltip" href="#" title="Analyze the performance of the page from one of our several test regions.  Your PageSpeed and YSlow scores usually stay roughly the same, but Page Load times and Waterfall should be different. Use this to see how latency affects your page load times from different parts of the world."></a></th>
                    <td><select name="gfw_location" id="gfw_location">
                            <?php
                            foreach ( $options['locations'] as $location ) {
                                echo '<option value="' . $location['id'] . '" ' . selected( isset( $options['default_location'] ) ? $options['default_location'] : $location['default'], $location['id'], false ) . '>' . $location['name'] . '</option>';
                            }
                            ?>
                        </select><br />
                        <span class="description">Test Server Region</span></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="gfw_adblock">Adblock Plus</label><a class="gfw-help-icon tooltip" href="#" title="Prevent ads from loading using the Adblock Plus plugin.  This can help you assess how ads affect the loading of your site."></a></th>
                    <td><input type="checkbox" name="gfw_adblock" id="gfw_adblock" value="1" <?php checked( isset( $options['default_adblock'] ) ? $options['default_adblock'] : 0, 1 ); ?> /> <span class="description">Block ads with Adblock Plus</span></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="gfw_video">Video</label><a class="gfw-help-icon tooltip" href="#" title="Debug page load issues by seeing exactly how the page loads. View the page load up to 4x slower to help pinpoint rendering or other page load problems."></a></th>
                    <td><input type="checkbox" name="gfw_video" id="gfw_video" value="1" /> <span class="description">Create a video of the page loading (requires 5 api credits)</span></td>
                </tr>
            </table>


            <?php submit_button( 'Test URL now!', 'primary', 'submit', false ); ?>
        </form>
        <?php
    }

    public function schedule_meta_box() {
        $report_id = isset( $_GET['report_id'] ) ? $_GET['report_id'] : 0;
        $event_id = isset( $_GET['event_id'] ) ? $_GET['event_id'] : 0;
        $cpt_id = $report_id ? $report_id : $event_id;
        $custom_fields = get_post_custom( $cpt_id );
        $options = get_option( 'gfw_options' );
        $grades = array( 90 => 'A', 80 => 'B', 70 => 'C', 60 => 'D', 50 => 'E', 40 => 'F' );

        if ( empty( $custom_fields ) ) {
            echo '<p>Event not found.</p>';
            return false;
        }
        ?>
        <form method="post">
            <input type="hidden" name="event_id" value="<?php echo $event_id; ?>" />
            <input type="hidden" name="report_id" value="<?php echo $report_id; ?>" />
            <?php wp_nonce_field( plugin_basename( __FILE__ ), 'gfwschedulenonce' ); ?>

            <p><b>URL/label:</b> <?php echo ($custom_fields['gfw_label'][0] ? $custom_fields['gfw_label'][0] . ' (' . $custom_fields['gfw_url'][0] . ')' : $custom_fields['gfw_url'][0]); ?></p>
            <p><b>Adblock:</b> <?php echo $custom_fields['gfw_adblock'][0] ? 'On' : 'Off'; ?></p>
            <p><b>Location:</b> Vancouver, Canada <i>(scheduled tests always use the Vancouver, Canada test server region)</i></p>


            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Frequency</th>
                    <td><select name="gfw_recurrence" id="gfw_recurrence">
                            <?php
                            foreach ( array( 'Hourly' => 'hourly', 'Daily' => 'daily', 'Weekly' => 'weekly', 'Monthly' => 'monthly' ) as $name => $recurrence ) {
                                echo '<option value="' . $recurrence . '" ' . selected( isset( $custom_fields['gfw_recurrence'][0] ) ? $custom_fields['gfw_recurrence'][0] : 'weekly', $recurrence, false ) . '>' . $name . '</option>';
                            }
                            ?>
                        </select><br />
                        <span class="description">Note: every report will use up 1 of your API credits on GTmetrix.com</span></td>
                </tr>
                <?php
                if ( isset( $custom_fields['gfw_notifications'][0] ) ) {
                    $notifications = unserialize( $custom_fields['gfw_notifications'][0] );
                    $notifications_count = count( $notifications );
                } else {
                    // display a disabled, arbitrary condition if no conditions are already set
                    $notifications = array( 'pagespeed_score' => 90 );
                    $notifications_count = 0;
                }
                ?>
                <tr valign="top">
                    <th scope="row"><label for="gfw-notifications">Enable alerts</label></th>
                    <td><input type="checkbox" id="gfw-notifications" value="1" <?php checked( $notifications_count > 0 ); ?> /><br />
                        <span class="description">If you'd like to be notified by email when poor test results are returned, click Enable alerts above</span></td>
                </tr>

                <?php
                for ( $i = 0; $i < 4; $i++ ) {
                    if ( $notifications ) {
                        $condition_unit = reset( $notifications );
                        $condition_name = key( $notifications );
                        $condition_status = ' style="display: table-row;"';
                        $disabled = '';
                    } else {
                        $condition_unit = false;
                        $condition_name = false;
                        $condition_status = ' style="display: none;"';
                        $disabled = ' disabled="disabled"';
                    }
                    ?>
                    <tr valign="top" class="gfw-conditions gfw-conditions-<?php echo $i; ?>"<?php echo $condition_status; ?>>
                        <th scope="row"><?php echo $i ? 'or' : 'Email admin when'; ?></th>
                        <td><select name="gfw_condition[<?php echo $i; ?>]" class="gfw-condition"<?php echo $disabled; ?>>
                                <?php
                                $conditions = array(
                                    'pagespeed_score' => 'PageSpeed score is less than ',
                                    'yslow_score' => 'YSlow score is less than ',
                                    'page_load_time' => 'Page load time is greater than ',
                                    'page_bytes' => 'Page size is greater than '
                                );
                                foreach ( $conditions as $value => $name ) {
                                    echo '<option value="' . $value . '" ' . selected( $condition_name, $value, false ) . '>' . $name . '</option>';
                                }
                                ?>
                            </select>
                            <select name="pagespeed_score[<?php echo $i; ?>]" class="pagespeed_score gfw-units"<?php echo ('pagespeed_score' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                                <?php
                                foreach ( $grades as $index => $value ) {
                                    echo '<option value="' . $index . '" ' . selected( $condition_unit, $index, false ) . '>' . $value . '</option>';
                                }
                                ?>
                            </select>
                            <select name="yslow_score[<?php echo $i; ?>]" class="yslow_score gfw-units"<?php echo ('yslow_score' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                                <?php
                                foreach ( $grades as $index => $value ) {
                                    echo '<option value="' . $index . '" ' . selected( $condition_unit, $index, false ) . '>' . $value . '</option>';
                                }
                                ?>
                            </select>
                            <select name="page_load_time[<?php echo $i; ?>]" class="page_load_time gfw-units"<?php echo ('page_load_time' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                                <?php
                                foreach ( array( 1000 => '1 second', 2000 => '2 seconds', 3000 => '3 seconds', 4000 => '4 seconds', 5000 => '5 seconds' ) as $index => $value ) {
                                    echo '<option value="' . $index . '" ' . selected( $condition_unit, $index, false ) . '>' . $value . '</option>';
                                }
                                ?>
                            </select>
                            <select name="page_bytes[<?php echo $i; ?>]" class="page_bytes gfw-units"<?php echo ('page_bytes' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                                <?php
                                foreach ( array( 102400 => '100 KB', 204800 => '200 KB', 307200 => '300 KB', 409600 => '400 KB', 512000 => '500 KB', 1048576 => '1 MB' ) as $index => $value ) {
                                    echo '<option value="' . $index . '" ' . selected( $condition_unit, $index, false ) . '>' . $value . '</option>';
                                }
                                ?>
                            </select>
                            <?php echo $i ? '<a href="javascript:void(0)" class="gfw-remove-condition">- Remove</a>' : ''; ?>
                        </td>
                        <?php
                        array_shift( $notifications );
                    }
                    ?>
                </tr>

                <tr style="display: <?php echo ($notifications_count && $notifications_count < 4 ? 'table-row' : 'none'); ?>" id="gfw-add-condition">
                    <th scope="row">&nbsp;</th>
                    <td><a href="javascript:void(0)">+ Add a condition</a></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Status</th>
                    <td><select name="gfw_status" id="gfw_status">
                            <?php
                            foreach ( array( 1 => 'Active', 2 => 'Paused', 3 => 'Paused due to recurring failures' ) as $key => $status ) {
                                echo '<option value="' . $key . '" ' . selected( isset( $custom_fields['gfw_status'][0] ) ? $custom_fields['gfw_status'][0] : 1, $key, false ) . '>' . $status . '</option>';
                            }
                            ?>
                        </select></td>
                </tr>

            </table>
            <?php
            submit_button( 'Save', 'primary', 'submit', false );
            echo '</form>';
        }

        public function reports_list() {
            $args = array(
                'post_type' => 'gfw_report',
                'posts_per_page' => -1,
                'meta_key' => 'gfw_event_id',
                'meta_value' => 0
            );
            $query = new WP_Query( $args );
            $no_posts = !$query->post_count;
            ?>
            <p>Click a report to see more detail, or to schedule future tests.</p>
            <div class="gfw-table-wrapper">
                <table class="gfw-table">
                    <thead>
                        <tr style="display: <?php echo $no_posts ? 'none' : 'table-row' ?>">
                            <th class="gfw-reports-url">Label/URL</th>
                            <th class="gfw-reports-load-time">Page Load</th>
                            <th class="gfw-reports-pagespeed">PageSpeed</th>
                            <th class="gfw-reports-yslow">YSlow</th>
                            <th class="gfw-reports-last">Date</th>
                            <th class="gfw-reports-delete"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $row_number = 0;
                        while ( $query->have_posts() ) {
                            $query->next_post();
                            $custom_fields = get_post_custom( $query->post->ID );
                            foreach ( $custom_fields as $name => $value ) {
                                $$name = $value[0];
                            }

                            if ( !isset( $gtmetrix_error ) ) {
                                $pagespeed_grade = $this->score_to_grade( $pagespeed_score );
                                $yslow_grade = $this->score_to_grade( $yslow_score );
                            }
                            $report_date = $this->wp_date( $query->post->post_date, true );
                            $title = $gfw_label ? $gfw_label : $this->append_http( $gfw_url );

                            echo '<tr class="' . ($row_number++ % 2 ? 'even' : 'odd') . '" id="post-' . $query->post->ID . '">';

                            if ( isset( $gtmetrix_error ) ) {
                                echo '<td data-th="Error" class="gfw-reports-url">' . $title . '</td>';
                                echo '<td data-th="Message" class="reports-error" colspan="3">' . $this->translate_message( $gtmetrix_error ) . '</td>';
                                echo '<td data-th="Date">' . $report_date . '</td>';
                            } else {
                                echo '<td data-th="Label/URL" title="Click to expand/collapse" class="gfw-reports-url gfw-toggle tooltip">' . $title . '</td>';
                                echo '<td data-th="Page Load" class="gfw-toggle">' . number_format( $page_load_time / 1000, 2 ) . 's</td>';
                                echo '<td data-th="PageSpeed" class="gfw-toggle gfw-reports-pagespeed"><div class="gfw-grade-meter gfw-grade-meter-' . $pagespeed_grade['grade'] . '"><span class="gfw-grade-meter-text">' . $pagespeed_grade['grade'] . ' (' . $pagespeed_score . ')</span><span class="gfw-grade-meter-bar" style="width: ' . $pagespeed_score . '%"></span></div></td>';
                                echo '<td data-th="YSlow" class="gfw-toggle gfw-reports-yslow"><div class="gfw-grade-meter gfw-grade-meter-' . $yslow_grade['grade'] . '"><span class="gfw-grade-meter-text">' . $yslow_grade['grade'] . ' (' . $yslow_score . ')</span><span class="gfw-grade-meter-bar" style="width: ' . $yslow_score . '%"></span></div></td>';
                                echo '<td data-th="Date" class="gfw-toggle" title="' . $report_date . '">' . $report_date . '</td>';
                            }
                            echo '<td class="gfw-action-icons"><a href="' . GFW_SCHEDULE . '&report_id=' . $query->post->ID . '" class="gfw-schedule-icon-small tooltip" title="Schedule tests">Schedule test</a> <a href="' . GFW_TESTS . '&delete=' . $query->post->ID . '" rel="#gfw-confirm-delete" class="gfw-delete-icon delete-report tooltip" title="Delete Report">Delete Report</a></td>';
                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>
                <?php
                if ( $no_posts ) {
                    echo '<p class="gfw-no-posts">You have no reports yet</p>';
                }
                ?>
            </div>
            <?php
        }

        public function events_list() {

            $args = array(
                'post_type' => 'gfw_event',
                'posts_per_page' => -1,
                'meta_key' => 'gfw_recurrence'
            );
            $query = new WP_Query( $args );
            $no_posts = !$query->post_count;
            ?>

            <div id="gfw-graph" class="gfw-dialog" title="">
                <div id="gfw-flot-placeholder"></div>
                <div class="graph-legend" id="gfw-graph-legend"></div>
            </div>

            <div class="gfw-table-wrapper">
                <table class="gfw-table events">
                    <thead>
                        <tr style="display: <?php echo $no_posts ? 'none' : 'table-row' ?>">
                            <th>Label/URL</th>
                            <th>Frequency</th>
                            <th>Alerts</th>
                            <th>Last Report</th>
                            <th>Next Report</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $row_no = 0;
                        $next_report['hourly'] = wp_next_scheduled( 'gfw_hourly_event', array( 'hourly' ) );
                        $next_report['daily'] = wp_next_scheduled( 'gfw_daily_event', array( 'daily' ) );
                        $next_report['weekly'] = wp_next_scheduled( 'gfw_weekly_event', array( 'weekly' ) );
                        $next_report['monthly'] = wp_next_scheduled( 'gfw_monthly_event', array( 'monthly' ) );

                        while ( $query->have_posts() ) {
                            $query->next_post();

                            $custom_fields = get_post_custom( $query->post->ID );
                            if ( $custom_fields['gfw_event_error'][0] ) {
                                $gtmetrix_error = get_post_meta( $custom_fields['gfw_last_report_id'][0], 'gtmetrix_error', true );
                            }
                            $last_report = isset( $custom_fields['gfw_last_report'][0] ) ? $this->wp_date( $custom_fields['gfw_last_report'][0], true ) : 'Pending';

                            $title = $custom_fields['gfw_label'][0] ? $custom_fields['gfw_label'][0] : $custom_fields['gfw_url'][0];
                            $row = '<tr class="' . ($row_no % 2 ? 'even' : 'odd') . '" id="post-' . $query->post->ID . '">';
                            $toggle_title = ' title="Click to expand/collapse" ';
                            $toggle_class = 'gfw-toggle tooltip';
                            if ( isset( $gtmetrix_error ) ) {
                                $toggle_title = '';
                                $toggle_class = '';
                            }

                            $row .= '<td class="' . $toggle_class . ' gfw-reports-url"' . $toggle_title . '>' . $title . '</td>';
                            $row .= '<td class="' . $toggle_class . '"' . $toggle_title . '>' . ucwords( $custom_fields['gfw_recurrence'][0] ) . '</div></td>';
                            $row .= '<td class="' . $toggle_class . '"' . $toggle_title . '>' . (isset( $custom_fields['gfw_notifications'][0] ) ? 'Enabled' : 'Disabled') . '</div></td>';
                            $row .= '<td class="' . $toggle_class . '"' . $toggle_title . '>' . $last_report . ($custom_fields['gfw_event_error'][0] ? ' <span class="gfw-failed tooltip" title="' . $gtmetrix_error . '">(failed)</span>' : '') . '</td>';
                            $row .= '<td class="' . $toggle_class . '"' . $toggle_title . '>' . $this->wp_date( $next_report[$custom_fields['gfw_recurrence'][0]], true ) . '</td>';
                            $row .= '<td><a href="' . GFW_SCHEDULE . '&event_id=' . $query->post->ID . '" rel="" class="gfw-edit-icon tooltip" title="Edit this event">Edit</a> <a href="' . GFW_SCHEDULE . '&delete=' . $query->post->ID . '" rel="#gfw-confirm-delete" title="Delete this event" class="gfw-delete-icon delete-event tooltip">Delete Event</a> <a href="' . GFW_SCHEDULE . '&status=' . $query->post->ID . '" class="tooltip gfw-pause-icon' . (1 == $custom_fields['gfw_status'][0] ? '" title="Pause this event">Pause Event' : ' paused" title="Reactivate this event">Reactivate Event') . '</a></td>';
                            $row .= '</tr>';
                            echo $row;
                            $row_no++;
                        }
                        ?>
                    </tbody>
                </table>

                <?php
                if ( $no_posts ) {
                    echo '<p class="gfw-no-posts">You have no Scheduled Tests. Go to <a href="' . GFW_TESTS . '">Tests</a> to create one.</p>';
                }
                ?>

            </div>

            <div id="gfw-confirm-delete" class="gfw-dialog" title="Delete this event?">
                <p>Are you sure you want to delete this event?</p>
                <p>This will delete all the reports generated so far by this event.</p>
            </div>


            <?php
        }

        protected function translate_message( $message ) {
            if ( 0 === stripos( $message, 'Maximum number of API calls reached.' ) ) {
                $message .= ' or <a href="https://gtmetrix.com/pro/' . GFW_GA_CAMPAIGN . '" target="_blank" title="Go Pro">go Pro</a> to receive bigger daily top-ups and other benefits.';
            }
            return $message;
        }

        public function authenticate_meta_box() {
            if ( !GFW_AUTHORIZED ) {
                echo '<p style="font-weight:bold">You must have an API key to use this plugin.</p><p>To get an API key, register for a FREE account at gtmetrix.com and generate one in the API section.</p><p><a href="https://gtmetrix.com/api/' . GFW_GA_CAMPAIGN . '" target="_blank">Register for a GTmetrix account now &raquo;</a></p>';
            }
            echo '<table class="form-table">';
            do_settings_fields( 'gfw_settings', 'authentication_section' );
            echo '</table>';
        }

        public function options_meta_box() {
            echo '<table class="form-table">';
            do_settings_fields( 'gfw_settings', 'options_section' );
            echo '</table>';
        }

        public function widget_meta_box() {
            echo '<table class="form-table">';
            do_settings_fields( 'gfw_settings', 'widget_section' );
            echo '</table>';
        }

        public function reset_meta_box() {
            echo '<table class="form-table">';
            do_settings_fields( 'gfw_settings', 'reset_section' );
            echo '</table>';
        }

        protected function score_to_grade( $score ) {
            $grade = array( );
            if ($score == 100) {
                $grade['grade'] = 'A';
            } else if ($score < 50) {
                $grade['grade'] = 'F';
            } else {
                $grade['grade'] = '&#' . (74 - floor( $score / 10 )) . ';';
            }
            return $grade;
        }

        protected function gtmetrix_file_exists( $url ) {
            $options = get_option( 'gfw_options' );
            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
            curl_setopt( $ch, CURLOPT_NOBODY, true );
            if ( curl_exec( $ch ) !== false ) {
                $curl_info = curl_getinfo( $ch );
                if ( $curl_info['http_code'] == 200 ) {
                    return true;
                }
                return false;
            } else {
                echo curl_error( $ch );
                return false;
            }
        }

        protected function append_http( $url ) {
            if ( stripos( $url, 'http' ) === 0 || !$url ) {
                return $url;
            } else {
                return 'http://' . $url;
            }
        }

        protected function wp_date( $date_time, $time = false ) {
            date_default_timezone_set( GFW_TIMEZONE );
            $local_date_time = date( get_option( 'date_format' ) . ($time ? ' ' . get_option( 'time_format' ) : ''), (is_numeric( $date_time ) ? $date_time : strtotime( $date_time ) ) );
            return $local_date_time;
        }

        public function gfw_widget_init() {
            if ( GFW_AUTHORIZED ) {
                register_widget( 'GFW_Widget' );
            }
        }

    }

    $gfw = new GTmetrix_For_WordPress();
