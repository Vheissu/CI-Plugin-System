<?php

/**
* @name Hooks plugin system for Codeigniter
* @author Dwayne Charrington - http://ilikekillnerds.com
* @coypright Dwayne Charrington 2011
* @licence http://ilikekillnerds.com
*/

class Plugins {
    
    // Codeigniter instance
    protected $CI;
    
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
        
        // Load the file helper to read plugin files
        $this->CI->load->helper('file');
        
        // Set the plugins directory if not already set
        if ( empty(self::$plugins_directory) )
        {
            self::$plugins_directory = FCPATH . "plugins/";   
        }
        
        // Load all plugins
        $this->load_plugins();
        
        // Register plugins
        $this->register_plugins();
        
        // Clean out old plugins that don't exist
        $this->clean_plugins_table();
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
    * Activates a plugin for use as long as it's valid
    * 
    * @param mixed $plugin
    */
    public function activate_plugin($name)
    {
        if ( !isset(self::$plugins[$name]) )
        {
            return TRUE;
        }
        else
        {
            $data = array("plugin_status" => 1);
            $this->CI->db->where('plugin_system_name', $name)->update('plugins', $data);
        }
        $this->register_plugins();
        $this->trigger_activate_plugin($name);
    }
        
    /**
    * Deactivates a plugin a long as it's valid
    * 
    * @param mixed $plugin
    */
    public function deactivate_plugin($name)
    {
        if ( !isset(self::$plugins[$name]) )
        {
            return TRUE;
        }
        else
        {
            $data = array("plugin_status" => 0);
            $this->CI->db->where('plugin_system_name', $name)->update('plugins', $data);
        }
        $this->trigger_deactivate_plugin($name);
    }
    
    public function trigger_activate_plugin($name)
    {
        // Call plugin activate function
        @call_user_func($name."_activate");
    }
    
    public function trigger_deactivate_plugin($name)
    {
        // Call our plugin deactivate function
        @call_user_func($name."_deactivate");
    }
    
    /**
    * The number of plugins found
    * 
    */
    public function count_found_plugins()
    {
        return count(self::$plugins);
    }
    
