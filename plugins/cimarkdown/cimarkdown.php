<?php
/**
* Plugin Name: CI Markdown
* Plugin URI: http://ilikekillnerds.com
* Version: 1.0
* Description: Parses text for Markdown
* Author: Dwayne Charrington
* Author URI: http://ilikekillnerds.com
*/

// Include Markdown
include_once "markdown.php";

// When message text is parsed, call cimarkdown
add_action("parse.message", "cimarkdown", 10);

// Plugin install hook
add_action("install_cimarkdown", "install_plugin", 10);

// Plugin deactivate hook
add_action("deactivate_cimarkdown", "deactivate_plugin", 10);

/**
* Function called when plugin deactivated
* 
*/
function deactivate_plugin()
{
    return true;
}

function install_plugin()
{
    return true;
}

function cimarkdown($text)
{
    $markdown_value = Markdown($text);
    return $markdown_value;
}