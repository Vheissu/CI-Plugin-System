<?php

/**
* @name CI System
* @author Dwayne Charrington
* @link http://ilikekillnerds.com
* @coypright Dwayne Charrington 2011
* @licence http://ilikekillnerds.com/dwayne-licence
*/

class Plugins {
    
    private static $table = "plugins"; // the table name the plugins data is stored    

    public  static $instance;          // The instance of this class
    public  static $plugins_directory; // Where our plugins are located
    public  static $hooks;             // Our array of registered hooks
    public  static $current_hook;      // The currently running hook (if any)
    public  static $plugins;           // An array of all plugins
    public  static $run_hooks;         // An array of previously executed hooks
    
    public function __construct()
    {       
        self::$instance = $this; // Store our instance
        
        /**
        * Load Codeigniter helper functions and driver class
        * 
        * @var Plugins
        */
        $this->load->helper('directory');
        $this->load->helper('file');
        $this->load->driver('cache', array('adapter' => 'file'));
        
        // Set the plugins directory if not already set
        if ( is_null(self::$plugins_directory) )
        {
            self::$plugins_directory = FCPATH . "plugins/";   
        }
        
        // Find all plugins
        $this->load_plugins();
        
        // Work our what plugins we have that are activated
        $this->get_activated_plugins();
        
        // Include activated plugins
        $this->include_plugins();
    }
    
    /**
    * Shortcut to Codeigniter instance
    */
    public function __get($bleh)
    {
        $ci = get_instance();
        return $ci->$bleh;
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
    * Scans the plugins directory for valid plugins
    * 
    */
    private function load_plugins()
    {
        // Only go one deep as the plugin file is the same name as the folder
        $plugins = directory_map(self::$plugins_directory, 1);
        
        // Iterate through every plugin found
        foreach ($plugins AS $key => $name)
        {                       
            // If the plugin hasn't already been added and isn't a file
            if ( !isset(self::$plugins[$name]) AND !stripos($name, ".") )
            {                
                // Make sure a valid plugin file by the same name as the folder exists
                if ( file_exists(self::$plugins_directory.$name."/".$name.".php") )
                {
                    // Register the plugin to this class as first unactivated
                    self::$plugins[$name] = array(
                        "is_included" => "false",
                        "activated"   => "false"
                    ); 
                }
            }
            else
            {
                return true;    
            }
        }
    }
    
    /**
    * Get and store all active plugins from the database
    * 
    */
    private function get_activated_plugins()
    {
        $plugins = $this->db->where('plugin_status', 1)->get(self::$table);
        
        // If we have activated plugins
        if ( $plugins->num_rows() > 0 )
        {
            // For every plugin, store it
            foreach ($plugins->result_array() AS $plugin)
            {
                self::$plugins[$plugin['plugin_system_name']]['activated'] = "true";
            }
        }
        else
        {
            return true;
        }
        $plugins->free_result();
    }
    
    /**
    * Includes activated plugins, registers newly found plugins in the database if new ones are found
    * 
    */
    private function include_plugins()
    {
        $this->load->database();
        
        // Validate and include our found plugins
        foreach (self::$plugins AS $name => $data)
        {
            $query = $this->db->where("plugin_system_name", $name)->get(self::$table);
            $row   = $query->row();
            
            // Plugin doesn't exist, add it.
            if ($query->num_rows() == 0)
            {
                // The plugin information being added to the database
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
                $this->db->insert('plugins', $data);
                
                // Trigger an install event
                $this->trigger_install_plugin($name);   
            }
            // The plugin was found 
            elseif ($query->num_rows() == 1)
            {
                // The plugin is set as activated
                if ($row->plugin_status == 1)
                {
                    // If the file was included
                    if (@include_once self::$plugins_directory.$name."/".$name.".php")
                    {
                        self::$plugins[$name]['is_included'] = "true";
                        self::$plugins[$name]['activated']   = "true";
                    }
                    else
                    {
                        self::$plugins[$name]['is_included'] = "false";
                    }
                }
            } 
        }
    }
    
    /**
    * Activates a plugin for use as long as it's valid
    * 
    * @param mixed $plugin
    */
    public function activate_plugin($name)
    {
        // If plugin doesn't exist, just pretend nothing happened and return true
        if ( !isset(self::$plugins[$name]) )
        {
            return true;
        }
        else
        {
            // Set plugin to be activated in the database
            $data['plugin_status'] = 1;
            $this->db->where('plugin_system_name', $name)->update('plugins', $data);
            
            // Update our plugins array to let it know the plugin is activated
            self::$plugins[$name]['activated'] = "true";
            
            // Perform our check of whether or not there is new plugins to include
            $this->include_plugins();
            
            // Trigger an activate event for our plugin
            $this->trigger_activate_plugin($name);
        }
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
            return true;
        }
        else
        {
            // Set the plugin in the database to be deactivated
            $data['plugin_status'] = 0;
            $this->db->where('plugin_system_name', $name)->update('plugins', $data);
            
            // Update our plugins array to let it know the plugin is innactive
            self::$plugins[$name]['activated'] = "false";
            
            // Trigger deactivate event to happen in the plugin
            $this->trigger_deactivate_plugin($name);
            
            // Trigger a refresh of what plugins should be included and what shouldn't
            $this->load_plugins();
        }
    }
    
