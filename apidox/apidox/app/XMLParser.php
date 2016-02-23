<?php

namespace Application;

require_once realpath(dirname(__FILE__) . '/Apidox.php');
use Application\Apidox;

class XMLParser
{

	public function __construct()
	{
	}

	public function parse(&$apidox, $dirname)
	{
		if (is_null($apidox) || !$apidox instanceof Apidox)
		{
			new \Exception();
		}
		if (is_null($dirname) || !is_string($dirname) || !is_dir($dirname))
		{
			new \Exception();
		}
		
		// =======================================================
		
		$endpointsDictionary = array();
		$errorsDictionary = array();
		
		// -------------------------------------------------------
		
		$endpointsOrdering = array();
		$endpointsCollection = array();
		$errorsCollection = array();
		
		// =======================================================
		
		$resources = scandir($dirname);
		foreach ( $resources as $resource )
		{
			if ($resource === '.' or $resource === '..')
			{
				continue;
			}
			// -------------------------------------------------------
			$filepath = rtrim($dirname, '/') . '/' . rtrim($resource, '/');
			if (is_file($filepath) && strcasecmp($resource, Apidox::CONFIG_XML) === 0)
			{
				$configFile = simplexml_load_file($filepath);
				
				$apidox->setTitle($configFile->attributes()[Apidox::TITLE]);
				$apidox->setUri($configFile->attributes()[Apidox::URI]);
				$apidox->setScheme($configFile->attributes()[Apidox::SCHEME]);
			}
			else if (is_file($filepath) && strcasecmp($resource, Apidox::INDEX_XML) === 0)
			{
				$orderFile = simplexml_load_file($filepath);
				if (isset($orderFile->order))
				{
					foreach ( $orderFile->order as $order )
					{
						if (isset($order->attributes()[Apidox::NAME]))
						{
							$endpointsOrdering[] = (string)$order->attributes()[Apidox::NAME];
						}
					}
				}
			}
			else if (is_file($filepath) && strcasecmp($resource, Apidox::ERRORS_XML) === 0)
			{
				$errorsFile = simplexml_load_file($filepath);
				// -------------------------------------------------------
				if (isset($errorsFile->error) && count($errorsFile->error) > 0)
				{
					$categorized = array();
					$grouped = (string)Apidox::CATEGORY;
					
					foreach ( $errorsFile->error as $error )
					{
						$code = (string)$error->attributes()[Apidox::CODE];
						$description = $error->attributes()[Apidox::DESCRIPTION];
						
						$errorResource = array();
						$errorResource[Apidox::CODE] = $code;
						$errorResource[Apidox::DESCRIPTION] = $description;
						array_push($categorized, $errorResource);
						
						$errorsDictionary[$code][Apidox::CATEGORY] = $grouped;
						$errorsDictionary[$code][Apidox::DESCRIPTION] = $description;
					}
					$errorsCollection[$grouped] = $categorized;
				}
				// -------------------------------------------------------
				if (isset($errorsFile->category) && count($errorsFile->category) > 0)
				{
					foreach ( $errorsFile->category as $category )
					{
						if (isset($category->error) && count($category->error) > 0)
						{
							$categorized = array();
							$grouped = (string)$category->attributes()[Apidox::NAME];
							
							foreach ( $category->error as $error )
							{
								$code = (string)$error->attributes()[Apidox::CODE];
								$description = $error->attributes()[Apidox::DESCRIPTION];
								
								$errorResource = array();
								$errorResource[Apidox::CODE] = $code;
								$errorResource[Apidox::DESCRIPTION] = $description;
								array_push($categorized, $errorResource);
								
								$errorsDictionary[$code][Apidox::CATEGORY] = $grouped;
								$errorsDictionary[$code][Apidox::DESCRIPTION] = $description;
							}
							$errorsCollection[$grouped] = $categorized;
						}
					}
				}
			}
			else if (is_dir($filepath))
			{
				$endpointsResource = array();
				$endpointsResource[Apidox::NAME] = $resource;
				$endpointsResource[Apidox::PATH] = $filepath;
				
				array_push($endpointsCollection, $endpointsResource);
			}
		}
		// =======================================================
		
		if (count($endpointsOrdering) > 0)
		{
			$endpointsDictionary = $this->reorderingEndpoints($endpointsCollection, $endpointsOrdering);
		}
		
		// =======================================================
		
		$methodDictionary = array();
		$methodOrdering = array();
		$apidoxCounter = 0;
		
		foreach ( $endpointsDictionary as &$endpointResource )
		{
			$methodResources = array();
			$methodCounter = 0;
			
			$methods = scandir($endpointResource[Apidox::PATH]);
			foreach ( $methods as $resource )
			{
				if ($resource === '.' or $resource === '..')
				{
					continue;
				}
				// -------------------------------------------------------
				$filepath = rtrim($endpointResource[Apidox::PATH], '/') . '/' . rtrim($resource, '/');
				if (is_file($filepath) && strcasecmp(pathinfo($filepath, PATHINFO_EXTENSION), Apidox::XML) === 0)
				{
					if (strcasecmp(pathinfo($filepath, PATHINFO_FILENAME), Apidox::INDEX) === 0)
					{
						$orderFile = simplexml_load_file($filepath, null, LIBXML_NOCDATA);
						foreach ( $orderFile->order as $order )
						{
							$methodOrdering[] = (string)$order->attributes()[Apidox::NAME];
						}
					}
					else
					{
						$methodFile = simplexml_load_file($filepath);
						if (!isset($methodFile->attributes()[Apidox::HIDDEN]) || strcasecmp($methodFile->attributes()[Apidox::HIDDEN], "N") === 0)
						{
							$apidoxCounter ++;
							$methodCounter ++;
							$name = pathinfo($filepath, PATHINFO_FILENAME);
							
							$methodResource = array();
							$methodResource[Apidox::URI] = $methodFile->attributes()[Apidox::URI];
							$methodResource[Apidox::TYPE] = $methodFile->attributes()[Apidox::TYPE];
							$methodResource[Apidox::DESCRIPTION] = $methodFile->attributes()[Apidox::DESCRIPTION];
							
							$example = trim((string)$methodFile->example);
							if (!is_null($example) && is_string($example) && strlen($example) > 0)
							{
								$methodResource[Apidox::EXAMPLE] = $example;
							}
							$information = trim((string)$methodFile->information);
							if (!is_null($information) && is_string($information) && strlen($information) > 0)
							{
								$methodResource[Apidox::INFORMATION] = $information;
							}
							
							$paramResources = array();
							foreach ( $methodFile->param as $param )
							{
								$paramResource = array();
								$paramResource[Apidox::NAME] = $param->attributes()[Apidox::NAME];
								$paramResource[Apidox::TYPE] = $param->attributes()[Apidox::TYPE];
								$paramResource[Apidox::REQUIRED] = $param->attributes()[Apidox::REQUIRED];
								$paramResource[Apidox::DESCRIPTION] = $param->attributes()[Apidox::DESCRIPTION];
								$paramResource[Apidox::VALUE] = $param->attributes()[Apidox::VALUE];
								
								if (isset($paramResource[Apidox::TYPE]) && strcasecmp($paramResource[Apidox::TYPE], Apidox::TYPE_ENUMERATED) === 0)
								{
									$enumeratedResources = array();
									foreach ( $param->option as $option )
									{
										$optionResource = array();
										$optionResource[Apidox::VALUE] = $option->attributes()[Apidox::VALUE];
										$optionResource[Apidox::DESCRIPTION] = $option->attributes()[Apidox::DESCRIPTION];
										
										array_push($enumeratedResources, $optionResource);
									}
									$paramResource[Apidox::TYPE_ENUMERATED] = $enumeratedResources;
								}
								array_push($paramResources, $paramResource);
							}
							
							$methodResource[Apidox::PARAMS] = $paramResources;
							array_push($methodResources, $methodResource);
						}
					}
				}
			}
			
			// -------------------------------------------------------
			if (count($methodOrdering) > 0)
			{
				$methodDictionary = $this->reorderingMethods($methodResources, $methodOrdering);
			}
			// -------------------------------------------------------
			
			$endpointResource[Apidox::METHODS] = $methodDictionary;
			$endpointResource[Apidox::COUNTER] = $methodCounter;
		}
		
		// =======================================================
		$apidox->setCounter($apidoxCounter);
		
		$apidox->setEndpoints($endpointsDictionary);
		$apidox->setErrors($errorsDictionary);
		// =======================================================
	}

