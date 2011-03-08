<?php

/**
* Name: Hello World
* Description: A simple hello world plugin
* Website: http://ilikekillnerds.com
* Author: Dwayne Charrington
* Author url: http://ilikekillnerds.com
*/

/**
* Add our hooks
*/
Plugins::register_action("pre.render.thread", "hello_world");

function hello_world()
{
    
}

function hello_install()
{
    // Install logic is run when plugin is installed
}

function hello_activate()
{
    // When plugin is activated   
}

