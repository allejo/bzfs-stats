<?php
/*
Plugin Name: BZFS Stats
Plugin URI: http://allejo.me/projects/wordpress/plugins/bzfs-stats
Description: Allows you to insert server information about a BZFlag server
Version: 0.8.0
Author: Vladimir Jimenez
Author URI: http://allejo.me/
License: GPL2

Copyright 2013 Vladimir Jimenez (allejo@me.com)
*/

include('bzfquery.php');

/**
 * The function that gets called to build a BZFS widget
 *
 * @param $attributes array Parameters that are passed in the short code
 *
 * @return string The HTML for the widget to be displayed
 */
function bzfs_widget_handler($attributes)
{
    // Load necessary CSS and JS files
    wp_register_style('bzfs-widget-css', plugins_url('style.css', __FILE__ ));
    wp_enqueue_style('bzfs-widget-css');

    // Build the HTML for the widget
    $bzfs_widget = bzfs_widget_builder($attributes);

    // Return the widget HTML to be displayed
    return $bzfs_widget;
}

/**
 * Builds the widget out of available HTML templates with the specified information
 *
 * @param $attributes array Parameters that are passed in the short code
 *
 * @return string The HTML for the widget to be displayed
 */
function bzfs_widget_builder($attributes)
{
    $widget = ""; // We'll store the HTML here

    // Get all the parameters that were passed in the short code and save them in variables
    // Here are our default values in case the parameters were not passed
    extract(shortcode_atts(array(
        'name' => 'My First Server',
        'server' => 'localhost',
        'port' => '5154',
        'mode' => 'FFA',
        'players' => '0,0,0,0,0,0',
        'flags' => false,
        'jumping' => false,
        'inertia' => false,
        'ricochet' => false,
        'shaking' => false,
        'antidote' => false,
        'handicap' => false,
        'no-team-kills' => false
    ), $attributes));

    // Make a JSON request to GitHub for a specified user and repository
    $data = bzfs_query($server, $port);

    // Start building the widget
    $widget =
        '<div class="bzfs-stats">'.
        '<div class="header">' .
        sprintf('<h2>%s</h2>', $name) .
        sprintf('<p>%s:%s</p>', $server, $port) .
        '</div>' .
        '<h3>Server Details</h3>' .
        '<ul class="details">' .
        '<li>' .
        '<strong>Server Options</strong>' .
        '<ul class="options">';

    foreach ($data['options'] as $key=>$value)
    {
        if ($value)
        {
            $widget .= sprintf('<li>%s</li>', ucfirst($key));
        }
    }

    $widget .=
        '</ul>' .
        '</li>' .
        '<li>' .
        '<strong>Max Players</strong>'.
        '<ul>';

    foreach ($data['max_players'] as $key=>$value)
    {
        if ($value > 0)
        {
            $widget .= sprintf('<li>%s: %s<li>', ucfirst($key), $value);
        }
    }

    $widget .=
        '</ul>' .
        '</li>' .
        '</ul>';

    if (!empty($data)
    {
        $widget .= '<h3>Top Players</h3>';

        if (!empty($data['players']))
        {
            $widget .= '<ul class="score">');

            foreach ($data['players'] as $key=>$value)
            {
                $widget .= sprintf('<li>%s - %s</li>', $value, $key);
            }

            $widget .= '</ul>';
        }
        else
        {
            $widget .= '<div class="inactive">No Active players</div>';
        }
    }

    if (empty($data))
    {
        $widget .= sprintf('<div class="update">Last updated: %s minutes ago</div>');
    }
    else
    {
        $widget .= '<div class="update">Server Status: Down</div>';
    }

    $widget .= '</div>';

    // Return the generated HTML
    return $widget;
}

/**
 * Make a query to a BZFlag server and port to get the information
 *
 * @param $server string The hostname of the server
 * @param $port int The port number of the server
 *
 * @return array|mixed The information retrieved from the query or the transient
 */
function bzfs_query($server, $port)
{
    $game_styles = array('TeamFFA', 'ClassicCTF', 'OpenFFA', 'RabbitChase');

    $transient = "bzfs-widget_" . $server . "-" . $port; // Build a name for the transient so we can "cache" information
	$status = get_transient($transient); // Check whether or not the transient exists

    // If the transient exists, return that
    if ($status)
    {
        return $status;
    }

    $bzfs_raw_data = bzfquery($server . ":" . $port);
    $bzfs_data = array();
    $bzfs_data['game_mode']                = $game_styles[$bzfs_raw_data['gameStyle']];
    $bzfs_data['max_players']['rogue']     = $bzfs_raw_data['rogueMax'];
    $bzfs_data['max_players']['red']       = $bzfs_raw_data['redMax'];
    $bzfs_data['max_players']['green']     = $bzfs_raw_data['greenMax'];
    $bzfs_data['max_players']['blue']      = $bzfs_raw_data['blueMax'];
    $bzfs_data['max_players']['purple']    = $bzfs_raw_data['purpleMax'];
    $bzfs_data['options']['flags']         = ($bzfs_raw_data['gameOptions'] & 0x0002);
    $bzfs_data['options']['jumping']       = ($bzfs_raw_data['gameOptions'] & 0x0008);
    $bzfs_data['options']['inertia']       = ($bzfs_raw_data['gameOptions'] & 0x0010);
    $bzfs_data['options']['ricochet']      = ($bzfs_raw_data['gameOptions'] & 0x0020);
    $bzfs_data['options']['shaking']       = ($bzfs_raw_data['gameOptions'] & 0x0040);
    $bzfs_data['options']['antidote']      = ($bzfs_raw_data['gameOptions'] & 0x0080);
    $bzfs_data['options']['handicap']      = ($bzfs_raw_data['gameOptions'] & 0x0100);
    $bzfs_data['options']['no-team-kills'] = ($bzfs_raw_data['gameOptions'] & 0x0400);
    $bzfs_data['players'] = "";

    // Store the information in the transient in order to cache it
    set_transient($transient, $bzfs_data, 300);

    // Return our array of information
    return $bzfs_data;
}

// Register the 'bzfs' short code and make the handler function the main function
add_shortcode('bzfs', 'bzfs_widget_handler');