	/**
	 * Proceso para la reorganizacion de endpoints.
	 * 
	 * @param unknown $arrayOriginal        
	 * @param unknown $arrayOrder        
	 * @return Ambigous <multitype:, multitype:NULL
	 */
	private function reorderingEndpoints($arrayOriginal, &$arrayOrder)
	{
		$arrayNew = array();
		$numOrders = count($arrayOrder);
		
		for($currIdx = 0; $currIdx < $numOrders; $currIdx ++)
		{
			$numOriginal = count($arrayOriginal);
			for($j = 0; $j < $numOriginal; $j ++)
			{
				if (strcasecmp($arrayOrder[$currIdx], $arrayOriginal[$j][Apidox::NAME]) === 0)
				{
					$arrayNew[] = array_splice($arrayOriginal, $j, 1)[0];
					break;
				}
			}
		}
		if (count($arrayOriginal) > 0)
		{
			$arrayNew = array_merge($arrayNew, $arrayOriginal);
		}
		
		return $arrayNew;
	}

	/**
	 * Proceso de reordenacion de los metodos de un endpoint.
	 * 
	 * @param unknown $arrayOriginal        
	 * @param unknown $arrayOrder        
	 * @return Ambigous <multitype:, multitype:unknown >
	 */
	private function reorderingMethods($arrayOriginal, $arrayOrder)
	{
		$arrayNew = array();
		$numOrders = count($arrayOrder);
		
		for($currIdx = 0; $currIdx < $numOrders; $currIdx ++)
		{
			if (array_key_exists($arrayOrder[$currIdx], $arrayOriginal))
			{
				$key = $arrayOriginal[$arrayOrder[$currIdx]];
				$arrayNew[$arrayOrder[$currIdx]] = $key;
			}
		}
		if (count($arrayOriginal) > 0)
		{
			$arrayNew = array_merge($arrayNew, $arrayOriginal);
		}
		
		return $arrayNew;
	}
}