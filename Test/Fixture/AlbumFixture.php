<?php
class AlbumFixture extends CakeTestFixture {
	var $name = 'Album';

	var $fields = array(
		'id' => array('type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 10, 'key' => 'primary'),
		'name' => array('type' => 'string', 'null' => true, 'default' => NULL, 'length' => 64),
		'artist_id' => array('type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 10, 'key' => 'primary'),
		'indexes' => array('PRIMARY' => array('column' => 'id', 'unique' => 1)),
		'tableParameters' => array('charset' => 'utf8', 'collate' => 'utf8_general_ci', 'engine' => 'InnoDB')
	);

	var $records = array(
		array(
			'id' => 1,
			'name' => 'Scurrilous',
			'artist_id' => 1
		),
		array(
			'id' => 2,
			'name' => 'Act 1: The Lake South, The River North',
			'artist_id' => 2
		),
		array(
			'id' => 3,
			'name' => 'Act 2: The Meaning Of, And All Things Regarding Ms. Leading',
			'artist_id' => 2
		),
		array(
			'id' => 4,
			'name' => 'Act 3: Life And Death',
			'artist_id' => 2
		)
	);
}
