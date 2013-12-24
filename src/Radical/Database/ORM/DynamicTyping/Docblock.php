<?php
namespace Radical\Database\ORM\DynamicTyping;

use Radical\Core\Debug;

class Docblock extends Debug\Docblock {
	/**
	 * List of supported docblock tags for the database system.
	 * Only applies to database variables / fields
	 *
	 * @var array
	 */
	public static $tags = array(
			'var'
	);
}