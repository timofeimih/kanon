<?php
class extendable{
	protected $___methods = array();
	/**
	 * @param extension $extension
	 */
	public function extend($extension){
		$extension->setUpMethods($this);
		$extension->setUp($this);
	}
	/**
	 * Get class property
	 * @param string $name
	 */
	public function &___get($name){
		return $this->{$name};
	}
	/**
	 * Set class property
	 * @param string $name
	 * @param mixed $value
	 */
	public function ___set($name, &$value){
		return $this->{$name} = &$value;
	}
	/**
	 * @param string $name
	 * @param array $arguments
	 */
	public function __call($name, $arguments){
		if (isset($this->___methods[$name])){
			if (is_callable($this->___methods[$name])){
				array_unshift($arguments, $this);
				call_user_func_array($this->___methods[$name], $arguments);
			}
		}
		throw new BadMethodCallException('Tried to call unknown method '.get_class($this).'::'.$f);
	}
}