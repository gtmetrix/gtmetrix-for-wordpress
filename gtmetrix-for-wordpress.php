<?php
/*
  Plugin Name: GTmetrix for WordPress
  Plugin URI: https://gtmetrix.com/gtmetrix-for-wordpress-plugin.html
  Description: GTmetrix can help you develop a faster, more efficient, and all-around improved website experience for your users. Your users will love you for it.
  Version: 0.4.7
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
        add_action( 'admin_init', array( &$this, 'set_schedules_and_perms' ) );
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
        add_action( 'wp_ajax_sync', array( &$this, 'sync_callback' ) );
        add_action( 'widgets_init', array( &$this, 'gfw_widget_init' ) );
        add_filter( 'cron_schedules', array( &$this, 'add_intervals' ) );
        add_filter( 'plugin_row_meta', array( &$this, 'plugin_links' ), 10, 2 );

        $options = get_option( 'gfw_options' );
        define( 'GFW_WP_VERSION', '3.3.1' );
        define( 'GFW_VERSION', '0.4.6' );
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

    /*
     * Removed logic from this function. It's all called from the admin_init hook now to work around Composer not running the install logic.
     */
    public function activate() {
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
                        $conditions = array(
                            'pagespeed_score' => 'PageSpeed score',
                            'yslow_score' => 'YSlow score',
                            'fully_loaded_time' => 'Fully Loaded',
                            'time_to_first_byte' => 'Time to First Byte',
                            'redirect_duration' => 'Redirect Duration',
                            'connect_duration' => 'Connect Duration',
                            'backend_duration' => 'Backend Duration',
                            'onload_time' => 'Onload',
                            'onload_duration' => 'Onload Duration',
                            'page_bytes' => 'Total Size',
                            'page_requests' => 'Total Requests',
                            'html_bytes' => 'HTML Size',
                            'first_paint_time' => 'First Paint',
                            'dom_interactive_time' => 'DOM Interactive',
                            'dom_content_loaded_time' => 'DOM Content Loaded',
                            'dom_content_loaded_duration' => 'DOM Content Loaded Duration',
                            'gtmetrix_grade' => 'GTmetrix Grade',
                            'performance_score' => 'Performance Score',
                            'structure_score' => 'Structure Score',
                            'largest_contentful_paint' => 'Largest Contentful Paint',
                            'first_contentful_paint' => 'First Contentful Paint',
                            'total_blocking_time' => 'Total Blocking Time',
                            'cumulative_layout_shift' => 'Cumulative Layout Shift',
                            'time_to_interactive' => 'Time to Interactive',
                            'speed_index' => 'Speed Index'
                        );
                        foreach ( unserialize( $event_custom['gfw_notifications'][0] ) as $key => $value_arr ) {
                            //for time values, multiply by 1000 to get milliseconds. If the key contains "time" or "duration" or "contentful_paint" which are time scores
                            $value_score = $value_arr['value'];
                            if( str_contains( $key, "time" ) || str_contains( $key, "duration" ) || str_contains( $key, "contentful_paint" ) ) {
                                $value_score_compare = $value_score * 1000;
                                $value_score_compare_email = $value_score . " s";
                                $value_score_email = ($report[$key] / 1000 ) . " s";
                            } else if( $key == "html_bytes" ) {
                                $value_size = $value_arr['html_bytes_size'];
                                if( $value_size == "KB" ) {
                                    $value_score_email = round($report[$key] / 1024, 3) . "KB";
                                    $value_score_compare = $value_score * 1024;
                                } else if( $value_size == "MB" ) {
                                    $value_score_email = round($report[$key] / (1024 * 1024), 3) . "MB";
                                    $value_score_compare = $value_score * 1024 * 1024;
                                }
                                $value_score_compare_email = $value_score . $value_size;
                            } else if( $key == "page_bytes" ) {
                                $value_size = $value_arr['page_bytes_size'];
                                if( $value_size == "KB" ) {
                                    $value_score_email = round($report[$key] / 1024, 3) . "KB";
                                    $value_score_compare = $value_score * 1024;
                                } else if( $value_size == "MB" ) {
                                    $value_score_email = round($report[$key] / (1024 * 1024), 3) . "MB";
                                    $value_score_compare = $value_score * 1024 * 1024;
                                }
                                $value_score_compare_email = $value_score . $value_size;
                            } else if( $key == "gtmetrix_grade" ) {
                                $value_score_email = $report[$key];
                                $value_score_compare = $value_score;
                                $value_score_compare_email = $value_score;
                            } else {
                                $value_score_compare = $value_score;
                                $value_score_compare_email = $value_score;
                                $value_score_email = $report[$key];
                            }
                            if( $key == "gtmetrix_grade" ) {
                                if( $value_arr['comparator'] == ">" ) {
                                    if( $report[$key] < $value_score_compare ) {
                                        $email_content[] = '<p>' . $conditions[$key] . ' was greater than ' . $value_score_compare_email . '.</p><p><span style="font-size:12px; color:#666666; font-style:italic">The URL is currently scoring ' . $value_score_email . '.</p>';
                                    }
                                } else if ( $value_arr['comparator'] == "<" ) {
                                    if( $report[$key] > $value_score_compare ) {
                                        $email_content[] = '<p>' . $conditions[$key] . ' was less than ' . $value_score_compare_email . '.</p><p><span style="font-size:12px; color:#666666; font-style:italic">The URL is currently scoring ' . $value_score_email . '.</p>';
                                    }
                                } else {
                                    if( $report[$key] == $value_score_compare ) {
                                        $email_content[] = '<p>' . $conditions[$key] . ' was equal to ' . $value_score_compare_email . '.</p>';
                                    }
                                }
                            } else {
                                if( $value_arr['comparator'] == "<" ) {
                                    if( $report[$key] < $value_score_compare ) {
                                        $email_content[] = '<p>' . $conditions[$key] . ' was less than ' . $value_score_compare_email . '.</p><p><span style="font-size:12px; color:#666666; font-style:italic">The URL is currently scoring ' . $value_score_email . '.</p>';
                                    }
                                } else if ( $value_arr['comparator'] == ">" ) {
                                    if( $report[$key] > $value_score_compare ) {
                                        $email_content[] = '<p>' . $conditions[$key] . ' was greater than ' . $value_score_compare_email . '.</p><p><span style="font-size:12px; color:#666666; font-style:italic">The URL is currently scoring ' . $value_score_email . '.</p>';
                                    }
                                } else {
                                    if( $report[$key] == $value_score_compare ) {
                                        $email_content[] = '<p>' . $conditions[$key] . ' was equal to ' . $value_score_compare_email . '.</p>';
                                    }
                                }
                            }
                            //error_log( "EMAIL CONTENT " . print_r( $email_content, TRUE ));
                            /*
                            switch ( $key ) {
                                //note that we
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
                            */
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
            wp_add_dashboard_widget( 'gfw_dashboard_widget', 'Latest Front Page GTmetrix Results', array( &$this, 'dashboard_widget' ) );
        }
    }

    public function dashboard_widget() {
        $this->front_score( true );
    }

    public function add_menu_items() {
        if ( GFW_AUTHORIZED ) {
            add_menu_page( 'GTmetrix', 'GTmetrix', 'access_gtmetrix', 'gfw_tests', array( $this, 'tests_page' ), 'none' );
            $this->tests_page_hook = add_submenu_page( 'gfw_tests', 'Tests', 'Tests', 'access_gtmetrix', 'gfw_tests', array( $this, 'tests_page' ) );
            $this->schedule_page_hook = add_submenu_page( 'gfw_tests', 'Monitor', 'Monitor', 'access_gtmetrix', 'gfw_schedule', array( $this, 'schedule_page' ) );
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
        /*
         * Moved from plugin activation hook
         */
        $options = get_option( 'gfw_options' );
        $options['widget_pagespeed'] = isset( $options['widget_pagespeed'] ) ? $options['widget_pagespeed'] : 1;
        $options['widget_yslow'] = isset( $options['widget_yslow'] ) ? $options['widget_yslow'] : 1;
        $options['widget_scores'] = isset( $options['widget_scores'] ) ? $options['widget_scores'] : 1;
        $options['widget_link'] = isset( $options['widget_link'] ) ? $options['widget_link'] : 1;
        $options['widget_css'] = isset( $options['widget_css'] ) ? $options['widget_css'] : 1;
        $options['front_url'] = isset( $options['front_url'] ) ? $options['front_url'] : 'wp';
        $options['clear_settings'] = isset( $options['clear_settings'] ) ? $options['clear_settings'] : 0;
        $options['clear_records'] = isset( $options['clear_records'] ) ? $options['clear_records'] : 0;
        update_option( 'gfw_options', $options );
        $options = get_option( 'gfw_account' );
        $options['account_type'] = 'Basic';
        
        register_setting( 'gfw_options_group', 'gfw_options', array( &$this, 'sanitize_settings' ) );
        add_settings_section( 'authentication_section', '', array( &$this, 'section_text' ), 'gfw_settings' );
        add_settings_field( 'api_username', 'GTmetrix Account E-mail', array( &$this, 'set_api_username' ), 'gfw_settings', 'authentication_section' );
        add_settings_field( 'api_key', 'API Key', array( &$this, 'set_api_key' ), 'gfw_settings', 'authentication_section' );
        if ( GFW_AUTHORIZED ) {
            add_settings_field( 'sync', 'Account Sync', array( &$this, 'set_sync' ), 'gfw_settings', 'authentication_section' );
            add_settings_section( 'options_section', '', array( &$this, 'section_text' ), 'gfw_settings' );
            add_settings_field( 'dashboard_widget', 'Show Dashboard widget', array( &$this, 'set_dashboard_widget' ), 'gfw_settings', 'options_section' );
            add_settings_field( 'toolbar_link', 'Show "GTmetrix" on Admin Toolbar', array( &$this, 'set_toolbar_link' ), 'gfw_settings', 'options_section' );
            add_settings_field( 'notifications_email', 'E-mail to send Alerts to', array( &$this, 'set_notifications_email' ), 'gfw_settings', 'options_section' );
            add_settings_field( 'front_url', 'Front page URL', array( &$this, 'set_front_url' ), 'gfw_settings', 'options_section' );
            add_settings_field( 'report_type', 'Report Type', array( &$this, 'set_report_type' ), 'gfw_settings', 'options_section' );
            add_settings_field( 'options_separator', 'Default Analysis Options', array( &$this, 'set_default_separator' ), 'gfw_settings', 'options_section', array( 'class' => 'gfw_default_analysis_options' ) );
            add_settings_field( 'default_browser', 'Default browser', array( &$this, 'set_default_browser' ), 'gfw_settings', 'options_section' );
            add_settings_field( 'default_location', 'Default location', array( &$this, 'set_default_location' ), 'gfw_settings', 'options_section' );
            add_settings_field( 'default_connection', 'Default connection', array( &$this, 'set_default_connection' ), 'gfw_settings', 'options_section' );
            add_settings_field( 'default_retention', 'Default data retention', array( &$this, 'set_default_retention' ), 'gfw_settings', 'options_section' );
            //add_settings_field( 'clear_settings', 'Clear settings on uninstall', array( &$this, 'set_clear_settings' ), 'gfw_settings', 'options_section' );
            //add_settings_field( 'clear_records', 'Clear records on uninstall', array( &$this, 'set_clear_records' ), 'gfw_settings', 'options_section' );
            add_settings_section( 'reset_section', '', array( &$this, 'section_text' ), 'gfw_settings' );
            add_settings_field( 'default_adblock', 'Default Adblock status', array( &$this, 'set_default_adblock' ), 'gfw_settings', 'options_section' );
            add_settings_field( 'default_enable_video', 'Default enable video', array( &$this, 'set_default_enable_video' ), 'gfw_settings', 'options_section' );
            add_settings_field( 'reset', 'Flush plugin data from WordPress database', array( &$this, 'set_reset' ), 'gfw_settings', 'reset_section' );
        }
    }

    public function set_schedules_and_perms() {
        //create events if necessary
        $args = array( FALSE );
        if ( ! wp_next_scheduled( 'gfw_hourly_event', $args ) ) {
            wp_schedule_event( mktime( date( 'H' ) + 1, 0, 0 ), 'hourly', 'gfw_hourly_event', array( 'hourly' ) );
        }
        if ( ! wp_next_scheduled( 'gfw_daily_event', $args ) ) {
            wp_schedule_event( mktime( date( 'H' ) + 1, 0, 0 ), 'daily', 'gfw_daily_event', array( 'daily' ) );
        }
        if ( ! wp_next_scheduled( 'gfw_weekly_event', $args ) ) {
            wp_schedule_event( mktime( date( 'H' ) + 1, 0, 0 ), 'weekly', 'gfw_weekly_event', array( 'weekly' ) );
        }
        if ( ! wp_next_scheduled( 'gfw_monthly_event', $args ) ) {
            wp_schedule_event( mktime( date( 'H' ) + 1, 0, 0 ), 'monthly', 'gfw_monthly_event', array( 'monthly' ) );
        }

        $role = get_role( 'administrator' );
        if( !$role->has_cap( 'access_gtmetrix' ) ) {
            $role->add_cap( 'access_gtmetrix' );
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
    
    public function set_sync() {
        $options = get_option( 'gfw_options' );
        echo '<input type="button" value="Sync Now" class="button-primary" id="gfw-sync" /> ';
       echo '<span class="description">Get the latest data regarding your account status (API credit usage, plan level, etc.)</span>';
    }

    public function set_report_type () {
        $options = get_option( 'gfw_options' );
        echo '<p><select name="gfw_options[report_type]" id="report_type">';
        foreach ( $options['report_types'] as $report_type ) {
            echo '<option value="' . $report_type['id'] . '" ' . selected( $options['report_type'], $report_type['id'], false ) . '>' . $report_type['attributes']['name'] . '</option>';
        }
        echo '</select></p>';
    }
    
    public function set_default_location() {
        $options = get_option( 'gfw_options' );
        echo '<p><select name="gfw_options[default_location]" id="default_location">';
        foreach ( $options['locations'] as $location_region => $region_locations ) {
            if( !empty( $region_locations ) ) {
                echo '<optgroup label="' . $location_region . '">';
                //ALL locations are grouped by region.
                foreach( $region_locations as $location ) {
                    echo '<option value="' . $location['id'] . '" ' . selected( $options['default_location'], $location['id'], false );
                    if( $location['attributes']['account_has_access'] != 1 ) {
                        echo ' disabled';
                    }
                    echo '>' . $location['attributes']['name'] .  '</option>';
                }
                echo '</optgroup>';
            }
        }
        echo '</select><br /><span class="description">Test Server Region (monitored pages will override this setting)</span></p>';
    }

    public function set_default_browser() {
        $options = get_option( 'gfw_options' );
        echo '<p><select name="gfw_options[default_browser]" id="default_browser">';
        foreach ( $options['browsers'] as $browser ) {
            echo '<option value="' . $browser['id'] . '" ' . selected( $options['default_browser'], $browser['id'], false ) . '>' . $browser['attributes']['name'] . '</option>';
        }
        echo '</select><br /><span class="description">Basic users can only test from Chrome Desktop. <a href="#" target="_blank">Upgrade for mobile device testing</a>.</span></p>';
    }

    public function set_default_connection () {
        $options = get_option( 'gfw_options' );
        echo '<p><select name="gfw_options[default_connection]" id="default_connection">';
        foreach ( $options['connections'] as $connection ) {
            echo '<option value="' . $connection['id'] . '" ' . selected( $options['default_connection'], $connection['id'], false ) . '>' . $connection['attributes']['name'] . '</option>';
        }
        echo '</select><br /><span class="description">Which connection speed to test from by default (Individual monitored pages will override this)</span></p>';
    }

    public function set_default_retention () {
        $options = get_option( 'gfw_options' );
        echo '<p><select name="gfw_options[default_retention]" id="default_retention">';
        foreach ( $options['retentions'] as $retention ) {
            echo '<option value="' . $retention['id'] . '" ' . selected( $options['default_retention'], $retention['id'], false ) . '>' . $retention['attributes']['name'] . '</option>';
        }
        echo '</select><br /><span class="description">Which connection speed to test from by default (Individual monitored pages will override this)</span></p>';
    }

    public function set_notifications_email() {
        $options = get_option( 'gfw_options' );
        echo '<p><select name="gfw_options[notifications_email]" id="notifications_email">';
        foreach ( array( 'api_username' => 'GTmetrix email (' . $options['api_username'] . ')', 'admin_email' => 'Admin email (' . get_option( 'admin_email' ) . ')' ) as $key => $value ) {
            echo '<option value="' . $key . '" ' . selected( $options['notifications_email'], $key, false ) . '>' . $value . '</option>';
        }
        echo '</select></p>';
    }

    public function set_default_separator() {
        echo '';
    }

    public function set_dashboard_widget() {
        $options = get_option( 'gfw_options' );
        echo '<input type="hidden" name="gfw_options[dashboard_widget]" value="0" />';
        echo '<span class="input-toggle">';
        echo '<input type="checkbox" name="gfw_options[dashboard_widget]" id="dashboard_widget" value="1" ' . checked( $options['dashboard_widget'], 1, false ) . ' /><label for="dashboard_widget"></label></span> <span class="description">Show latest front page GTmetrix results on WordPress dashboard pages</p>';
    }

    public function set_toolbar_link() {
        $options = get_option( 'gfw_options' );
        $options['toolbar_link'] = isset( $options['toolbar_link'] ) ? $options['toolbar_link'] : 0;
        echo '<input type="hidden" name="gfw_options[toolbar_link]" value="0" />';
        echo '<span class="input-toggle">';
        echo '<input type="checkbox" name="gfw_options[toolbar_link]" id="toolbar_link" value="1" ' . checked( $options['toolbar_link'], 1, false ) . ' /><label for="toolbar_link"></label></span> <span class="description">Test pages when logged in as admin from your WordPress Admin Toolbar</p>';
    }

    public function set_default_adblock() {
        $options = get_option( 'gfw_options' );
        echo '<input type="hidden" name="gfw_options[default_adblock]" value="0" />';
        echo '<span class="input-toggle">';
        echo '<input type="checkbox" name="gfw_options[default_adblock]" id="default_adblock" value="1" ' . checked( $options['default_adblock'], 1, false ) . ' /><label for="default_adblock"></label></span> <span class="description">Turning on AdBlock can help you see the difference Ad networks make on your blog</span>';
    }

    public function set_default_enable_video() {
        $options = get_option( 'gfw_options' );
        echo '<input type="hidden" name="gfw_options[default_enable_video]" value="0" />';
        echo '<span class="input-toggle">';
        echo '<input type="checkbox" name="gfw_options[default_enable_video]" id="default_enable_video" value="1" ' . checked( $options['default_enable_video'], 1, false ) . ' /><label for="default_enable_video"></label></span> <span class="description">Enable video creation of page load by default</span>';
    }

    public function set_front_url() {
        $options = get_option( 'gfw_options' );
        echo '<p><select name="gfw_options[front_url]" id="front_url">';
        foreach ( array( 'wp' => 'WordPress Address (' . site_url() . ')', 'site' => 'Site Address (' . home_url() . ')' ) as $key => $value ) {
            echo '<option value="' . $key . '" ' . selected( $options['front_url'], $key, false ) . '>' . $value . '</option>';
        }
        echo '</select></p>';
    }

    public function set_clear_settings() {
        $options = get_option( 'gfw_options' );
        echo '<input type="hidden" name="gfw_options[clear_settings]" value="0" />';
        echo '<input type="checkbox" name="gfw_options[clear_settings]" id="clear_settings" value="1" ' . checked( $options['clear_settings'], 1, false ) . ' />';
    }

    public function set_clear_records() {
        $options = get_option( 'gfw_options' );
        echo '<input type="hidden" name="gfw_options[clear_records]" value="0" />';
        echo '<input type="checkbox" name="gfw_options[clear_records]" id="clear_records" value="1" ' . checked( $options['clear_records'], 1, false ) . ' />';
    }

    public function set_reset() {
        echo '<p class="description">This will flush all GTmetrix records from the WordPress database and cannot be undone</p>';
        echo '<input type="button" value="Reset" class="button-primary" id="gfw-reset" />';
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
        wp_enqueue_script( 'gfw-script', GFW_URL . 'gtmetrix-for-wordpress-src.js', array( 'jquery-ui-autocomplete', 'jquery-ui-dialog' ), GFW_VERSION, true );
        wp_localize_script( 'gfw-script', 'gfwObject', array( 'gfwnonce' => wp_create_nonce( 'gfwnonce' ) ) );
        $gfw_status = get_option( 'gfw_status', array() );
        if ( GFW_AUTHORIZED ) {
            //add_meta_box( 'gfw-credits-meta-box', 'API Credits OBSOLETE', array( &$this, 'credits_meta_box' ), $this->tests_page_hook, 'side', 'core' );
            //add_meta_box( 'gfw-credits-meta-box', 'API Credits OBSOLETE', array( &$this, 'credits_meta_box' ), $this->schedule_page_hook, 'side', 'core' );
            //add_meta_box( 'gfw-api-meta-box', 'New API OBSOLETE', array( &$this, 'api_meta_box' ), $this->tests_page_hook, 'side', 'high' );
            if( $gfw_status['account_type'] == 'Basic' ) {
                add_meta_box( 'gfw-upgrade-box', 'Upgrade to GTmetrix Pro', array( &$this, 'upgrade_meta_box' ), $this->tests_page_hook, 'side', 'high' );
                add_meta_box( 'gfw-upgrade-box', 'Upgrade to GTmetrix Pro', array( &$this, 'upgrade_meta_box' ), $this->settings_page_hook, 'side', 'core' );
                add_meta_box( 'gfw-upgrade-box', 'Upgrade to GTmetrix Pro', array( &$this, 'upgrade_meta_box' ), $this->schedule_page_hook, 'side', 'core' );
            }
            add_meta_box( 'gfw-optimization-meta-box', 'GTmetrix Account', array( &$this, 'gtmetrix_account_meta_box' ), $this->tests_page_hook, 'side', 'core' );
            add_meta_box( 'gfw-optimization-meta-box', 'GTmetrix Account', array( &$this, 'gtmetrix_account_meta_box' ), $this->schedule_page_hook, 'side', 'core' );
            add_meta_box( 'gfw-optimization-meta-box', 'GTmetrix Account', array( &$this, 'gtmetrix_account_meta_box' ), $this->settings_page_hook, 'side', 'core' );
            add_meta_box( 'gfw-optimization-meta-box', 'Need optimization help?', array( &$this, 'optimization_meta_box' ), $this->tests_page_hook, 'side', 'core' );
            add_meta_box( 'gfw-optimization-meta-box', 'Need optimization help?', array( &$this, 'optimization_meta_box' ), $this->schedule_page_hook, 'side', 'core' );
            add_meta_box( 'gfw-news-meta-box', 'Latest News', array( &$this, 'news_meta_box' ), $this->tests_page_hook, 'side', 'core' );
            add_meta_box( 'gfw-news-meta-box', 'Latest News', array( &$this, 'news_meta_box' ), $this->schedule_page_hook, 'side', 'core' );
        }
        add_meta_box( 'gfw-optimization-meta-box', 'Need optimization help?', array( &$this, 'optimization_meta_box' ), $this->settings_page_hook, 'side', 'core' );
        add_meta_box( 'gfw-news-meta-box', 'Latest News', array( &$this, 'news_meta_box' ), $this->settings_page_hook, 'side', 'core' );

        if ( method_exists( $screen, 'add_help_tab' ) ) {
            $settings_help = '<p>You will need an account at <a href="https://gtmetrix.com/' . GFW_GA_CAMPAIGN . '" target="_blank">Gtmetrix.com</a> to use GTmetrix for WordPress. Registration is free. Once registered, go to the <a href="https://gtmetrix.com/api/' . GFW_GA_CAMPAIGN . '" target="_blank">API page</a> and generate an API key. Enter this key, along with your registered email address, in the authentication fields below, and you\'re ready to go!</p>';
            $options_help = '<p>You would usually set your <i>default location</i> to the city nearest to your target audience. When you run a test on a page, the report returned will reflect the experience of a user connecting from this location.</p>';

            $test_help = '<p>To analyze the performance of a page or post on your blog, simply enter its URL. You can even just start to type the title into the box, and an autocomplete facility will try and help you out.</p>';
            $test_help .= '<p>The optional <i>Label</i> is simply used to help you identify a given report in the system.</p>';

            $reports_help = '<p>The Reports section shows summaries of your reports. For even more detailed information, click on the Report\'s URL/label, and the full GTmetrix.com report will open. You can also delete a report.</p>';
            $reports_help .= '<p><b>Note:</b> deleting a report here only removes it from GTmetrix for WordPress - not from your GTmetrix account.<br /><b>Note:</b> if the URL/label is not a link, this means the report is no longer available on GTmetrix.com.</p>';

            $schedule_help = '<p>You can set up your reports to be generated even when you\'re away. Simply run the report as normal (in Reports), then expand the report\'s listing, and click <i>Monitor page</i>. You will be redirected to this page, where you can choose how often you want this report to run.</p>';
            $schedule_help .= '<p>You can also choose to be sent an email when certain conditions apply. This email can go to either your admin email address or your GTmetrix email address, as defined in settings.</p>';
            $schedule_help .= '<p><b>Note:</b> every test will use up 1 of your API credits on GTmetrix.com<br /><b>Note:</b> monitored pages use the WP-Cron functionality that is built into WordPress. This means that events are only triggered when your site is visited.</p>';

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
                                'title' => 'Monitor a page',
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
            //error_log( 'schedule_page ' . print_r( $data, TRUE));
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
                    $notifications[$value] = array(
                        'value' => $data[$value][$key],
                        'comparator' => $data['comparator'][$key]
                    );
                    if( $value == 'html_bytes' ) {
                        $notifications[$value]['html_bytes_size'] = $data['html_bytes_size'][$key];
                    } else if( $value == 'page_bytes' ) {
                        $notifications[$value]['page_bytes_size'] = $data['page_bytes_size'][$key];
                    } 
                    //$notifications[$value] = $data[$value][$key];
                }
                update_post_meta( $event_id, 'gfw_notifications', $notifications );
            } else {
                delete_post_meta( $event_id, 'gfw_notifications' );
            }
            echo '<div id="message" class="updated"><p><strong>Monitoring updated.</strong></p></div>';
        }

        if ( ($event_id || $report_id) && !isset( $data ) ) {
            add_meta_box( 'schedule-meta-box', 'Monitor a page', array( &$this, 'schedule_meta_box' ), $this->schedule_page_hook, 'normal', 'core' );
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

        add_meta_box( 'events-meta-box', 'Monitored Pages', array( &$this, 'events_list' ), $this->schedule_page_hook, 'normal', 'core' );
        ?>

        <div class="wrap gfw">
            <div id="gfw-icon" class="icon32"></div>
            <h2>GTmetrix for WordPress &raquo; Monitor</h2>
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
        add_meta_box( 'gfw-test-meta-box', 'Analyze Performance of:', array( &$this, 'test_meta_box' ), $this->tests_page_hook, 'normal', 'core' );
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
            if ( !class_exists( 'Services_WTF_Test_v2' ) ) {
                require_once('lib/Services_WTF_Test_2.php');
            }
            $test_v2 = new Services_WTF_Test_v2();
            $test_v2->api_username( $valid['api_username'] );
            $test_v2->api_password( $valid['api_key'] );
            $status_v2 = $test_v2->status();
            if( isset( $status_v2['data'] ) ) {
                $valid['authorized'] = 1;
                $status_options = $status_v2['data']['attributes'];
                update_option( 'gfw_status', $status_options );
            } else {
                if ( !get_settings_errors( 'gfw_options' ) ) {
                    add_settings_error( 'gfw_options', 'api_error', $test_v2->error() );
                }
            }                
            $valid['report_types'][] = array(
                'id' => 'lighthouse',
                'attributes' => array(
                    'name' => 'Lighthouse (API 2.0) - 1 credit per test'
                )
            );
            $valid['report_types'][] = array(
                'id' => 'legacy',
                'attributes' => array(
                    'name' => 'Pagespeed/YSlow (API 0.1) - 0.7 credits per test'
                )
            );
            $valid['retentions'][] = array(
                'id' => '1',
                'attributes' => array(
                    'name' => '1 month'
                )
            );
            $valid['retentions'][] = array(
                'id' => '6',
                'attributes' => array(
                    'name' => '6 months (+0.4 API credits)'
                )
            );
            $valid['retentions'][] = array(
                'id' => '12',
                'attributes' => array(
                    'name' => '12 months (+0.9 API credits)'
                )
            );
            $valid['retentions'][] = array(
                'id' => '24',
                'attributes' => array(
                    'name' => '24 months (+01.4 API credits)'
                )
            );
            $valid['connections'][] = array(
                'id' => "",
                'attributes' => array(
                    'name' => 'Unthrottled Connection'
                )
            );
            $valid['connections'][] = array(
                'id' => '20000/5000/25',
                'attributes' => array(
                    'name' => 'Broadband Fast (20/5 Mbps, 25ms)'
                )
            );
            $valid['connections'][] = array(
                'id' => '5000/1000/30',
                'attributes' => array(
                    'name' => 'Broadband (5/1 Mbps, 30ms)'
                )
            );
            $valid['connections'][] = array(
                'id' => '1500/384/50',
                'attributes' => array(
                    'name' => 'Broadband Slow (1.5 Mbps/384 Kbps, 50ms)'
                )
            );
            $valid['connections'][] = array(
                'id' => '15000/10000/100',
                'attributes' => array(
                    'name' => 'LTE (15/10 Mbps, 100ms)'
                )
            );
            $valid['connections'][] = array(
                'id' => '5000/1000/150',
                'attributes' => array(
                    'name' => '4G Slow (5/1 Mbps, 150ms)'
                )
            );
            $valid['connections'][] = array(
                'id' => '1600/768/200',
                'attributes' => array(
                    'name' => '3G (1.6 Mbps/768 Kbps, 200ms)'
                )
            );
            $valid['connections'][] = array(
                'id' => '750/500/250',
                'attributes' => array(
                    'name' => '3G Slow (750/500 Kbps, 250ms)'
                )
            );
            $locations = $test_v2->locations();
            if ( $test_v2->error() ) {
                if ( !get_settings_errors( 'gfw_options' ) ) {
                    add_settings_error( 'gfw_options', 'api_error', $test_v2->error() );
                }
            } else {
                if( isset( $locations['data'] ) ) {
                    
                    $valid['locations'] = array(
                        "Available Locations" => array(),
                        "North America" => array(),
                        "Latin America" => array(),
                        "Europe" => array(),
                        "Asia Pacific" => array(),
                        "Africa" => array(),
                        "Middle East" => array()
                    );
                    foreach ( $locations['data'] as $location ) {
                        $location_region = $location['attributes']['region'];
                        //error_log($location_region);
                        if( $status_options['account_pro_locations_access'] ) {
                            //we've got access to all locations. Group them by region
                            $valid['locations'][$location_region][$location['id']] = $location;
                        } else {
                            if( $location['attributes']['account_has_access'] ) {
                                $valid['locations']["Available Locations"][$location['id']] = $location;
                            } else {
                                $valid['locations'][$location_region][$location['id']] = $location;
                            }
                        }
                        //$valid['locations'][$location['id']] = $location;
                    }
                }
                if( $status_options['account_pro_locations_access'] ) {
                    // with PRO location access, we group locations by region and order them.
                } else {

                }
                if ( !get_settings_errors( 'gfw_options' ) ) {
                    add_settings_error( 'gfw_options', 'settings_updated', 'Settings Saved. Please click on <a href="' . GFW_TESTS . '">Tests</a> to test your WordPress installation.', 'updated' );
                }
            }
            $browsers = $test_v2->browsers();
            if ( $test_v2->error() ) {
                if ( !get_settings_errors( 'gfw_options' ) ) {
                    add_settings_error( 'gfw_options', 'api_error', $test_v2->error() );
                }
            } else {
                if( isset( $browsers['data'] ) ) {
                    foreach ( $browsers['data'] as $browser ) {
                        $valid['browsers'][$browser['id']] = $browser;
                    }
                }                
                if ( !get_settings_errors( 'gfw_options' ) ) {
                    add_settings_error( 'gfw_options', 'settings_updated', 'Settings Saved. Please click on <a href="' . GFW_TESTS . '">Tests</a> to test your WordPress installation.', 'updated' );
                }
            }
        }
        $options = get_option( 'gfw_options' );
        //$valid['pro_locations'] = isset( $status['data']['attributes']['account_pro_locations_access'] ) ? $status['data']['attributes']['account_pro_locations_access'] : 0;
        //$valid['pro_analysis_options'] = isset( $status['data']['attributes']['account_pro_analysis_options_access'] ) ? $status['data']['attributes']['account_pro_analysis_options_access'] : 0;
        $valid['default_location'] = isset( $input['default_location'] ) ? $input['default_location'] : (isset( $options['default_location'] ) ? $options['default_location'] : 1);
        $valid['default_adblock'] = isset( $input['default_adblock'] ) ? $input['default_adblock'] : (isset( $options['default_adblock'] ) ? $options['default_adblock'] : 0);
        $valid['dashboard_widget'] = isset( $input['dashboard_widget'] ) ? $input['dashboard_widget'] : (isset( $options['dashboard_widget'] ) ? $options['dashboard_widget'] : 1);
        $valid['toolbar_link'] = isset( $input['toolbar_link'] ) ? $input['toolbar_link'] : (isset( $options['toolbar_link'] ) ? $options['toolbar_link'] : 1);
        $valid['notifications_email'] = isset( $input['notifications_email'] ) ? $input['notifications_email'] : (isset( $options['notifications_email'] ) ? $options['notifications_email'] : 'api_username');
        $valid['report_type'] = isset( $input['report_type'] ) ? $input['report_type'] : (isset( $options['report_type'] ) ? $options['report_type'] : 'lighthouse');
        $valid['default_retention'] = isset( $input['default_retention'] ) ? $input['default_retention'] : (isset( $options['default_retention'] ) ? $options['default_retention'] : '1month');
        $valid['default_connection'] = isset( $input['default_connection'] ) ? $input['default_connection'] : (isset( $options['default_connection'] ) ? $options['default_connection'] : 'unthrottled');
        $valid['default_browser'] = isset( $input['default_browser'] ) ? $input['default_browser'] : (isset( $options['default_browser'] ) ? $options['default_browser'] : 1);

        $valid['widget_pagespeed'] = isset( $input['widget_pagespeed'] ) ? $input['widget_pagespeed'] : $options['widget_pagespeed'];
        $valid['widget_yslow'] = isset( $input['widget_yslow'] ) ? $input['widget_yslow'] : $options['widget_yslow'];
        $valid['widget_scores'] = isset( $input['widget_scores'] ) ? $input['widget_scores'] : $options['widget_scores'];
        $valid['widget_link'] = isset( $input['widget_link'] ) ? $input['widget_link'] : $options['widget_link'];
        $valid['widget_css'] = isset( $input['widget_css'] ) ? $input['widget_css'] : $options['widget_css'];
        $valid['front_url'] = isset( $input['front_url'] ) ? $input['front_url'] : $options['front_url'];
        $valid['clear_settings'] = isset( $input['clear_settings'] ) ? $input['clear_settings'] : $options['clear_settings'];
        $valid['clear_records'] = isset( $input['clear_records'] ) ? $input['clear_records'] : $options['clear_records'];
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
        $options = get_option( 'gfw_options' );
        if( isset( $data['page'] ) && $data['page'] ) {
            $existing_report = array(
                'post_type' => 'gfw_report',
                'post_status' => 'publish',
                'meta_key' => 'gtmetrix_page_id',
                'meta_value' => $data['page'],
                'posts_per_page' => 1,
                'fields' => 'ids'
            );    
            $existing_reports = get_posts( $existing_report );
            if( !empty( $existing_reports ) ) {
                $post_id = $existing_reports[0];
            } else {
                $post_id = wp_insert_post( array(
                    'post_type' => 'gfw_report',
                    'post_status' => 'publish',
                    'post_author' => 1
                        ) );
            }
        } else {
            $post_id = wp_insert_post( array(
                'post_type' => 'gfw_report',
                'post_status' => 'publish',
                'post_author' => 1
                    ) );
        }

        update_post_meta( $post_id, 'gfw_url', $this->append_http( $data['gfw_url'] ) );
        //update_post_meta( $post_id, 'gfw_label', $data['gfw_label'] );
        update_post_meta( $post_id, 'gfw_location', $data['gfw_location'] );
        update_post_meta( $post_id, 'gfw_connection', $data['gfw_connection'] );
        update_post_meta( $post_id, 'gfw_browser', $data['gfw_browser'] );
        update_post_meta( $post_id, 'gfw_adblock', isset( $data['gfw_adblock'] ) ? $data['gfw_adblock'] : 0  );
        update_post_meta( $post_id, 'gfw_video', isset( $data['gfw_enable_video'] ) ? $data['gfw_enable_video'] : 0  );
        update_post_meta( $post_id, 'gfw_event_id', $event_id );
/*
                            $conditions = array(
                                        'Scores' => array(
                                            'pagespeed_score' => 'PageSpeed score',
                                            'yslow_score' => 'YSlow score'
                                        ),
                                        'Page Timings' => array(
                                            'fully_loaded_time' => 'Fully Loaded',
                                            'time_to_first_byte' => 'Time to First Byte',
                                            'redirect_duration' => 'Redirect Duration',
                                            'connect_duration' => 'Connect Duration',
                                            'backend_duration' => 'Backend Duration',
                                            'onload_time' => 'Onload',
                                            'onload_duration' => 'Onload Duration',
                                        ),
                                        'Page Details' => array(
                                            'page_bytes' => 'Total Size',
                                            'page_requests' => 'Total Requests'
                                        ),
                                        'Legacy & Other Metrics' => array(
                                            'html_bytes' => 'HTML Size',
                                            'first_paint_time' => 'First Paint',
                                            'dom_interactive_time' => 'DOM Interactive',
                                            'dom_content_loaded_time' => 'DOM Content Loaded',
                                            'dom_content_loaded_duration' => 'DOM Content Loaded Duration'
                                        )
                                    );
                                } else {
                                    $conditions = array(
                                        'Scores' => array(
                                            'gtmetrix_grade' => 'GTmetrix Grade',
                                            'performance_score' => 'Performance Score',
                                            'structure_score' => 'Structure Score'
                                        ),
                                        'Performance Metrics' => array(
                                            'largest_contentful_paint' => 'Largest Contentful Paint',
                                            'first_contentful_paint' => 'First Contentful Paint',
                                            'total_blocking_time' => 'Total Blocking Time',
                                            'cumulative_layout_shift' => 'Cumulative Layout Shift',
                                            'time_to_interactive' => 'Time to Interactive',
                                            'speed_index' => 'Speed Index'
                                        ),
                                        'Page Timings' => array(
                                            'time_to_first_byte' => 'Time to First Byte',
                                            'redirect_duration' => 'Redirect Duration',
                                            'connect_duration' => 'Connect Duration',
                                            'backend_duration' => 'Backend Duration',
                                            'onload_time' => 'Onload',
                                            'fully_loaded_time' => 'Fully Loaded'
                                        ),
                                        'Page Details' => array(
                                            'page_bytes' => 'Total Size',
                                            'page_requests' => 'Total Requests'
                                        ),
                                        'Legacy & Other Metrics' => array(
                                            'html_bytes' => 'HTML Size',
                                            'first_paint_time' => 'First Paint',
                                            'dom_interactive_time' => 'DOM Interactive',
                                            'dom_content_loaded_time' => 'DOM Content Loaded',
                                            'dom_content_loaded_duration' => 'DOM Content Loaded Duration',
                                            'onload_duration' => 'Onload Duration'
                                        )
                                    );
                                    */
                            
        if ( !isset( $data['error'] ) ) {
            update_post_meta( $post_id, 'gtmetrix_test_id', $data['test_id'] );
            update_post_meta( $post_id, 'gtmetrix_report_id', $data['report_id'] );
            update_post_meta( $post_id, 'gtmetrix_page_id', $data['page'] );
            update_post_meta( $post_id, 'fully_loaded_time', $data['fully_loaded_time'] );
            update_post_meta( $post_id, 'html_bytes', $data['html_bytes'] );
            update_post_meta( $post_id, 'page_requests', $data['page_requests'] );
            update_post_meta( $post_id, 'report_url', $data['report_url'] );
            update_post_meta( $post_id, 'page_bytes', $data['page_bytes'] );
            update_post_meta( $post_id, 'time_to_first_byte', $data['time_to_first_byte'] );
            update_post_meta( $post_id, 'redirect_duration', $data['redirect_duration'] );
            update_post_meta( $post_id, 'connect_duration', $data['connect_duration'] );
            update_post_meta( $post_id, 'backend_duration', $data['backend_duration'] );
            update_post_meta( $post_id, 'onload_time', $data['onload_time'] );
            update_post_meta( $post_id, 'onload_duration', $data['onload_duration'] );
            update_post_meta( $post_id, 'first_paint_time', $data['first_paint_time'] );
            update_post_meta( $post_id, 'dom_interactive_time', $data['dom_interactive_time'] );
            update_post_meta( $post_id, 'dom_content_loaded_time', $data['dom_content_loaded_time'] );
            update_post_meta( $post_id, 'dom_content_loaded_duration', $data['dom_content_loaded_duration'] );
            if( $options['report_type'] == 'legacy' ) {
                update_post_meta( $post_id, 'pagespeed_score', $data['pagespeed_score'] );
                update_post_meta( $post_id, 'yslow_score', $data['yslow_score'] );
            } else {
                update_post_meta( $post_id, 'performance_score', $data['performance_score'] );
                update_post_meta( $post_id, 'structure_score', $data['structure_score'] );
                update_post_meta( $post_id, 'gtmetrix_grade', $data['gtmetrix_grade'] );
                update_post_meta( $post_id, 'largest_contentful_paint', $data['largest_contentful_paint'] );
                update_post_meta( $post_id, 'total_blocking_time', $data['total_blocking_time'] );
                update_post_meta( $post_id, 'cumulative_layout_shift', $data['cumulative_layout_shift'] );
                update_post_meta( $post_id, 'first_contentful_paint', $data['first_contentful_paint'] );
                update_post_meta( $post_id, 'time_to_interactive', $data['time_to_interactive'] );
                update_post_meta( $post_id, 'speed_index', $data['speed_index'] );
            }
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
        error_log( 'run_test parameters ' . print_r( $parameters, TRUE ) );
        $test_id = $api->test( array(
            'url' => $this->append_http( $parameters['gfw_url'] ),
            'browser' => $parameters['gfw_browser'],
            'location' => $parameters['gfw_location'],
            'report' => $parameters['gfw_report'],
            'connection' => $parameters['gfw_connection'],
            'retention' => $parameters['gfw_retention'],
            'cookies' => $parameters['gfw_cookies'],
            'httpauth_username' => isset( $parameters['gfw_httpauth_username'] ) ? $parameters['gfw_httpauth_username'] : '',
            'httpauth_password' => isset( $parameters['gfw_httpauth_password'] ) ? $parameters['gfw_httpauth_password'] : '',
            'adblock' => isset( $parameters['gfw_adblock'] ) ? $parameters['gfw_adblock'] : 0,
            'video' => isset( $parameters['gfw_enable_video'] ) ? $parameters['gfw_enable_video'] : 0,
            )
        );

        if ( $api->error() ) {
            error_log($api->error());
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
            error_log( "API RESULTS " . print_r( $api->results(), TRUE ) );
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
            if( isset( $custom_fields['performance_score'] ) ) {
                $report_type = "lighthouse";
            } else {
                $report_type = "legacy";
            }
            $loaded_time_text = "Onload time";
            if (isset($custom_fields['fully_loaded_time'][0])) {
                $loaded_time = $custom_fields['fully_loaded_time'][0];
                $loaded_time_text = "Fully loaded time";
            }

            $options = get_option( 'gfw_options' );
            $expired = ($this->gtmetrix_file_exists( $custom_fields['report_url'][0] . '/screenshot.jpg' ) ? false : true);
            ?>
            <div class="gfw-meta">
                <div><b>Performance summary for:</b> <?php echo date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $report->post_date ) ); ?></div>
            </div>
            <div>
                <?php if( $report_type == "legacy" ) { ?>
                <table>
                    <tr>
                        <th>PageSpeed score</th>
                        <td><?php echo $custom_fields['pagespeed_score'][0]; ?></td>
                        <th>YSlow score</th>
                        <td><?php echo $custom_fields['yslow_score'][0]; ?></td>
                        <th><?php echo $loaded_time_text; ?></th>
                       <td><?php echo number_format( $loaded_time / 1000, 2 ); ?> seconds</td>
                    </tr>
                    <tr>
                        <th>Requests</th>
                        <td><?php echo $custom_fields['page_requests'][0]; ?></td>
                        <th>Page size</th>
                        <td><?php echo size_format( $custom_fields['page_bytes'][0], 2 ); ?></td>
                        <th>HTML size</th>
                        <td><?php echo size_format( $custom_fields['html_bytes'][0], 1 ); ?></td>
                    </tr>
                </table>
                <?php } else { ?>
                <table>
                    <tr>
                        <th>GTmetrix grade</th>
                        <td><?php echo $custom_fields['gtmetrix_grade'][0]; ?></td>
                        <th>Performance score</th>
                        <td><?php echo $custom_fields['performance_score'][0]; ?></td>
                        <th>Structure score</th>
                       <td><?php echo $custom_fields['structure_score'][0]; ?></td>
                    </tr>
                    <tr>
                        <th>LCP</th>
                        <td><?php echo $custom_fields['largest_contentful_paint'][0]; ?></td>
                        <th>TBT</th>
                        <td><?php echo $custom_fields['total_blocking_time'][0]; ?></td>
                        <th>CLS</th>
                       <td><?php echo $custom_fields['cumulative_layout_shift'][0]; ?></td>
                    </tr>
                    <tr>
                        <th>Fully loaded time</th>
                        <td><?php echo number_format( $loaded_time / 1000, 2 ); ?> seconds</td>
                        <th>Requests</th>
                        <td><?php echo $custom_fields['page_requests'][0]; ?></td>
                        <th>Page size</th>
                       <td><?php echo size_format( $custom_fields['page_bytes'][0], 2 ); ?></td>
                    </tr>
                </table>
                <?php } ?>
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
                echo '<div><a href="' . GFW_SCHEDULE . '&report_id=' . $report->ID . '" class="gfw-schedule-icon-large">Monitor this page</a></div>';
            }
            if ( !$expired ) {
                echo '<div><a href="' . $custom_fields['report_url'][0] . '" target="_blank" class="gfw-report-icon">Detailed report</a></div>';
                echo '<div><a href="' . $custom_fields['report_url'][0] . '/pdf' . '" class="gfw-pdf-icon">Download PDF</a></div>';
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

    public function sync_callback() {
        if ( check_ajax_referer( 'gfwnonce', 'security' ) ) {
            $options = get_option( 'gfw_options' );
            if ( !class_exists( 'Services_WTF_Test_v2' ) ) {
                require_once('lib/Services_WTF_Test_2.php');
            }
            $test = new Services_WTF_Test_v2();
            $test->api_username( $options['api_username'] );
            $test->api_password( $options['api_key'] );
            $test->user_agent( GFW_USER_AGENT );
            $status = $test->status();
            if ( $test->error() ) {
                /*
                if ( !get_settings_errors( 'gfw_options' ) ) {
                    add_settings_error( 'gfw_options', 'api_error', $test->error() );
                }
                */
            } else {
                $status_options = $status['data']['attributes'];
                //error_log( "STATUS OPTIONS " . print_r( $status_options, TRUE ));
                update_option( 'gfw_status', $status_options );              
            }
        }
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
        if ( !class_exists( 'Services_WTF_Test_v2' ) ) {
            require_once('lib/Services_WTF_Test_2.php');
        }
        $api = new Services_WTF_Test_v2();
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
        <p>Every test costs 1 API credit, except tests that use video, which cost 5 credits.</p>
        <p>You can view your API credit limits and usage in <a href="https://gtmetrix.com/dashboard/account" target="_blank">your Account page</a> on GTmetrix.com</p>
        <p>If you need more API credits, you can <a href="https://gtmetrix.com/pricing.html" target="_blank">upgrade your plan here</a>.</p>
        <?php
    }

    public function gtmetrix_account_meta_box() {
        $gfw_status = get_option( 'gfw_status', array() );
        $gfw_options = get_option( 'gfw_options' );
        $test_cost = 1;
        if( $gfw_options['report_type'] != 'lighthouse' ) {
            $test_cost = 0.7;
        }
        
        ?>
        <p>
            <strong>Account Type:</strong> <?php echo $gfw_status['account_type']; ?>
        </p>
        <p style="margin-bottom:0;">
            <strong>API Credit usage</strong><br />            

            <div style="border:1px solid grey;border-radius:3px;text-align:right;position:relative;">

                <div style="position:absolute;width:50%;background-color:#3c9adc;height:100%;width:<?php echo ( ( $gfw_status['api_refill_amount'] - $gfw_status['api_credits']) / $gfw_status['api_refill_amount'] ) * 100; ?>%"></div>
                <span style="padding:2px;"><?php echo ($gfw_status['api_refill_amount'] - $gfw_status['api_credits'] ) . ' of ' . $gfw_status['api_refill_amount']; ?></span>
            </div>
            <span class="next-api-refill">Next refill: <?php 
            $tz = wp_timezone_string();
            $timestamp = time();
            $dt = new DateTime("now", new DateTimeZone($tz)); //first argument "must" be a string
            $dt->setTimestamp( $gfw_status['api_refill']); //adjust the object to correct timestamp
            echo $dt->format('d.m.Y, H:i:s'); ?></span>
        </p>
        <p>
            Every test costs <?php echo $gfw_options['report_type'] == 'lighthouse' ? "<strong>1</strong> API credit" : "<strong>0.7</strong> API credits"; ?>
        </p>
        <p>
            Tests that add video playback cost an additional <strong>0.9</strong> API credits
        </p>
        <p>
            Tests with PDF Summary download enabled cost <strong><?php echo ( $test_cost + 0.2 ); ?></strong> API credits (<strong><?php echo ( $test_cost + 0.3 ); ?></strong> API credits for Full Reports)
        </p>
        <p>
            You can view your API credit limit and usage in your <a href="https://gtmetrix.com/dashboard/account" target="_blank">your Account page</a> on GTmetrix.com
        </p>

        <?php
    }

    public function optimization_meta_box() {
        ?>
        <p>Have a look at our WordPress Optimization Guide <a target="_blank" href="https://gtmetrix.com/wordpress-optimization-guide.html">WordPress Optimization Guide</a>.</p>
        <p>You can also <a target="_blank" href="https://gtmetrix.com/contact.html?type=optimization-request">contact us</a> for optimization help and we'll put you in the right direction towards a faster website.</p>
        <?php
    }

    public function upgrade_meta_box() {
        ?>
        <p>
            Get more API credits, monitoring in global locations and more with GTmetrix PRO
        </p>
        <p>
            <a href="https://gtmetrix.com/why-gtmetrix-pro.html" target="_blank">More reasons to upgrade</a>
        </p>
        <p>
            <a href="https://gtmetrix.com/pricing.html" target="_blank">Upgrade to GTmetrix PRO</a>
        </p>
        <?php
    }

    public function api_meta_box() {
        ?>
        <p>
            <strong>This plugin is using v0.1 of our API</strong> GTmetrix API v0.1 only provides Legacy Report data (PageSpeed/YSlow scores).
        </p><p>
            We are currently working on v2.0 which will allow for Lighthouse metrics (Web Vitals) to be retrieved. Our plugin will be updated soon to reflect our new Lighthouse testing methodology.
        </p>
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
        echo '<p><a href="https://twitter.com/gtmetrix" target="_blank" class="button-secondary">Follow us on Twitter</a></p>';
        echo '<p><a href="https://facebook.com/gtmetrix/" target="_blank" class="button-secondary">Follow us on Facebook</a></p>';
    }

    protected function front_score( $dashboard = false ) {
        $options = get_option( 'gfw_options' );
        $args = array(
            'post_type' => 'gfw_report',
            'posts_per_page' => 1,
            'orderby' => 'post_date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => 'gfw_url',
                    'value' => array( trailingslashit( GFW_FRONT ), untrailingslashit( GFW_FRONT ) ),
                    //'value' => 'https://google.ca',
                    'compare' => 'IN'
                ),
                array(
                    'key' => 'gtmetrix_test_id',
                    'value' => 0,
                    'compare' => '!='
                )
            ),
        );
        if( $options['report_type'] == 'lighthouse' ) {
            $args['meta_query'][] = array(
                'key' => 'gtmetrix_grade',
                'compare' => 'EXIST'
            );
        } else {
            $args['meta_query'][] = array(
                'key' => 'pagespeed_score',
                'compare' => 'EXIST'
            );
        }

        $query = new WP_Query( $args );

        echo '<input type="hidden" id="gfw-front-url" value="' . trailingslashit( GFW_FRONT ) . '" />';
        ?>
            <?php
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->next_post();
                $custom_fields = get_post_custom( $query->post->ID );
                //error_log( print_r($custom_fields, TRUE));
                $expired = true;
                if ( $this->gtmetrix_file_exists( $custom_fields['report_url'][0] . '/screenshot.jpg' ) ) {
                    $expired = false;
                }    
                

                
                if ( !$dashboard && !$expired ) {
                    //echo '<div class="gfw-latest-report-wrapper">';
                    //echo '';
                    //echo '';
                    //echo '</div>';
                ?>
                <?php if( isset( $custom_fields['pagespeed_score'][0] ) ) {
                    $pagespeed_grade = $this->score_to_grade( $custom_fields['pagespeed_score'][0] );
                    $yslow_grade = $this->score_to_grade( $custom_fields['yslow_score'][0] );
                    $loaded_time = $custom_fields['page_load_time'][0];
                    $loaded_time_text = "Onload time";
                    if (isset($custom_fields['fully_loaded_time'][0])) {
                        $loaded_time = $custom_fields['fully_loaded_time'][0];
                        $loaded_time_text = "Fully loaded time";
                    }
                    ?>
                    <div class="gfw-latest-report-wrapper">
                        <div class="gfw-box gfw-latest-report-screenshot">
                            <img src="<?php 
                            if( isset( $custom_fields['report_url'][0] ) ) {
                                echo $custom_fields['report_url'][0] . "/screenshot.jpg";
                            } else {
                                echo plugin_dir_url( __FILE__ ) . "/images/no-report-splash.png";
                            }
                            ?>" style="display: inline-block; margin-right: 10px; border-radius: 8px 8px 8px 8px;" />
                        </div>
                        <div class="gfw-box gfw-latest-report-<?php echo $options['report_type']; ?>">
                            <div class="gfw-latest-report-scores">
                                <div class="gfw-latest-report-grades">
                                    <div class="gfw-latest-report gfw-latest-report-grade gfw-latest-report-pagespeed gfw-report-grade-<?php echo $pagespeed_grade['grade']; ?>">
                                        <span class="gfw-report-title">PageSpeed<?php echo $query->post->ID; ?></span><br>
                                        <span class="gfw-report-grade"><?php echo $pagespeed_grade['grade']; ?></span> <span class="gfw-report-score">(<?php echo $custom_fields['pagespeed_score'][0]; ?>%)</span>
                                    </div>
                                    <div class="gfw-latest-report gfw-latest-report-grade gfw-latest-report-yslow gfw-report-grade-<?php echo $yslow_grade['grade']; ?>">
                                        <span class="gfw-report-title">YSlow</span><br />
                                        <span class="gfw-report-grade"><?php echo $yslow_grade['grade']; ?></span> <span class="gfw-report-score">(<?php echo $custom_fields['yslow_score'][0]; ?>%)</span>
                                    </div>
                                </div>
                                <div class="gfw-latest-report-numbers gfw-latest-report-numbers-legacy">
                                    <div class="gfw-latest-report-number"><strong><?php echo $loaded_time_text; ?></strong> <span class="gfw-report-score" style="color:#858585"><?php 
                                    if( $loaded_time < 1000 ) {
                                        echo $loaded_time . "ms";
                                    } else {
                                        echo number_format( $loaded_time / 1000, 1 ) . "s";
                                    } ?></span></div>
                                    <div class="gfw-latest-report-number"><strong>Total page size</strong> <span class="gfw-report-score" style="color:#858585"><?php 
                                    if( $custom_fields['page_bytes'][0] < 1024 ) {
                                        echo $custom_fields['page_bytes'][0] . "B";
                                    } else if ( $custom_fields['page_bytes'][0] < 1048576 ) {
                                        echo number_format( $custom_fields['page_bytes'][0] / 1024, 1) . "KB";
                                    } else {
                                        echo number_format( $custom_fields['page_bytes'][0] / 1048576, 1) . "MB";
                                    }
                                    ?></span></div>
                                    <div class="gfw-latest-report-number"><strong>Requests</strong> <span class="gfw-report-score" style="color:#858585"><?php echo $custom_fields['page_requests'][0]; ?></span></div>
                                </div>
                            </div>
                            <div class="gfw-report-links">
                                <p><a href="<?php echo GFW_SCHEDULE; ?>&report_id=<?php echo $query->post->ID; ?>" class="gfw-schedule-icon-large">Monitor this page</a>
                                <?php $this->display_retest_form( 'Re-test your Front Page', $options['report_type'], untrailingslashit( GFW_FRONT ), $options['default_browser'], $options['default_location'], $options['default_connection'], $options['default_retention'] ); ?>
                                <p><a href="<?php echo $custom_fields['report_url'][0]; ?>" target="_blank" class="gfw-report-icon">Detailed report</a> &nbsp;&nbsp; </p>
                            </div>
                        </div>
                    </div>
                    <?php
                    } else {
                    $performance_grade = $this->score_to_grade( $custom_fields['performance_score'][0] );
                    $structure_grade = $this->score_to_grade( $custom_fields['structure_score'][0] );
                    
                    ?>
                    <div class="gfw-latest-report-wrapper">
                        <div class="gfw-box gfw-latest-report-screenshot">
                            <img src="<?php echo $custom_fields['report_url'][0]; ?>/screenshot.jpg" style="display: inline-block; margin-right: 10px; border-radius: 8px 8px 8px 8px;" />
                        </div>
                        <div class="gfw-box gfw-latest-report gfw-latest-report-<?php echo $options['report_type']; ?>">
                            <div class="gfw-latest-report-scores">
                                <div class="gfw-latest-report-grades">
                                    <div class="gfw-latest-report-grade gfw-latest-report-gtmetrixgrade gfw-report-grade-<?php echo $custom_fields['gtmetrix_grade'][0]; ?>">
                                        <span class="gfw-report-grade"><?php echo custom_fields['gtmetrix_grade'][0]; ?></span>
                                    </div>
                                    <div class="gfw-latest-report-grade gfw-latest-report-performance gfw-report-grade-<?php echo $performance_grade['grade']; ?>">
                                        <span class="gfw-report-title">Performance</span><br>
                                        <span class="gfw-report-score"><?php echo $custom_fields['performance_score'][0]; ?>%</span>
                                    </div>
                                    <div class="gfw-latest-report-grade gfw-latest-report-structure gfw-report-grade-<?php echo $structure_grade['grade']; ?>">
                                        <span class="gfw-report-title">Structure</span><br />
                                        <span class="gfw-report-score"><?php echo $custom_fields['structure_score'][0]; ?>%</span>
                                    </div>
                                </div>
                                <div class="gfw-latest-report-numbers gfw-latest-report-numbers-lighthouse">
                                    <div class="gfw-latest-report-number"><strong>Largest Contentful Paint</strong> <span class="gfw-report-score" style="color:<?php echo $this->lcp_to_color( $custom_fields['largest_contentful_paint'][0] ); ?>"><?php 
                                    if( $custom_fields['largest_contentful_paint'][0] < 1000 ) {
                                        echo $custom_fields['largest_contentful_paint'][0] . "ms";
                                    } else {
                                        echo number_format( $custom_fields['largest_contentful_paint'][0] / 1000, 1 ) . "s";
                                    } ?></span></div>
                                    <div class="gfw-latest-report-number"><strong>Total blocking time</strong><span class="gfw-report-score" style="color:<?php echo $this->tbt_to_color( $custom_fields['total_blocking_time'][0] ); ?>"><?php echo $custom_fields['total_blocking_time'][0]; ?> ms</span></div>
                                    <div class="gfw-latest-report-number"><strong>Cumulative Layout Shift</strong> <span class="gfw-report-score" style="color:<?php echo $this->cls_to_color( $custom_fields['cumulative_layout_shift'][0] ); ?>"><?php echo $custom_fields['cumulative_layout_shift'][0]; ?></span></div>
                                </div>
                            </div>
                            <div class="gfw-report-links">
                                <p><a href="<?php echo GFW_SCHEDULE; ?>&report_id=<?php echo $query->post->ID; ?>" class="gfw-schedule-icon-large">Monitor this page</a>
                                <?php $this->display_retest_form( 'Re-test your Front Page', $options['report_type'], untrailingslashit( GFW_FRONT ), $options['default_browser'], $options['default_location'], $options['default_connection'] ); ?>
                                <p><a href="<?php echo $custom_fields['report_url'][0]; ?>" target="_blank" class="gfw-report-icon">Detailed report</a> &nbsp;&nbsp; </p>
                            </div>
                        </div>
                    </div>
                <?php
                    }
                } else {
                }
                ?>
                        <?php
                        if ( !$expired ) {
                            //echo '<a href="' . $custom_fields['report_url'][0] . '" target="_blank" class="gfw-report-icon">Detailed report</a> &nbsp;&nbsp; ';
                        }
                        ?>
                <?php
            }
        } else {
            ?>
            <div class="gfw-latest-report-wrapper">
                <div class="gfw-box gfw-latest-report-screenshot"><img src="<?php echo plugin_dir_url( __FILE__ ) . "/images/no-report-splash.png"; ?>" /></div>
            <?php
            //If we found no report of the right type, look for reports of the WRONG type. That will influence what's shown here.
            $args = array(
                'post_type' => 'gfw_report',
                'post_status' => 'published',
                'posts_per_page' => 1,
                'orderby' => 'post_date',
                'order' => 'DESC',
                'meta_query' => array(
                    array(
                        'key' => 'gfw_url',
                        'value' => array( trailingslashit( GFW_FRONT ), untrailingslashit( GFW_FRONT ) ),
                        //'value' => 'https://google.ca',
                        'compare' => 'IN'
                    ),
                    array(
                        'key' => 'gtmetrix_test_id',
                        'value' => 0,
                        'compare' => '!='
                    )
                ),
            );
            $no_reports = TRUE;
            if( $options['report_type'] == 'lighthouse' ) {
                $args['meta_query'][] = array(
                    'key' => 'pagespeed_score',
                    'compare' => 'EXIST'
                );
            } else {
                $args['meta_query'][] = array(
                    'key' => 'gtmetrix_grade',
                    'compare' => 'EXIST'
                );
            }
    
            $query = new WP_Query( $args );
            if ( $query->have_posts() ) {
                $no_reports = FALSE;
            }
            echo '<div class="gfw-box gfw-latest-report">';
            echo '<div class="gfw-box gfw-no-latest-report">';
            if( $no_reports) {
                echo '<h4>Your Front Page (' . GFW_FRONT . ') has not been analyzed yet</h4><p>Your front page is set in the <a href="' . get_admin_url() . 'options-general.php">Settings</a> of your WordPress install.</p>';
                //echo '</div>';
                $this->display_retest_form( 'Test your Front Page now', $options['report_type'], untrailingslashit( GFW_FRONT ), $options['default_browser'], $options['default_location'], $options['default_connection'], $options['default_retention'] );
            } else {
                if( $options['report_type'] == 'lighthouse' ) {
                    echo '<h4>No Lighthouse Report data for this page</h4><p>You have not tested the front page as a Lighthouse report yet.</p>';
                    //echo '</div>';
                } else {
                    echo '<h4>No Legacy Report data for this page</h4><p>You have not tested the front page as a Legacy report yet.</p>';
                    //echo '</div>';    
                }
                $this->display_retest_form( 'Re-test your Front Page', $options['report_type'], untrailingslashit( GFW_FRONT ), $options['default_browser'], $options['default_location'], $options['default_connection'] );
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
    }

    public function display_retest_form( $label, $report_type, $url, $browser, $location, $connection, $retention ) {
        ?>
        <form method="post" id="gfw-retest"><input type="hidden" name="post_type" value="gfw_report" /><input type="hidden" name="gfw_url" value="<?php echo $url; ?>" /><input type="hidden" name="gfw_location" value="<?php echo $location; ?>" /><input type="hidden" name="gfw_report" value="<?php echo $report_type;?>" /><input type="hidden" name="gfw_browser" value="<?php echo $browser; ?>" /><input type="hidden" name="gfw_connection" value="<?php echo $connection; ?>" /><input type="hidden" name="gfw_retention" value="<?php echo $retention; ?>" /><?php
            wp_nonce_field( plugin_basename( __FILE__ ), 'gfwtestnonce' );?><?php submit_button( $label, 'primary', 'submit', false ); ?></form>
        <?php
    }

    public function score_meta_box() {
        $this->front_score( false );
    }

    public function test_meta_box() {
        $passed_url = isset( $_GET['url'] ) ? GFW_FRONT . $_GET['url'] : '';
        $passed_url = htmlspecialchars( $passed_url );
        ?>
        <form method="post" id="gfw-parameters">
            <input type="hidden" name="post_type" value="gfw_report" />
            <div id="gfw-scan" class="gfw-dialog" title="Analyzing...">
                <div id="gfw-screenshot"><img src="<?php echo GFW_URL . 'images/scanner.png'; ?>" alt="" id="gfw-scanner" /><div class="gfw-message"></div></div>
            </div>
            <?php
            wp_nonce_field( plugin_basename( __FILE__ ), 'gfwtestnonce' );
            $options = get_option( 'gfw_options' );
            ?>
            <input type="hidden" name="gfw_report" value="<?php echo $options['report_type'];?>" />
            
            <p><input type="text" id="gfw_url" name="gfw_url" value="<?php echo $passed_url; ?>" placeholder="You can enter a URL (eg. http://yourdomain.com), or start typing the title of your page/post" /><br />
                <span class="gfw-placeholder-alternative description">You can enter a URL (eg. http://yourdomain.com), or start typing the title of your page/post</span></p>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Browser<a class="gfw-help-icon tooltip" href="#" title=""></a></th>
                    <td><select name="gfw_browser" id="gfw_browser">
                            <?php
                            foreach ( $options['browsers'] as $browser ) {
                                echo '<option value="' . $browser['id'] . '" ' . selected( isset( $options['default_browser'] ) ? $options['default_browser'] : $browser['default'], $browser['id'], false ) . '>' . $browser['attributes']['name'] . '</option>';
                            }
                            ?>
                        </select><br />
                        <span class="description">Test Browser</span></td>
                </tr><tr valign="top">
                    <th scope="row">Location<a class="gfw-help-icon tooltip" href="#" title="f"></a></th>
                    <td><select name="gfw_location" id="gfw_location">
                            <?php
                            foreach ( $options['locations'] as $location_region => $region_locations ) {
                                if( !empty( $region_locations ) ) {
                                    echo '<optgroup label="' . $location_region . '">';
                                    //ALL locations are grouped by region.
                                    foreach( $region_locations as $location ) {
                                        echo '<option value="' . $location['id'] . '" ' . selected( $options['default_location'], $location['id'], false );
                                        if( $location['attributes']['account_has_access'] != 1 ) {
                                            echo ' disabled';
                                        }
                                        echo '>' . $location['attributes']['name'] .  '</option>';
                                    }
                                    echo '</optgroup>';
                                }
                            }
                            //foreach ( $options['locations'] as $location ) {
                            //    echo '<option value="' . $location['id'] . '" ' . selected( isset( $options['default_location'] ) ? $options['default_location'] : $location['default'], $location['id'], false ) . '>' . $location['attributes']['name'] . '</option>';
                            //}
                            ?>
                        </select><br />
                        <span class="description">Test Server Region</span></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Connection<a class="gfw-help-icon tooltip" href="#" title="Analyze the performance of the page from one of our several test regions.  Your PageSpeed and YSlow scores usually stay roughly the same, but Page Load times and Waterfall should be different. Use this to see how latency affects your page load times from different parts of the world."></a></th>
                    <td><select name="gfw_connection" id="gfw_connection">
                            <?php
                            foreach ( $options['connections'] as $connection ) {
                                echo '<option value="' . $connection['id'] . '" ' . selected( isset( $options['default_connection'] ) ? $options['default_connection'] : $connection['default'], $connection['id'], false ) . '>' . $connection['attributes']['name'] . '</option>';
                            }
                            ?>
                        </select><br />
                        <span class="description">Test Connection</span></td>
                </tr>
            </table>
            <div id="analysis-options-wrapper">
                <h3 id="analysis-options-header">&#8964; Show Analysis Options</h3>
                <div id="analysis-options">
                    <table class="form-table">
                        <tr valign="top">
                        <th scope="row">Enable Adblock Plus<a class="gfw-help-icon tooltip" href="#" title=""></a></th>
                        <td><span class="input-toggle">
                            <input type="checkbox" name="gfw_enable_adblock" id="gfw_enable_adblock" value="1" /><label for="gfw_enable_adblock"></label></span> <span class="description">Block ads from loading on your site</span><br />
                            </td>
                        </tr>
                        <tr valign="top">
                        <th scope="row">Enable Video<a class="gfw-help-icon tooltip" href="#" title=""></a></th>
                        <td><span class="input-toggle">
                            <input type="checkbox" name="gfw_enable_video" id="gfw_enable_video" value="1" /><label for="gfw_enable_video"></label></span> <span class="description">Generate a video of your page load (+X API credits per test)</span><br />
                            </td>
                        </tr>
                        <tr valign="top">
                        <th scope="row">Cookies<a class="gfw-help-icon tooltip" href="#" title=""></a></th>
                        <td><textarea id="gfw_cookies" name="gfw_cookies"></textarea>
                            </td>
                        </tr>
                        <tr valign="top">
                        <th scope="row">HTTP Auth<a class="gfw-help-icon tooltip" href="#" title=""></a></th>
                            <td>
                            <input type="text" id="gfw_httpauth_username" name="gfw_httpauth_username" /> <input type="text" id="gfw_httpauth_password" name="gfw_httpauth_password" />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Data Retention<a class="gfw-help-icon tooltip" href="#" title="Data retention"></a></th>
                            <td><select name="gfw_retention" id="gfw_retention">
                                    <?php
                                    foreach ( $options['retentions'] as $retention ) {
                                        echo '<option value="' . $retention['id'] . '" ' . selected( isset( $options['default_retention'] ) ? $options['default_retention'] : $retention['default'], $retention['id'], false ) . '>' . $retention['attributes']['name'] . '</option>';
                                    }
                                    ?>
                                </select></td>
                        </tr>
                    </table>
                </div>
            
            </div>

            <?php submit_button( 'Test Page', 'primary', 'submit', false ); ?>
        </form>
        <?php
    }

    public function schedule_meta_box() {
        $report_id = isset( $_GET['report_id'] ) ? $_GET['report_id'] : 0;
        $event_id = isset( $_GET['event_id'] ) ? $_GET['event_id'] : 0;
        $cpt_id = $report_id ? $report_id : $event_id;
        $custom_fields = get_post_custom( $cpt_id );
        $options = get_option( 'gfw_options' );
        //$grades = array( 90 => 'A', 80 => 'B', 70 => 'C', 60 => 'D', 50 => 'E', 40 => 'F' );

        if ( empty( $custom_fields ) ) {
            echo '<p>Event not found.</p>';
            return false;
        }
        ?>
        <form method="post">
            <input type="hidden" name="event_id" value="<?php echo $event_id; ?>" />
            <input type="hidden" name="report_id" value="<?php echo $report_id; ?>" />
            <?php wp_nonce_field( plugin_basename( __FILE__ ), 'gfwschedulenonce' ); ?>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row">URL</th>
                    <td><?php echo $custom_fields['gfw_url'][0]; ?></td>
                </tr>
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
                <tr style="display: <?php echo ($notifications_count && $notifications_count < 4 ? 'table-row' : 'none'); ?>" id="gfw-add-condition">
                    <th scope="row">&nbsp;</th>
                    <td><a href="javascript:void(0)">+ Add a condition</a></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="gfw-location">Browser</label></th>
                    <td><?php echo $options['browsers'][$custom_fields['gfw_browser'][0]]['attributes']['name']; ?></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="gfw-location">Location</label></th>
                    <td><?php 
                    foreach( $options['locations'] as $region => $region_locations ) {
                        $location_in_region = array_search( $custom_fields['gfw_location'][0], array_keys( $region_locations ) );
                        if( $location_in_region !== FALSE ) {
                            echo $region_locations[$custom_fields['gfw_location'][0]]['attributes']['name'];
                            break;
                        }
                    } ?></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="gfw-location">Connection</label></th>
                    <td><?php 
                    //echo print_r( $options['connections'], true);
                    foreach( $options['connections'] as $connection ) {
                        if( $connection['id'] == $custom_fields['gfw_connection'][0] ) {
                            echo $connection['attributes']['name'];
                        }
                    } 
                    ?>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="gfw-location">Adblock Plus</label></th>
                    <td><?php echo $custom_fields['gfw_adblock'][0] ? 'On' : 'Off'; ?></td>
                </tr>
                <?php
                //echo  print_r( $custom_fields['gfw_notifications'], TRUE ) ;
                if ( isset( $custom_fields['gfw_notifications'][0] ) ) {
                    $notifications = unserialize( $custom_fields['gfw_notifications'][0] );
                    $notifications_count = count( $notifications );
                } else {
                    if( isset( $custom_fields['pagespeed_score']) ) {
                    // display a disabled, arbitrary condition if no conditions are already set
                        $notifications = array( 'pagespeed_score' => array(
                            'value' => 90,
                            'comparator' => '<',
                         ) );
                        $notifications_count = 0;
                    } else {
                        $notifications = array( 'gtmetrix_grade' => array(
                            'value' => "A" ,
                            'comparator' => '<'
                        ) );
                        $notifications_count = 0;
                    }
                }
                ?>
                <tr valign="top">
                    <th scope="row"><label for="gfw-notifications">Enable Alerts</label></th>
                    <td><span class="input-toggle"><input type="checkbox" id="gfw-notifications" value="1" <?php checked( $notifications_count > 0 ); ?> /><label for="gfw-notifications"></label></span>
                        <span class="description">Get notified by e-mail if a test result underperforms based on conditions you set</span></td>
                </tr>

                <?php
                $grades = array(
                    "A" => "A",
                    "B" => "B",
                    "C" => "C",
                    "D" => "D",
                    "E" => "E",
                    "F" => "F",
                );
                for ( $i = 0; $i < 4; $i++ ) {
                    if ( $notifications ) {
                        $condition_value_unit = reset( $notifications );
                        $condition_value = $condition_value_unit['value'];
                        $condition_comparator = $condition_value_unit['comparator'];
                        if( isset( $condition_value_unit['page_bytes_size'] ) ) {
                            $condition_page_bytes_size = $condition_value_unit['page_bytes_size'];
                        } else {
                            $condition_page_bytes_size = "";
                        }
                        if( isset( $condition_value_unit['html_bytes_size'] ) ) {
                            $condition_html_bytes_size = $condition_value_unit['html_bytes_size'];
                        } else {
                            $condition_html_bytes_size = "";
                        }
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
                        <th scope="row"><?php echo $i ? 'or' : 'Alert admin when'; ?></th>
                        <td><select name="gfw_condition[<?php echo $i; ?>]" class="gfw-condition"<?php echo $disabled; ?>>
                                <?php
                                if( isset( $custom_fields['pagespeed_score']) ) {
                                    $conditions = array(
                                        'Scores' => array(
                                            'pagespeed_score' => 'PageSpeed score',
                                            'yslow_score' => 'YSlow score'
                                        ),
                                        'Page Timings' => array(
                                            'fully_loaded_time' => 'Fully Loaded',
                                            'time_to_first_byte' => 'Time to First Byte',
                                            'redirect_duration' => 'Redirect Duration',
                                            'connect_duration' => 'Connect Duration',
                                            'backend_duration' => 'Backend Duration',
                                            'onload_time' => 'Onload',
                                            'onload_duration' => 'Onload Duration',
                                        ),
                                        'Page Details' => array(
                                            'page_bytes' => 'Total Size',
                                            'page_requests' => 'Total Requests'
                                        ),
                                        'Legacy & Other Metrics' => array(
                                            'html_bytes' => 'HTML Size',
                                            'first_paint_time' => 'First Paint',
                                            'dom_interactive_time' => 'DOM Interactive',
                                            'dom_content_loaded_time' => 'DOM Content Loaded',
                                            'dom_content_loaded_duration' => 'DOM Content Loaded Duration'
                                        )
                                    );
                                } else {
                                    $conditions = array(
                                        'Scores' => array(
                                            'gtmetrix_grade' => 'GTmetrix Grade',
                                            'performance_score' => 'Performance Score',
                                            'structure_score' => 'Structure Score'
                                        ),
                                        'Performance Metrics' => array(
                                            'largest_contentful_paint' => 'Largest Contentful Paint',
                                            'first_contentful_paint' => 'First Contentful Paint',
                                            'total_blocking_time' => 'Total Blocking Time',
                                            'cumulative_layout_shift' => 'Cumulative Layout Shift',
                                            'time_to_interactive' => 'Time to Interactive',
                                            'speed_index' => 'Speed Index'
                                        ),
                                        'Page Timings' => array(
                                            'time_to_first_byte' => 'Time to First Byte',
                                            'redirect_duration' => 'Redirect Duration',
                                            'connect_duration' => 'Connect Duration',
                                            'backend_duration' => 'Backend Duration',
                                            'onload_time' => 'Onload',
                                            'fully_loaded_time' => 'Fully Loaded'
                                        ),
                                        'Page Details' => array(
                                            'page_bytes' => 'Total Size',
                                            'page_requests' => 'Total Requests'
                                        ),
                                        'Legacy & Other Metrics' => array(
                                            'html_bytes' => 'HTML Size',
                                            'first_paint_time' => 'First Paint',
                                            'dom_interactive_time' => 'DOM Interactive',
                                            'dom_content_loaded_time' => 'DOM Content Loaded',
                                            'dom_content_loaded_duration' => 'DOM Content Loaded Duration',
                                        )
                                    );
                                }
                                foreach ( $conditions as $option_group_label => $option_group ) {
                                    echo '<optgroup label="' . $option_group_label . '">';
                                    foreach( $option_group as $value => $name ) {
                                        echo '<option value="' . $value . '" ' . selected( $condition_name, $value, false ) . '>' . $name . '</option>';
                                    }
                                    echo '</optgroup>';
                                }
                                ?>
                            </select>
                            <select name="comparator[<?php echo $i; ?>]" class="gfw-comparator">
                            <option value="<"<?php if( $condition_comparator == "<") {echo " selected";} ?>>Is less than</option>
                            <option value="="<?php if( $condition_comparator == "=") {echo " selected";} ?>>Is equal to</option>
                            <option value=">"<?php if( $condition_comparator == ">") {echo " selected";} ?>>Is greater than</option>
                            </select>
                            <?php
                            if( isset( $custom_fields['pagespeed_score']) ) { ?>
                            <div class="gfw-condition-wrapper pagespeed_score"<?php echo ('pagespeed_score' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text"  name="pagespeed_score[<?php echo $i; ?>]" class="pagespeed_score gfw-units" value="<?php echo $condition_value; ?>" /> %
                            </div>
                            <div class="gfw-condition-wrapper yslow_score"<?php echo ('yslow_score' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text"  name="yslow_score[<?php echo $i; ?>]" class="yslow_score gfw-units" value="<?php echo $condition_value; ?>"> %
                            </div>
                            <div class="gfw-condition-wrapper fully_loaded_time"<?php echo ('fully_loaded_time' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="fully_loaded_time[<?php echo $i; ?>]" class="fully_loaded_time gfw-units" value="<?php echo $condition_value; ?>" /> s
                            </div>
                            <div class="gfw-condition-wrapper time_to_first_byte"<?php echo ('time_to_first_byte' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="time_to_first_byte[<?php echo $i; ?>]" class="time_to_first_byte gfw-units" value="<?php echo $condition_value; ?>" /> s
                            </div>
                            <div class="gfw-condition-wrapper redirect_duration"<?php echo ('redirect_duration' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="redirect_duration[<?php echo $i; ?>]" class="redirect_duration gfw-units" value="<?php echo $condition_value; ?>" /> s
                            </div>
                            <div class="gfw-condition-wrapper connect_duration"<?php echo ('connect_duration' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="connect_duration[<?php echo $i; ?>]" class="connect_duration gfw-units" value="<?php echo $condition_value; ?>" /> s
                            </div>
                            <div class="gfw-condition-wrapper backend_duration"<?php echo ('backend_duration' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="backend_duration[<?php echo $i; ?>]" class="backend_duration gfw-units" value="<?php echo $condition_value; ?>" /> s
                            </div>
                            <div class="gfw-condition-wrapper onload_time"<?php echo ('onload_time' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="onload_time[<?php echo $i; ?>]" class="onload_time gfw-units" value="<?php echo $condition_value; ?>" /> s
                            </div>
                            <div class="gfw-condition-wrapper onload_duration"<?php echo ('onload_duration' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="onload_duration[<?php echo $i; ?>]" class="onload_duration gfw-units" value="<?php echo $condition_value; ?>" /> s
                            </div>
                            <div class="gfw-condition-wrapper page_bytes"<?php echo ('page_bytes' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="page_bytes[<?php echo $i; ?>]" class="page_bytes gfw-units" value="<?php echo $condition_value; ?>" /> 
                            <select name="page_bytes_size[<?php echo $i; ?>]" class="gfw-comparator">
                            <option value="B"<?php if( $condition_page_bytes_size == "B") {echo " selected";} ?>>B</option>
                            <option value="KB"<?php if( $condition_page_bytes_size == "KB") {echo " selected";} ?>>KB</option>
                            <option value="MB"<?php if( $condition_page_bytes_size == "BB") {echo " selected";} ?>>MB</option>
                            </select>
                            
                            </div>
                            <div class="gfw-condition-wrapper page_requests"<?php echo ('page_requests' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="page_requests[<?php echo $i; ?>]" class="page_requests gfw-units" value="<?php echo $condition_value; ?>" />
                            </div>
                            <div class="gfw-condition-wrapper html_bytes"<?php echo ('html_bytes' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <?php echo $condition_html_bytes_size; ?><input type="text" name="html_bytes[<?php echo $i; ?>]" class="html_bytes gfw-units" value="<?php echo $condition_value; ?>" /> 
                            <select name="html_bytes_size[<?php echo $i; ?>]" class="gfw-comparator">
                            <option value="B"<?php if( $condition_html_bytes_size == "B") {echo " selected";} ?>>B</option>
                            <option value="KB"<?php if( $condition_html_bytes_size == "KB") {echo " selected";} ?>>KB</option>
                            <option value="MB"<?php if( $condition_html_bytes_size == "MB") {echo " selected";} ?>>MB</option>
                            </select>
                            
                            </div>
                            <div class="gfw-condition-wrapper first_paint_time"<?php echo ('first_paint_time' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="first_paint_time[<?php echo $i; ?>]" class="first_paint_time gfw-units" value="<?php echo $condition_value; ?>" /> s
                            </div>
                            <div class="gfw-condition-wrapper dom_interactive_time"<?php echo ('dom_interactive_time' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="dom_interactive_time[<?php echo $i; ?>]" class="dom_interactive_time gfw-units" value="<?php echo $condition_value; ?>" /> s
                            </div>
                            <div class="gfw-condition-wrapper dom_content_loaded_time"<?php echo ('dom_content_loaded_time' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="dom_content_loaded_time[<?php echo $i; ?>]" class="dom_content_loaded_time gfw-units" value="<?php echo $condition_value; ?>" /> s
                            </div>
                            <div class="gfw-condition-wrapper dom_content_loaded_duration"<?php echo ('dom_content_loaded_duration' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="dom_content_loaded_duration[<?php echo $i; ?>]" class="dom_content_loaded_duration gfw-units" value="<?php echo $condition_value; ?>" /> s
                            </div>
                            <?php } else { ?>
                            <div class="gfw-condition-wrapper gtmetrix_grade"<?php echo ('gtmetrix_grade' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <select name="gtmetrix_grade[<?php echo $i; ?>]" class="gtmetrix_grade gfw-units">
                                <?php
                                foreach ( $grades as $index => $value ) {
                                    echo '<option value="' . $index . '" ' . selected( $condition_value, $index, false ) . '>' . $value . '</option>';
                                }
                                ?>
                            </select>
                            </div>
                            <div class="gfw-condition-wrapper performance_score"<?php echo ('performance_score' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="performance_score[<?php echo $i; ?>]" class="performance_score gfw-units" value="<?php echo $condition_value; ?>" /> %
                            </div>
                            <div class="gfw-condition-wrapper structure_score">
                            <input type="text" name="structure_score[<?php echo $i; ?>]" class="structure_score gfw-units" value="<?php echo $condition_value; ?>" /> %
                            </div>
                            <div class="gfw-condition-wrapper largest_contentful_paint"<?php echo ('largest_contentful_paint' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="largest_contentful_paint[<?php echo $i; ?>]" class="largest_contentful_paint gfw-units" value="<?php echo $condition_value; ?>" /> s 
                            </div>
                            <div class="gfw-condition-wrapper first_contentful_paint"<?php echo ('first_contentful_paint' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="first_contentful_paint[<?php echo $i; ?>]" class="first_contentful_paint gfw-units" value="<?php echo $condition_value; ?>" /> s
                            </div>
                            <div class="gfw-condition-wrapper total_blocking_time"<?php echo ('total_blocking_time' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="total_blocking_time[<?php echo $i; ?>]" class="total_blocking_time gfw-units" value="<?php echo $condition_value; ?>" /> s
                            </div>
                            <div class="gfw-condition-wrapper cumulative_layout_shift"<?php echo ('cumulative_layout_shift' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="cumulative_layout_shift[<?php echo $i; ?>]" class="cumulative_layout_shift gfw-units" value="<?php echo $condition_value; ?>" />
                            </div>
                            <div class="gfw-condition-wrapper time_to_interactive"<?php echo ('time_to_interactive' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="time_to_interactive[<?php echo $i; ?>]" class="time_to_interactive gfw-units" value="<?php echo $condition_value; ?>" /> s
                            </div>
                            <div class="gfw-condition-wrapper speed_index"<?php echo ('speed_index' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="speed_index[<?php echo $i; ?>]" class="speed_index gfw-units" value="<?php echo $condition_value; ?>" /> s
                            </div>
                            <div class="gfw-condition-wrapper time_to_first_byte"<?php echo ('time_to_first_byte' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="time_to_first_byte[<?php echo $i; ?>]" class="time_to_first_byte gfw-units" value="<?php echo $condition_value; ?>" /> s
                            </div>
                            <div class="gfw-condition-wrapper redirect_duration"<?php echo ('redirect_duration' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="redirect_duration[<?php echo $i; ?>]" class="redirect_duration gfw-units" value="<?php echo $condition_value; ?>" /> s
                            </div>
                            <div class="gfw-condition-wrapper connect_duration"<?php echo ('connect_duration' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="connect_duration[<?php echo $i; ?>]" class="connect_duration gfw-units" value="<?php echo $condition_value; ?>" /> s
                            </div>
                            <div class="gfw-condition-wrapper backend_duration"<?php echo ('backend_duration' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="backend_duration[<?php echo $i; ?>]" class="backend_duration gfw-units" value="<?php echo $condition_value; ?>" /> s
                            </div>
                            <div class="gfw-condition-wrapper onload_time"<?php echo ('onload_time' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="onload_time[<?php echo $i; ?>]" class="onload_time gfw-units" value="<?php echo $condition_value; ?>" /> s
                            </div>
                            <div class="gfw-condition-wrapper fully_loaded_time"<?php echo ('fully_loaded_time' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="fully_loaded_time[<?php echo $i; ?>]" class="fully_loaded_time gfw-units" value="<?php echo $condition_value; ?>" /> s
                            </div>
                            <div class="gfw-condition-wrapper page_bytes"<?php echo ('page_bytes' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="page_bytes[<?php echo $i; ?>]" class="page_bytes gfw-units" value="<?php echo $condition_value; ?>" />
                            <select name="page_bytes_size[<?php echo $i; ?>]" class="gfw-comparator">
                            <option value="B"<?php if( $condition_page_bytes_size == "B") {echo " selected";} ?>>B</option>
                            <option value="KB"<?php if( $condition_page_bytes_size == "KB") {echo " selected";} ?>>KB</option>
                            <option value="MB"<?php if( $condition_page_bytes_size == "MB") {echo " selected";} ?>>MB</option>
                            </select>
                            </div>
                            <div class="gfw-condition-wrapper page_requests"<?php echo ('page_requests' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="page_requests[<?php echo $i; ?>]" class="page_requests gfw-units" value="<?php echo $condition_value; ?>" />
                            </div>
                            <div class="gfw-condition-wrapper html_bytes"<?php echo ('html_bytes' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="html_bytes[<?php echo $i; ?>]" class="html_bytes gfw-units" value="<?php echo $condition_value; ?>" />
                            <select name="html_bytes_size[<?php echo $i; ?>]" class="gfw-comparator">
                            <option value="B"<?php if( $condition_html_bytes_size == "B") {echo " selected";} ?>>B</option>
                            <option value="KB"<?php if( $condition_html_bytes_size == "KB") {echo " selected";} ?>>KB</option>
                            <option value="MB"<?php if( $condition_html_bytes_size == "MB") {echo " selected";} ?>>MB</option>
                            </select>
                            </div>
                            <div class="gfw-condition-wrapper dom_interactive_time"<?php echo ('dom_interactive_time' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="dom_interactive_time[<?php echo $i; ?>]" class="dom_interactive_time gfw-units" value="<?php echo $condition_value; ?>" /> s
                            </div>
                            <div class="gfw-condition-wrapper dom_content_loaded_time"<?php echo ('dom_content_loaded_time' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="dom_content_loaded_time[<?php echo $i; ?>]" class="dom_content_loaded_time gfw-units" value="<?php echo $condition_value; ?>" /> s
                            </div>
                            <div class="gfw-condition-wrapper dom_content_loaded_duration"<?php echo ('dom_content_loaded_duration' == $condition_name ? ' style="display: inline;"' : ''); ?>>
                            <input type="text" name="dom_content_loaded_duration[<?php echo $i; ?>]" class="dom_content_loaded_duration gfw-units" value="<?php echo $condition_value; ?>" /> s
                            </div>
                            <?php } ?>
                            <?php echo $i ? '<a href="javascript:void(0)" class="gfw-remove-condition">- Remove</a>' : ''; ?>
                        </td>
                        <?php
                        array_shift( $notifications );
                    }
                    ?>
                </tr>

            </table>
            <?php
            submit_button( 'Save', 'primary', 'submit', false );
            echo '</form>';
        }

        public function reports_list() {
            $options = get_option( 'gfw_options' );
            $report_type = $options['report_type'];
            $args = array(
                'post_type' => 'gfw_report',
                'posts_per_page' => -1,
                'meta_key' => 'gfw_event_id',
                'meta_value' => 0
            );
            $query = new WP_Query( $args );
            $no_posts = !$query->post_count;
            ?>
            <p>Click a report to see more detail, or to monitor the page.</p>
            <div class="gfw-table-wrapper">
                <table class="gfw-table">
                    <thead>
                        <tr style="display: <?php echo $no_posts ? 'none' : 'table-row' ?>">
                            <th class="gfw-reports-url">URL</th>
                            <th class="gfw-reports-options">Options</th>
                            <?php if( $options['report_type'] == "lighthouse" ) { ?>
                                <th class="gfw-reports-options">Grade</th>
                            <?php } ?>
                            <th class="gfw-reports-pagespeed"><?php if($options['report_type'] == "lighthouse") {echo "Performance";} else { echo "PageSpeed"; } ?></th>
                            <th class="gfw-reports-yslow"><?php if($options['report_type'] == "lighthouse") {echo "Structure";} else { echo "YSlow"; } ?></th>
                            <th class="gfw-reports-load-time">Page Load</th>
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
                            if( isset( $custom_fields['performance_score'] ) ) {
                                $report_type = "lighthouse";
                            } else {
                                $report_type = "legacy";
                            }
                            foreach ( $custom_fields as $name => $value ) {
                                $$name = $value[0];
                            }
                            //error_log( print_r( $custom_fields, TRUE ));
                            if ( !isset( $gtmetrix_error ) ) {
                                if( isset( $performance_score )) {
                                    $performance_grade = $this->score_to_grade( $performance_score );
                                    $structure_grade = $this->score_to_grade( $structure_score );
                                    $pagespeed_grade = array();
                                    $yslow_grade = array();
                                } else {
                                    $performance_grade = array();
                                    $structure_grade = array();
                                    $pagespeed_grade = $this->score_to_grade( $pagespeed_score );
                                    $yslow_grade = $this->score_to_grade( $yslow_score );
                                }
                            }
                            $report_date = $this->wp_date( $query->post->post_date, true );
                            $title = $this->append_http( $gfw_url );

                            echo '<tr class="' . ($row_number++ % 2 ? 'even' : 'odd') . '" id="post-' . $query->post->ID . '">';

                            if ( isset( $gtmetrix_error ) ) {
                                echo '<td data-th="Error" class="gfw-reports-url">' . $title . '</td>';
                                echo '<td data-th="Message" class="reports-error" colspan="3">' . $this->translate_message( $gtmetrix_error ) . '</td>';
                                echo '<td data-th="Date">' . $report_date . '</td>';
                            } else {
                                echo '<td data-th="URL" title="Click to expand/collapse" class="gfw-reports-url gfw-toggle tooltip">' . $title . '</td>';
                                echo '<td data-th="Options" class="gfw-toggle"> <span class="gfw-browser-icon-small gfw-browser-icon-' . $gfw_browser . '"></span> <span class="gfw-location-icon-small gfw-location-icon-' . $gfw_location . '"></span>';
                                if( $gfw_video ) {
                                    echo '<span class="gfw-video-icon-small"></span>';
                                }
                                echo '<a href="' . GFW_SCHEDULE . '&report_id=' . $query->post->ID . '" class="gfw-schedule-icon-small tooltip" title="Monitor page">Monitor page</a></td>';
                                if( $options['report_type'] == "lighthouse" ) {
                                    echo '<td data-th="Grade" class="gfw-toggle">';
                                    if( $report_type == "lighthouse" ) {
                                        echo $gtmetrix_grade;
                                    } else {
                                        echo 'N/A';
                                    }
                                    echo '</td>';
                                }
                                if( $options['report_type'] == "lighthouse" ) {
                                    echo '<td data-th="Performance" class="gfw-toggle gfw-reports-pagespeed">';
                                    if( $report_type == "lighthouse" ) {
                                        echo '<div class="gfw-grade-meter gfw-grade-meter-' . $performance_grade['grade'] . '"><span class="gfw-grade-meter-text">' . $performance_grade['grade'] . ' (' . $performance_score . ')</span><span class="gfw-grade-meter-bar" style="width: ' . $performance_score . '%"></span></div>';
                                    } else {
                                        echo 'N/A';
                                    }
                                    echo '</td>';
                                    echo '<td data-th="Structure" class="gfw-toggle gfw-reports-yslow">';
                                    if( $report_type == "lighthouse" ) {
                                        echo '<div class="gfw-grade-meter gfw-grade-meter-' . $structure_grade['grade'] . '"><span class="gfw-grade-meter-text">' . $structure_grade['grade'] . ' (' . $structure_score . ')</span><span class="gfw-grade-meter-bar" style="width: ' . $structure_score . '%"></span></div>';
                                    } else {
                                        echo 'N/A';
                                    }
                                    echo '</td>';
                                } else {
                                    echo '<td data-th="PageSpeed" class="gfw-toggle gfw-reports-pagespeed">';
                                    if($report_type == "legacy" ) {
                                        echo '<div class="gfw-grade-meter gfw-grade-meter-' . $pagespeed_grade['grade'] . '"><span class="gfw-grade-meter-text">' . $pagespeed_grade['grade'] . ' (' . $pagespeed_score . ')</span><span class="gfw-grade-meter-bar" style="width: ' . $pagespeed_score . '%"></span></div>';
                                    } else {
                                        echo 'N/A';
                                    }
                                    echo '</td>';
                                    echo '<td data-th="YSlow" class="gfw-toggle gfw-reports-yslow">';
                                    if($report_type == "legacy" ) {
                                        echo '<div class="gfw-grade-meter gfw-grade-meter-' . $yslow_grade['grade'] . '"><span class="gfw-grade-meter-text">' . $yslow_grade['grade'] . ' (' . $yslow_score . ')</span><span class="gfw-grade-meter-bar" style="width: ' . $yslow_score . '%"></span></div>';
                                    } else {
                                        echo 'N/A';
                                    }
                                    echo '</td>';
                                }
                                echo '<td data-th="Page Load" class="gfw-toggle">' . number_format( $page_load_time / 1000, 2 ) . 's</td>';
                                echo '<td data-th="Date" class="gfw-toggle" title="' . $report_date . '">' . $report_date . '</td>';
                            }
                            echo '<td class="gfw-action-icons"> <a href="' . GFW_TESTS . '&delete=' . $query->post->ID . '" rel="#gfw-confirm-delete" class="gfw-delete-icon delete-report tooltip" title="Delete Report">Delete Report</a></td>';
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

        public function find_location_name( $location_id ) {

        }

        public function find_connection_name( $connection ) {

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
                    echo '<p class="gfw-no-posts">You have no Monitored pages. Go to <a href="' . GFW_TESTS . '">Tests</a> to create one.</p>';
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

        public function score_to_grade( $score ) {
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

        public function lcp_to_color( $score ) {
            if( $score < 1200 )
                return "#36a927";
            if ( $score < 1666 ) 
                return "#99c144";
            if ( $score < 2400 ) 
                return "#f6ab34"; 
            return "#ec685d";
        }

        public function tbt_to_color( $score ) {
            if( $score < 150 )
                return "#36a927";
            if ( $score < 224 ) 
                return "#99c144";
            if ( $score < 350 ) 
                return "#f6ab34"; 
            return "#ec685d";
        }

        public function cls_to_color( $score ) {
            if( $score < 0.1 )
                return "#36a927";
            if ( $score < 0.15 ) 
                return "#99c144";
            if ( $score < 0.25 ) 
                return "#f6ab34"; 
            return "#ec685d";
        }

        public function gtmetrix_file_exists( $url ) {
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
            $url = htmlspecialchars($url);
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
