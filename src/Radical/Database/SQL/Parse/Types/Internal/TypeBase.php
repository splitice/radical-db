<?php
namespace Radical\Database\SQL\Parse\Types\Internal;

abstract class TypeBase {
	const TYPE = '';
	static function is($type){
		return (static::TYPE == $type);
	}
	
	protected $type;
	protected $size;
	protected $null = false;
    protected $default;
	function __construct($type,$size,$default = null){
		$this->type = $type;
		$this->size = $size;
        $this->default = $default;
	}
	
	function canNull($null){
		$this->null = $null;
	}
	
	/**
	 * @return the $type
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @return the $size
	 */
	public function getSize() {
		return $this->size;
	}

    /**
     * @return null|string
     */
    public function getDefault()
    {
        return $this->default;
    }
	
	function _Validate($value){
		if($value === null && $this->null) return true;
	}
}