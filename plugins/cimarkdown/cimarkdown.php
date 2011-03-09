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

/**
* Function called when plugin first activated
* 
*/
function cimarkdown_activate()
{
    echo "Activated!";
}

/**
* Function called when plugin deactivated
* 
*/
function cimarkdown_deactivate()
{
    echo "Deactiviated!";
}

function cimarkdown_install()
{
    echo "Install!";
}

function cimarkdown($text)
{
    $markdown_value = Markdown($text);
    return $markdown_value;
}