<?php

class UserAppMetadata
{
	public $provider;
	public $key;

	public function __construct($data)
	{
		$this->provider = $data->provider;
		$this->key      = $data->key;
	}
}
