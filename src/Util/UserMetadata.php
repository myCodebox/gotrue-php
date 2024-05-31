<?php

class UserMetadata
{
	// public array $key;
	
	public $key;

	public function __construct($data)
	{
		$this->key = $data->key;
	}
}
