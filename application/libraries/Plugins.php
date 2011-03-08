<?php

/**
* @name Hooks plugin system for Codeigniter
* @author Dwayne Charrington - http://ilikekillnerds.com
* @coypright Dwayne Charrington 2011
* @licence http://ilikekillnerds.com
*/

class Plugins {
    
    protected $names;

    protected $current_hook;
    
    // Array of all plugins
    protected $plugins_array = array();

    public function load_plugins()
    {
        // Load plugins
    }

    public static function add_action($name, $function, $priority=10)
    {
        if( !empty($this->hooks[$name][$priority][$function]) && is_array($this->hooks[$name][$priority][$function]) )
        {
            return true;
        }

        $this->hooks[$name][$priority][$function] = array("function" => $function);
        return true;
    }

    public static function run_action($name, $arguments="")
    {
        if(!is_array($this->hooks[$name]))
        {
            return $arguments;
        }
        $this->current_hook = $name;
        ksort($this->hooks[$name]);
        
        foreach($this->hooks[$name] as $priority => $names)
        {
            if (is_array($names))
            {
                foreach($names as $name)
                {                    
                    $returnargs = call_user_func_array($name['function'], array(&$arguments));
                    
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

    public static function remove_action($name, $function, $priority=10)
    {
        if(!isset($this->hooks[$name][$priority][$function]))
        {
            return true;
        }
        unset($this->hooks[$name][$priority][$function]);
    }
    
    public static function current_hook()
    {
        return $this->current_hook;
    }
    
}
?>
