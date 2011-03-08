<?php

/**
* @name Hooks plugin system for Codeigniter
* @author Dwayne Charrington - http://ilikekillnerds.com
* @coypright Dwayne Charrington 2011
* @licence http://ilikekillnerds.com
*/

class Plugins {
    
    protected $hooks;

    protected $current_hook;
    
    // Array of all plugins
    protected $plugins_array = array();

    public function load_plugins()
    {
        // Load plugins
    }

    public static function add_hook($hook, $function, $priority=10)
    {
        if( !empty($this->hooks[$hook][$priority][$function]) && is_array($this->hooks[$hook][$priority][$function]) )
        {
            return true;
        }

        $this->hooks[$hook][$priority][$function] = array("function" => $function);
        return true;
    }

    public static function run_hooks($hook, $arguments="")
    {
        if(!is_array($this->hooks[$hook]))
        {
            return $arguments;
        }
        $this->current_hook = $hook;
        ksort($this->hooks[$hook]);
        
        foreach($this->hooks[$hook] as $priority => $hooks)
        {
            if (is_array($hooks))
            {
                foreach($hooks as $hook)
                {                    
                    $returnargs = call_user_func_array($hook['function'], array(&$arguments));
                    
                    if($returnargs)
                    {
                        $arguments = $returnargs;
                    }
                }
            }
        }
        $this->current_hook = '';
        return $arguments;
    }  

    public static function remove_hook($hook, $function, $priority=10)
    {
        if(!isset($this->hooks[$hook][$priority][$function]))
        {
            return true;
        }
        unset($this->hooks[$hook][$priority][$function]);
    }
    
}
?>
