<?php

class UserMetadata
{
	public $key;

	public function __construct($data)
	{
		$this->key = $data->key;
	}
}
