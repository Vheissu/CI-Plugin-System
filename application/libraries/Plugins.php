<?php

/**
* @name Hooks plugin system for Codeigniter
* @author Dwayne Charrington - http://ilikekillnerds.com
* @coypright Dwayne Charrington 2011
* @licence http://ilikekillnerds.com
*/

class Plugins {
    
    // Codeigniter instance
    private $CI;
    
    // So. Much. Static.
    public static $plugins_directory, $instance, $hooks, $current_hook, $plugins;
    
    public function __construct()
    {
        // Store instance of this class
        self::$instance =& $this;
        
    	// Store our Codeigniter instance
        $this->CI =& get_instance();
        
        // Load the directory helper so we can parse for plugins in the plugin directory
        $this->CI->load->helper('directory');
        
        // Set the plugins directory if not already set
        if ( empty(self::$plugins_directory) )
        {
            self::$plugins_directory = FCPATH . "plugins/";   
        }
        
        // Load all plugins
        $this->load_plugins();
    }
    
    /**
    * Store the location of where our plugins are located
    * 
    * @param mixed $directory
    */
    public static function set_plugin_dir($directory)
    {
    	self::$plugins_directory = trim($directory);
    }
	
    /**
    * Takes care of loading our plugins and making them usable, etc.
    * One lone protected function in a sea of static functions.
    * 
    */
    protected function load_plugins()
    {
    	// Only go one deep as the plugin file is the same name as the folder
    	$plugins = directory_map(self::$plugins_directory, 1);
    	
    	// Iterate through every plugin found
    	foreach ($plugins AS $key => $name)
    	{               		
    		// If the plugin hasn't already been added and isn't a file
    		if ( !isset(self::$plugins[$name]) AND !stripos($name, ".") )
    		{                
    			self::$plugins[$name];
    		}
    		else
    		{
				return TRUE;	
    		}
    	}
    	
    	// Get plugin headers and store them
    	//$this->get_plugin_headers();	
    }
    
    /**
    * Shameless Wordpress rip off. Gets plugin information from header of
    * plugin file.
    * 
    */
    protected function get_plugin_headers()
    {
    	$plugin_data = "";
    	
		preg_match ( '|Plugin Name:(.*)$|mi', $plugin_data, $name );
		preg_match ( '|Plugin URI:(.*)$|mi', $plugin_data, $uri );
		preg_match ( '|Version:(.*)|i', $plugin_data, $version );
		preg_match ( '|Description:(.*)$|mi', $plugin_data, $description );
		preg_match ( '|Author:(.*)$|mi', $plugin_data, $author_name );
		preg_match ( '|Author URI:(.*)$|mi', $plugin_data, $author_uri );
    }
	
    /**
    * Registers a new action hook callback
    * 
    * @param mixed $name
    * @param mixed $function
    * @param mixed $priority
    */
    public static function add_action($name, $function, $priority=10)
    {
        // If we have already registered this action return true
        if ( isset(self::$hooks[$name][$priority][$function]) )
        {
            return true;
        }
        
        // Store the action hook in the $hooks array
        self::$hooks[$name][$priority][$function] = array(
            "function" => $function
        );
        
        return true;
    }
	
    /**
    * Trigger an action for a particular action hook
    * 
    * @param mixed $name
    * @param mixed $arguments
    * @return mixed
    */
    public static function run_action($name, $arguments = "")
    {
        // Oh, no you didn't. Are you trying to run an action hook that doesn't exist?
        if ( !isset(self::$hooks[$name]) AND !is_array(self::$hooks[$name]) )
        {
            return $arguments;
        }
        
        // Set the current running hook to this
        self::$current_hook = $name;
        
        // Key sort our action hooks
        ksort(self::$hooks[$name]);
        
        foreach(self::$hooks[$name] AS $priority => $names)
        {
            if (is_array($names))
            {
                foreach($names AS $name)
                {
                    // This line runs our function and stores the result in a variable                    
                    $returnargs = call_user_func_array($name['function'], array(&$arguments));
                    
                    if ($returnargs)
                    {
                        $arguments = $returnargs;
                    }
                }
            }
        }
        
        // No running hook!
        self::$current_hook = '';
        return $arguments;
    }  
	
    /**
    * Remove an action hook. No more needs to be said.
    * 
    * @param mixed $name
    * @param mixed $function
    * @param mixed $priority
    */
    public static function remove_action($name, $function, $priority=10)
    {
        // If the action hook doesn't, just return true
        if ( !isset(self::$hooks[$name][$priority][$function]) )
        {
            return true;
        }
        
        // Remove the action hook from our hooks array
        unset( self::$hooks[$name][$priority][$function] );
    }
    
    /**
    * Get the currently running action hook
    * 
    */
    public static function current_hook()
    {
        return self::$current_hook;
    }
    
    /**
    * It's 3am, do you know where your children are?
    * Returns all found plugins and registered hooks.
    * 
    */
    public static function debug_plugins()
    {
		echo "<p><strong>Plugins found</strong></p>";
        
        if (self::$plugins)
        {
		    print_r(self::$plugins);
        }
        else
        {
            echo "<p>No plugins found.</p>";
        }
		
        echo "<br />";
		echo "<br />";
        
		echo "<p><strong>Registered hooks</strong></p>";
        
        if (self::$hooks)
        {
            print_r(self::$hooks);   
        }    	
        else
        {
            echo "<p>No registered hooks.</p>";
        }
    }
    
    /**
    * Return an instance of this class even though we probably
    * don't actually need it.
    * 
    */
    public static function instance()
    {
        return self::$instance;
    }  
}

function add_action($name, $function, $priority=10)
{
    Plugins::add_action($name, $function, $priority);
}

function run_action($name, $arguments = "")
{
    Plugins::run_action($name, $arguments);
}

function remove_action($name, $function, $priority=10)
{
    Plugins::remove_action($name, $function, $priority);
}

function set_plugin_dir($directory)
{
    Plugins::set_plugin_dir($directory);
}

function debug_plugins()
{
    Plugins::debug_plugins();
}

?>
