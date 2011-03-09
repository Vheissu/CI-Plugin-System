<?php
/**
* Plugin Name: Hello World
* Plugin URI: http://ilikekillnerds.com
* Version: 1.0
* Description: A simple hello world plugin
* Author: Dwayne Charrington
* Author URI: http://ilikekillnerds.com
*/

// Run our hello world function when the render thread event is called
add_action('render', 'hello_world', 10);

// Register another action which will trigger our string function
add_action('render.string', 'hello_world_string', 10);

function hello_world()
{
    echo "Hello World!";
    echo "<br /><br />";
}

function hello_world_string($string)
{
    echo "Hello World! I am a string function with a dynamic value of: ". $string;
    echo "<br /><br />";
    echo "Uh, oh! the string is manipulated to be call caps! see? ". strtoupper($string);
}

function helloworld_install()
{
    // Install logic is run when plugin is installed
}

function helloworld_activate()
{
    // When plugin is activated   
}

