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
	protected $extra;

	function __construct($type,$size,$default = null, $extra = null){
		$this->type = $type;
		$this->size = $size;
        $this->default = $default;
		$this->extra = $extra;
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

	public function getNull(){
		return $this->null;
	}
	
	protected function _Validate($value){
		return ($value === null && $this->null);
	}
}