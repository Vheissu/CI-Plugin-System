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
Plugins::register_action("pre.render.thread", "hello_world");

function hello_world()
{
    
}

function helloworld_install()
{
    // Install logic is run when plugin is installed
}

function helloworld_activate()
{
    // When plugin is activated   
}

