<?php
class CacheData2Fixture extends CakeTestFixture {
	var $name = 'CacheData2';

	var $fields = array(
		'id' => array('type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 10, 'key' => 'primary'),
		'name' => array('type' => 'string', 'null' => true, 'default' => NULL, 'length' => 64),
		'description' => array('type' => 'string', 'null' => true, 'default' => NULL, 'length' => 255),
		'created' => array('type' => 'datetime', 'null' => true, 'default' => NULL),
		'modified' => array('type' => 'datetime', 'null' => true, 'default' => NULL),
		'indexes' => array('PRIMARY' => array('column' => 'id', 'unique' => 1)),
		'tableParameters' => array('charset' => 'latin1', 'collate' => 'latin1_swedish_ci', 'engine' => 'MyISAM')
	);

	var $records = array(
		array(
			'id' => 1,
			'name' => 'A Cached Thing In Another Database',
			'description' => 'This is something that should be cached but resides in another database',
			'created' => '2010-05-20 00:00:00',
			'modified' => '2010-05-20 00:00:00'
		),
	);
}