    /**
    * Triggers the functionname_activate function when a plugin is activated
    * 
    * @param mixed $name
    */
    public function trigger_activate_plugin($name)
    {
        // Call plugin activate function
        @call_user_func($name."_activate");
    }
    
    /**
    * Triggers the functionname_deactivate function when a plugin is deactivated
    * 
    * @param mixed $name
    */
    public function trigger_deactivate_plugin($name)
    {
        // Call our plugin deactivate function
        @call_user_func($name."_deactivate");
    }
    
    /**
    * Triggers the functionname_install function when a plugin is first installed
    * 
    * @param mixed $name
    */
    public function trigger_install_plugin($name)
    {        
        // Call our plugin deactivate function
        @call_user_func($name."_install");
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
        return $this->db->where('plugin_status', 1)->get('plugins')->num_rows();
    }
    
    /**
    * This little function will check if the database has plugins that don't exist
    * and then it will remove them including their data because someone obviously
    * deleted the files and doesn't care about society.
    * 
    */
    private function clean_plugins_table()
    {
        $query = $this->db->get("plugins");
        $rows  = $query->result_array();
        
        // If we have plugins in the database
        if ($query->num_rows() >= 1)
        {
            // Iterate through every plugin pulled from the database and check it exists
            foreach ($rows AS $plugin)
            {
                // If the plugin isn't set in our plugins array, it doesn't exist, so remove it.
                if ( !isset(self::$plugins[$plugin['plugin_system_name']]) )
                {
                    $this->db->delete('plugins', array('plugin_system_name' => $plugin['plugin_system_name']));
                }   
            }
        }
        
        return true;
    }
    
    /**
    * Shameless Wordpress rip off. Gets plugin information from header of
    * plugin file.
    * 
    * 
    */
    private function get_plugin_headers()
    {
        $arr = "";
        
        // Iterate over all plugins
        foreach (self::$plugins AS $plugin => $value )
        {        
            // Load the plugin we want
            $plugin_data = read_file(self::$plugins_directory.$plugin."/".$plugin.".php");
                   
            preg_match ( '|Plugin Name:(.*)$|mi', $plugin_data, $name );
            preg_match ( '|Plugin URI:(.*)$|mi', $plugin_data, $uri );
            preg_match ( '|Version:(.*)|i', $plugin_data, $version );
            preg_match ( '|Description:(.*)$|mi', $plugin_data, $description );
            preg_match ( '|Author:(.*)$|mi', $plugin_data, $author_name );
            preg_match ( '|Author URI:(.*)$|mi', $plugin_data, $author_uri );
            
            if (isset($name[1]))
            {
                $arr['plugin_name'] = trim($name[1]);
            }
            
            if (isset($uri[1]))
            {
                $arr['plugin_uri'] = trim($uri[1]);
            }
            
            if (isset($version[1]))
            {
                $arr['plugin_version'] = trim($version[1]);
            }
            
            if (isset($description[1]))
            {
                $arr['plugin_description'] = trim($description[1]);
            }
            
            if (isset($author_name[1]))
            {
                $arr['plugin_author'] = trim($author_name[1]);
            }
            
            if (isset($author_uri[1]))
            {
                $arr['plugin_author_uri'] = trim($author_uri[1]);
            }
            
            // For every plugin header item
            foreach ($arr AS $k => $v)
            {
                // If the key doesn't exist or the value is not the same, update the array
                if ( !isset(self::$plugins[$plugin][$k]) OR self::$plugins[$plugin]['plugin_info'][$k] != $v )
                {
                    self::$plugins[$plugin]['plugin_info'][$k] = trim($v);
                }
                else
                {
                    return true;
                }
            }
            
            // Get the current plugin from the database
            $query = $this->db->where('plugin_system_name', $plugin)->get('plugins')->row();
            
            // If plugin value is different and we're not updating the plugin name
            if ( self::$plugins[$plugin]['plugin_info'][$k] != $query->$k )
            {
                // Ignore plugin name
                if (!stripos($k, "plugin_name"))
                {
                    $data[$k] = trim($v);
                    $this->db->where('plugin_system_name', $plugin)->update('plugins', $data);
                }
            }
        } 
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
        
        /**
        * Allows us to iterate through multiple action hooks.
        */
        if ( is_array($name) )
        {
            foreach ($name AS $name)
            {
                // Store the action hook in the $hooks array
                self::$hooks[$name][$priority][$function] = array("function" => $function);
            }
        }
        else
        {
            // Store the action hook in the $hooks array
            self::$hooks[$name][$priority][$function] = array("function" => $function);
        }
        
        return true;
    }
    
