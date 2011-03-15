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

        // Store the Codeigniter instance in our CI variable
        $this->CI = get_instance();
        
        $this->output->enable_profiler(TRUE);
        
        $this->load->helper('directory');
        $this->load->helper('file');
        
        // Set the plugins directory if not already set
        if ( empty(self::$plugins_directory) )
        {
            self::$plugins_directory = FCPATH . "plugins/";   
        }
        
        // Fetch plugins, load them and stuff
        $this->init_tasks();
    }
    
    /**
    * This function lets us access Codeigniter instance objects like;
    * helpers, libraries and core functions without having to prefix
    * our faux Codeigniter instance variable 'CI' we can load Codeigniter
    * libraries and other goodness like we would normally within controllers
    * and other things.
    * 
    * @param mixed $bleh
    */
    public function __get($bleh)
    {
        return $this->CI->$bleh;
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
    * Called by the constructor on load to load plugins, clean old plugin data, etc.
    * 
    */
    private function init_tasks()
    {
        /**
        * Load all plugins from the plugin directory and add them to the plugins array. 
        * Caching will be integrated into this process later on to save resources.
        * 
        * @var Plugins
        */
        $this->load_plugins();
        
        // Register all found plugins if they are activated, etc.
        $this->include_plugins();
        
        // Clean out old plugins that don't exist any more
        $this->clean_plugins_table(); 

        // Get plugin header information
        $this->get_plugin_headers();     
    }
    
    /**
    * Takes care of loading our plugins and making them usable, etc.
    * One lone private function in a sea of static functions.
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
                        "function"    => $name,
                        "is_included" => "false",
                        "activated"   => "false"
                    ); 
                }
            }
            else
            {
                return TRUE;    
            }
        }
    }
    
    /**
    * Includes plugin files if plugins are set to active in the database
    * 
    */
    private function include_plugins()
    {
        $this->load->database();
        
        // Foreach plugin found earlier, validate
        foreach (self::$plugins AS $name => $data)
        {  
            $query = $this->db->where("plugin_system_name", $name)->get("plugins");
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
    * Fetch activated plugins from the database and change their status in the plugins array
    * 
    */
    private function fetch_activated_plugins()
    {
        $query = $this->db->where("plugin_status", 1)->get('plugins')->result_array();
        
        foreach ($query AS $plugin)
        {
            if ( isset(self::$plugins[$plugin['plugin_system_name']]) )
            {
                self::$plugins[$plugin['plugin_system_name']]['activated'];
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
            return TRUE;
        }
        else
        {
            $data = array("plugin_status" => 1);
            $this->db->where('plugin_system_name', $name)->update('plugins', $data);
        }
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
            $this->db->where('plugin_system_name', $name)->update('plugins', $data);
        }
        $this->trigger_deactivate_plugin($name);
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
        
        return TRUE;
    }
    
    /**
    * Shameless Wordpress rip off. Gets plugin information from header of
    * plugin file.
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
        if (is_array($name))
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
            return TRUE;
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
    * Does a particular action hook even exist?
    * 
    * @param mixed $name
    */
    public static function action_exists($name)
    {
        if ( isset(self::$hooks[$name]) )
        {
            return TRUE;
        }
        else
        {
            return FALSE;
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

function action_exists($name)
{
    return Plugins::action_exists($name);
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