<?php

class GFW_Widget extends WP_Widget {

    function __construct() {
        $widget_ops = array( 'classname' => 'gfw_widget', 'description' => 'The GTmetrix grades for your home page' );
        parent::__construct( 'gfw-widget', 'GTmetrix for WordPress', $widget_ops );
    }

    function widget( $args, $instance ) {
        $options = get_option( 'gfw_options' );

        if ( $options['widget_css'] ) {
            wp_enqueue_style( 'gfw-widget', GFW_URL . 'widget.css', GFW_VERSION );
        }

        extract( $args, EXTR_SKIP );
        $title = apply_filters( 'widget_title', $instance['title'] );

        
        $args = array(
            'post_type' => 'gfw_report',
            'numberposts' => 1,
            'orderby' => 'post_date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => 'gfw_url',
                    'value' => GFW_FRONT
                ),
                array(
                    'key' => 'gtmetrix_test_id',
                    'value' => 0,
                    'compare' => '!='
                )
            )
        );

        $query = new WP_Query( $args );
 
        if ( $query->have_posts() ) {
            echo $before_widget;
            if ( !empty( $title ) ) {
                echo $before_title . $title . $after_title;
            }

            echo '<ul>';
              while ( $query->have_posts() ) {
                $query->next_post();
                $custom_fields = get_post_custom( $query->post->ID );
                $grades = GTmetrix_For_WordPress::gtmetrix_grade( $custom_fields['pagespeed_score'][0], $custom_fields['yslow_score'][0] );

                if ( $options['widget_pagespeed'] ) {
                    echo '<li class="gfw-grade-' . $grades['pagespeed_grade'] . ' gfw-pagespeed"><span class="gfw-tool">PageSpeed:</span> <span class="gfw-grade">' . $grades['pagespeed_grade'] . '</span>' . ($options['widget_scores'] ? ' <span class="gfw-score">(' . $custom_fields['pagespeed_score'][0] . '%)</span>' : '' ) . '</li>';
                }
                if ( $options['widget_yslow'] ) {
                    echo '<li class="gfw-grade-' . $grades['yslow_grade'] . ' gfw-yslow"><span class="gfw-tool">YSlow:</span> <span class="gfw-grade">' . $grades['yslow_grade'] . '</span>' . ($options['widget_scores'] ? ' <span class="gfw-score">(' . $custom_fields['yslow_score'][0] . '%)</span>' : '' ) . '</li>';
                }
            }
            echo '</ul>';

            if ( $options['widget_link'] ) {
                echo '<div class="gfw-link">Speed Matters! <a href="' . (GTmetrix_For_WordPress::gtmetrix_file_exists( $custom_fields['report_url'][0] . '/screenshot.jpg' ) ? $custom_fields['report_url'][0] : 'http://gtmetrix.com/') . '" target="_blank" class="gfw-link">GTmetrix</a></div>';
            }
            echo $after_widget;
        }
    }

    function form( $instance ) {
        $defaults = array(
            'title' => '',
        );

        $instance = wp_parse_args( ( array ) $instance, $defaults );
        $title = strip_tags( $instance['title'] );
        ?>

        <p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" /></p>

        <?php
    }

}