    public function count_activated_plugins()
    {
        return $this->CI->db->where('plugin_status', 1)->get('plugins')->num_rows();
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
                if ( file_exists(self::$plugins_directory.$name."/".$name.".php") )
                {
                    self::$plugins[$name] = array(
                        "function"            => $name
                    );
                    
                    // Stores meta of the plugin if not already there
                    $this->refresh_plugin_headers($name);  
                }
    		}
    		else
    		{
				return TRUE;	
    		}
    	}
    }
    
    private function trigger_install($name)
    {
        $this->register_plugins();
        
        // We're installing so call the install function
        call_user_func($name."_install");
    }
    
    /**
    * This bad boy function will help register and include plugin files
    * depending on their status in the database if they're enabled or not.
    * 
    */
    private function register_plugins()
    {
        $this->CI->load->database();
        
        foreach (self::$plugins AS $name => $data)
        {
            $query = $this->CI->db->where("plugin_system_name", $name)->get("plugins");
            $row   = $query->row();
            
            // Plugin doesn't exist, add it.
            if ($query->num_rows() == 0)
            {
                $data = array(
                    "plugin_system_name" => $name,
                    "plugin_name"        => trim($data['plugin_info']['name']),
                    "plugin_uri"         => trim($data['plugin_info']['uri']),
                    "plugin_version"     => trim($data['plugin_info']['version']),
                    "plugin_description" => trim($data['plugin_info']['description']),
                    "plugin_author"      => trim($data['plugin_info']['author_name']),
                    "plugin_author_uri"  => trim($data['plugin_info']['author_uri']),
                    "plugin_status"      => 0
                );
                $this->CI->db->insert('plugins', $data);
                
                // Trigger an install event
                $this->trigger_install($name);   
            }
            elseif ($query->num_rows() == 1)
            {
                if ($row->plugin_status == 1)
                {
                    $this->refresh_plugin_headers($name);
                    include_once self::$plugins_directory.$name."/".$name.".php";
                }
                else
                {
                    $this->refresh_plugin_headers($name);
                }
            } 
        }   
    }
    
    /**
    * This little function will check if the database has plugins that don't exist
    * and then it will remove them including their data because someone obviously
    * deleted the files and doesn't care about society.
    * 
    */
    private function clean_plugins_table()
    {
        $query = $this->CI->db->get("plugins");
        $rows  = $query->result_array();
        
        foreach ($rows AS $plugin)
        {
            if ( !isset(self::$plugins[$plugin['plugin_system_name']]) )
            {
                $this->CI->db->delete('plugins', array('plugin_system_name' => $plugin['plugin_system_name']));
            }   
        }
        
    }
    
    /**
    * This plugin just checks to make sure our plugins array has the proper meta for each plugin
    * 
    * @param mixed $plugin
    */
    private function refresh_plugin_headers($plugin)
    {
        $plugin_headers = $this->get_plugin_headers($plugin);
        
        foreach ($plugin_headers AS $k => $v)
        {
            if ( !isset(self::$plugins[$plugin][$k]) OR self::$plugins[$plugin]['plugin_info'][$k] != $v )
            {
                self::$plugins[$plugin]['plugin_info'][$k] = trim($v);
            }
            else
            {
                return true;
            }
            
            // Get our plugins to compare meta
            $query = $this->CI->db->where('plugin_system_name', $plugin)->get('plugins')->row();
            
            // If plugin value is different and we're not updating the plugin name
            if (self::$plugins[$plugin]['plugin_info'][$k] != $query->$k AND !stripos($k, "plugin_name"))
            {
                $data[$k] = trim($v);
                $this->CI->db->where('plugin_system_name', $plugin)->update('plugins', $data);
            }  
        }
         
    }
    
    /**
    * Shameless Wordpress rip off. Gets plugin information from header of
    * plugin file.
    * 
    */
    protected function get_plugin_headers($plugin)
    {
        $arr = "";
                
        // Load the plugin we want
        $plugin_data = read_file(self::$plugins_directory.$plugin."/".$plugin.".php");
        
        if ($plugin_data)
        {   	
		    preg_match ( '|Plugin Name:(.*)$|mi', $plugin_data, $name );
		    preg_match ( '|Plugin URI:(.*)$|mi', $plugin_data, $uri );
		    preg_match ( '|Version:(.*)|i', $plugin_data, $version );
		    preg_match ( '|Description:(.*)$|mi', $plugin_data, $description );
		    preg_match ( '|Author:(.*)$|mi', $plugin_data, $author_name );
		    preg_match ( '|Author URI:(.*)$|mi', $plugin_data, $author_uri );
            
            if (isset($name[1]))
            {
                $arr['plugin_name'] = $name[1];
            }
            
            if (isset($uri[1]))
            {
                $arr['plugin_uri'] = $uri[1];
            }
            
            if (isset($version[1]))
            {
                $arr['plugin_version'] = $version[1];
            }
            
            if (isset($description[1]))
            {
                $arr['plugin_description'] = $description[1];
            }
            
            if (isset($author_name[1]))
            {
                $arr['plugin_author'] = $author_name[1];
            }
            
            if (isset($author_uri[1]))
            {
                $arr['plugin_author_uri'] = $author_uri[1];
            }
        }
            
        return $arr;
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
        if ( !isset(self::$hooks[$name]) )
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
        
        // No hook is running any more
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
        unset(self::$hooks[$name][$priority][$function]);
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
    return Plugins::add_action($name, $function, $priority);
}

function run_action($name, $arguments = "")
{
    return Plugins::run_action($name, $arguments);
}

function remove_action($name, $function, $priority=10)
{
    return Plugins::remove_action($name, $function, $priority);
}

function set_plugin_dir($directory)
{
    Plugins::set_plugin_dir($directory);
}

function activate_plugin($name)
{
    return Plugins::instance()->activate_plugin($name);
}

function deactivate_plugin($name)
{
    return Plugins::instance()->deactivate_plugin($name);
}

function count_found_plugins()
{
    return Plugins::instance()->count_found_plugins();
}

function count_activated_plugins()
{
    return Plugins::instance()->count_activated_plugins();
}

function debug_plugins()
{
    Plugins::debug_plugins();
}