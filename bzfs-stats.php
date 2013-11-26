<?php
/*
Plugin Name: Repo Widget
Plugin URI: http://wordpress.org/extend/plugins/repo-widget
Description: Allows you to insert general GitHub repo information with a short code
Version: 0.8.0
Author: Vladimir Jimenez
Author URI: http://allejo.me/
License: GPL2

Copyright 2013 Vladimir Jimenez (allejo@me.com)
*/

/**
 * The function that gets called to build a repository widget
 *
 * @param $attributes array Parameters that are passed in the short code
 *
 * @return string The HTML for the widget to be displayed
 */
function repo_widget_handler($attributes)
{
    // Load necessary CSS and JS files
    wp_register_style('repo-widget-css', plugins_url('style.css', __FILE__ ));
    wp_enqueue_style('repo-widget-css');
    wp_register_script('repo-widget-js', plugins_url('repo-widget.js', __FILE__ ), array('jquery'));
    wp_enqueue_script('repo-widget-js');

    // Build the HTML for the widget
    $repo_widget = repo_widget_builder($attributes);

    // Return the widget HTML to be displayed
    return $repo_widget;
}

/**
 * Builds the widget out of available HTML templates with the specified information
 *
 * @param $attributes array Parameters that are passed in the short code
 *
 * @return string The HTML for the widget to be displayed
 */
function repo_widget_builder($attributes)
{
    $widget = ""; // We'll store the HTML here

    // Get all the parameters that were passed in the short code and save them in variables
    // Here are our default values in case the parameters were not passed
    extract(shortcode_atts(array(
        'host' => 'github',
        'user' => 'octocat',
        'repo' => 'Hello-World',
        'size' => 'normal',
        'commits' => '1',
        'theme' => 'github',
        'travis' => ''
    ), $attributes));

    // Our GitHub theme
    if ($theme == "github")
    {
        // Make a JSON request to GitHub for a specified user and repository
        $data = repo_widget_json("github", array('user' => $user, 'repo' => $repo));

        // Our normal size widget
        if ($size == "normal")
        {
            // Start building the widget
            $widget =
                sprintf('<div class="repo-widget github normal">') .
                sprintf('    <div class="header">') .
                sprintf('        <a href="%s">%s</a>', $data['url'], $data['name']);

            // Add the Travis build status if it's available
            if ($travis != '')
            {
                $widget .= sprintf('        <img src="%s">', $travis);
            }

            // Continue building the widget
            $widget .=
                sprintf('    </div>') .
                sprintf('    <div class="information">') .
                sprintf('        <div class="links">') .
                sprintf('            <div class="active" rel="%s">HTTP</div>', $data['clone_url']) .
                sprintf('            <div rel="%s">GIT</div>', $data['git_url']) .
                sprintf('            <div rel="%s">SSH</div>', $data['ssh_url']) .
                sprintf('            <input value="%s" readonly onclick="this.select()">', $data['clone_url']) .
                sprintf('        </div>') .
                sprintf('        <div class="description">') .
                sprintf('            <p>%s</p>', $data['description']) .
                sprintf('        </div>') .
                sprintf('    </div>') .
                sprintf('    <ul class="commits">') .
                sprintf('        <li>') .
                sprintf('            <div class="info">') .
                sprintf('                <p class="commit">latest commit <a href="%s">%s</a></p>', $data['last_commit']['url'], $data['last_commit']['hash']) .
                sprintf('                <p class="timestamp">%s</p>', $data['last_commit']['date']) .
                sprintf('                <div style="clear:both"></div>') .
                sprintf('            </div>') .
                sprintf('            <p class="message">%s by <em>%s</em></p>', $data['last_commit']['message'], $data['last_commit']['author']) .
                sprintf('        </li>') .
                sprintf('    </ul>') .
                sprintf('</div>');
            }
    }

    // Return the generated HTML
    return $widget;
}

/**
 * Make a JSON request and get required information
 *
 * @param $host string The respective host to make the JSON query to
 * @param $array array An array of values to be passed that will be used to make the GET query
 *
 * @return array|mixed The necessary information retrieved from the JSON query
 */
function repo_widget_json($host, $array)
{
    $transient = "repo-widget_" . $array['user'] . "-" . $array['repo']; // Build a name for the transient so we can "cache" information
	$status = get_transient($transient); // Check whether or not the transient exists

    // If the transient exists, return that
    if ($status)
    {
        return $status;
    }

    // Make a JSON query to GitHub
    if ($host == "github")
    {
        $repo_location = $array['user'] . '/' . $array['repo']; // The user and repository name combination used to build the URL

        // Retrieve information about the repository itself
        $repo_data  = json_decode(file_get_contents("https://api.github.com/repos/" . $repo_location), TRUE);

        // Retrieve the last commit
        $commit_sha = json_decode(file_get_contents("https://api.github.com/repos/" . $repo_location . "/git/refs/heads/master"), TRUE);

        // Retrieve information about the last commit
        $commit     = json_decode(file_get_contents("https://api.github.com/repos/" . $repo_location . "/git/commits/" . $commit_sha['object']['sha']), TRUE);

        // We'll build a DateTime object from our last commit time
        $date       = new DateTime($commit['author']['date']);

        // Store the necessary information in an array for easy access
        $repo_information = array();
        $repo_information['name'] = $repo_data['name'];
        $repo_information['url'] = $repo_data['html_url'];
        $repo_information['clone_url'] = $repo_data['clone_url'];
        $repo_information['git_url'] = $repo_data['git_url'];
        $repo_information['ssh_url'] = $repo_data['ssh_url'];
        $repo_information['description'] = $repo_data['description'];
        $repo_information['last_commit']['hash'] = substr($commit_sha['object']['sha'], 0, 10);
        $repo_information['last_commit']['url'] = $commit['html_url'];
        $repo_information['last_commit']['date'] = $date->format("D M j G:i:s Y");
        $repo_information['last_commit']['message'] = $commit['message'];
        $repo_information['last_commit']['author'] = $commit['author']['name'];

        // Store the information in the transient in order to cache it
        set_transient($transient, $repo_information, 90);

        // Return our array of information
        return $repo_information;
    }
}

// Register the 'repo' short code and make the handler function the main function
add_shortcode('repo', 'repo_widget_handler');