    /**
    * Trigger an action for a particular action hook
    * 
    * @param mixed $name
    * @param mixed $arguments
    * @return mixed
    */
    public static function do_action($name, $arguments = "")
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
            if ( is_array($names) )
            {
                foreach($names AS $name)
                {
                    // This line runs our function and stores the result in a variable                    
                    $returnargs = call_user_func_array($name['function'], array(&$arguments));
                    
                    if ($returnargs)
                    {
                        $arguments = $returnargs;
                    }
                    
                    // Store our run hooks in the hooks history array
                    self::$run_hooks[$name][$priority];
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
    * Check if a particular hook has been run
    * 
    * @param mixed $hook
    * @param mixed $priority
    */
    public static function has_run($hook, $priority = 10)
    {
        if ( isset(self::$hooks[$hook][$priority]) )
        {
            return true;
        }
        else
        {
            return false;
        }
    }
    
    /**
    * Does a particular action hook even exist?
    * 
    * @param mixed $name
    */
    public static function action_exists($name)
    {
        if ( isset(self::$hooks[$name]) )
        {
            return true;
        }
        else
        {
            return false;
        }
    }
    
    /**
    * It's 3am, do you know where your children are?
    * Returns all found plugins and registered hooks.
    * 
    */
    public static function debug_plugins()
    {
        echo "<p><strong>Plugins count</strong></p>";
        echo count(self::$plugins);
        
        echo "<p><strong>Plugins found</strong></p>";
        
        if (self::$plugins)
        {
            echo "<pre>";
            print_r(self::$plugins);
            echo "</pre>";
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
            echo "<pre>";
            print_r(self::$hooks);
            echo "</pre>";   
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

/**
* Add a new action hook
* 
* @param mixed $name
* @param mixed $function
* @param mixed $priority
*/
function add_action($name, $function, $priority=10)
{
    return Plugins::add_action($name, $function, $priority);
}

/**
* Run an action
* 
* @param mixed $name
* @param mixed $arguments
* @return mixed
*/
function do_action($name, $arguments = "")
{
    return Plugins::do_action($name, $arguments);
}

/**
* Remove an action
* 
* @param mixed $name
* @param mixed $function
* @param mixed $priority
*/
function remove_action($name, $function, $priority=10)
{
    return Plugins::remove_action($name, $function, $priority);
}

/**
* Check if an action actually exists
* 
* @param mixed $name
*/
function action_exists($name)
{
    return Plugins::action_exists($name);
}

/**
* Set the location of where our plugins are located
* 
* @param mixed $directory
*/
function set_plugin_dir($directory)
{
    Plugins::set_plugin_dir($directory);
}

/**
* Activate a specific plugin
* 
* @param mixed $name
*/
function activate_plugin($name)
{
    return Plugins::instance()->activate_plugin($name);
}

/**
* Deactivate a specific plugin
* 
* @param mixed $name
*/
function deactivate_plugin($name)
{
    return Plugins::instance()->deactivate_plugin($name);
}

/**
* Return the number of plugins found
* 
*/
function count_found_plugins()
{
    return Plugins::instance()->count_found_plugins();
}

/**
* Return number of plugins activated
* 
*/
function count_activated_plugins()
{
    return Plugins::instance()->count_activated_plugins();
}

/**
* Debug function will return all plugins registered and hooks
* 
*/
function debug_plugins()
{
    Plugins::debug_plugins();
}