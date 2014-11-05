<?php

/**
 * This file is part of the {@link http://aksw.org/Projects/Erfurt Erfurt} project.
 *
 * @copyright Copyright (c) 2014, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
abstract class OntoWiki_Model_AbstractHelper
{


    /**
     * The languages to consider for requested  properties.
     *
     * @var array
     */
    protected $_languages = array('en', '', 'localname');

    /**
     * The model object to operate on
     *
     * @var Erfurt_Rdf_Model
     */
    protected $_model = null;

    /**
     * The resources for whitch to fetch requested properties
     *
     * @var array
     */
    protected $_resources = array();

    /**
     * Resource query object
     *
     * @var Erfurt_Sparql_SimpleQuery
     */
    protected $_resourceQuery = null;

    /**
     * Erfurt store object
     *
     * @var Erfurt_Store
     */
    protected $_store = null;

    /**
     * Erfurt store object
     *
     * @var Erfurt_App
     */
    protected $_erfurtApp = null;

    /**
     * requestedProperties from configuration
     *
     * @var array
     */
    protected $_requestedProperties = null;


    /**
     * Adds a resource to list of resources for which to query the requested properties.
     *
     * @param Erfurt_Rdf_Resource|string $resource Resource instance or URI
     * @return $this
     */
    public function addResource($resource)
    {
        $resourceUri = (string)$resource;
        if (Erfurt_Uri::check($resourceUri)) {
            if (empty($this->_resources[$resourceUri])) {
                $this->_resources[$resourceUri] = null;
            }
        } else {
            // throw exeption in debug mode only
            if (defined('_OWDEBUG')) {
                $logger = OntoWiki::getInstance()->logger;
                $logger->info('Supplied resource ' . htmlentities('<' . $resource . '>') . ' is not a valid URI.');
            }
        }
        return $this;
    }

    /**
     * Adds a bunch of resources for which to query the requested properties.
     * @param array $resources
     * @return $this
     */
    public function addResources($resources = array(), $variable = null)
    {
        if (null === $variable) {
            foreach ($resources as $resourceUri) {
                $this->addResource($resourceUri);
            }
        } else {
            foreach ($resources as $row) {
                foreach ((array)$variable as $key) {
                    if (!empty($row[$key])) {
                        $object = $row[$key];
                        $toBeAdded = null;
                        if (is_array($object)) {
                            // probably support extended format
                            if (isset($object['type']) && ($object['type'] == 'uri')) {
                                $toBeAdded = $object['value'];
                            }
                        } else {
                            // plain object
                            $toBeAdded = $object;
                        }
                        if ($toBeAdded != null) {
                            $this->addResource($toBeAdded);
                        }
                    }
                }
            }
        }
        return $this;
    }

    /**
     * operate on _resources array and call the method to fetch the requested properties
     * if no requested properties found for the respective resource the localname will be extracted
     *
     * @return void
     */
    protected function _receiveRequestetProperties()
    {
        //first we check if there are resourceUris without the requested property
        $toBeReceived = array();
        foreach ($this->_resources as $resourceUri => $resource) {
            if ($resource == null) {
                $toBeReceived[] = $resourceUri;
            }
        }
        //now we try to receive the requested properties from ResourcePool
        $this->_fetchRequestedPropertiesFromResourcePool($toBeReceived);

        // if nothing found fetch fallback properties, if implemented
        $this->_fetchFallbackProperties();

    }

    /**
     * Fallback method in case we dont find the requested properties.
     */
    abstract protected function _fetchFallbackProperties();

    /**
     * fetches all requested properties according the given array if Uris
     *
     * @param array resourceUris
     */
    protected function _fetchRequestedPropertiesFromResourcePool($resourceUris)
    {
        $resourcePool = $this->_erfurtApp->getResourcePool();
        if (!empty($this->_model)) {
            $modelUri = $this->_model->getModelIri();
            $resources = $resourcePool->getResources($resourceUris, $modelUri);
        } else {
            $resources = $resourcePool->getResources($resourceUris);
        }

        $memoryModel = new Erfurt_Rdf_MemoryModel();
        foreach ($resources as $resourceUri => $resource) {
            $resourceDescription = $resource->getDescription();
            $memoryModel->addStatements($resourceDescription);
            foreach ($this->_requestedProperties as $requestedProperty) {
                $values = $memoryModel->getValues($resourceUri, $requestedProperty);
                foreach ($values as $value) {
                    if (!empty($value['lang'])) {
                        $language = $value['lang'];
                    } else {
                        $language = '';
                    }
                    $this->_resources[$resourceUri][$requestedProperty][$language] = $value['value'];
                }
            }
        }
    }

    /**
     * Returns the requested property for the resource URI in the requested language.
     *
     * @param string $resourceUri
     * @param string $language The preferred language for the commment
     *
     * @return string
     */
    public function getRequestedProperty($resourceUri, $language = null)
    {
        if (!Erfurt_Uri::check($resourceUri)) {
            return $resourceUri;
        }

        // * means any language
        if (trim($language) == '*') {
            $language = null;
        }

        //Have a look if we have an entry for the given resourceUri
        if (!array_key_exists($resourceUri, $this->_resources)) {

            if (defined('_OWDEBUG')) {
                $logger = OntoWiki::getInstance()->logger;
                $logger->info('CommentHelper: getRequestedProperty called for unknown resource. Adding resource before fetch.');
            }
            //If we dont have an entry create one
            $this->addResource($resourceUri);
        }

        // prepend the language that is asked for to the array
        // of languages we will look for
        $languages = $this->_languages;
        if (null !== $language) {
            array_unshift($languages, (string)$language);
        }

        $languages = array_values(array_unique($languages));

        //Have a look if we have already a commment for the given resourceUri
        if ($this->_resources[$resourceUri] === null) {
            $this->_receiveRequestetProperties();
        }

        $foundProperty = null;
        $requestedProperties = $this->_resources[$resourceUri];
        foreach ($languages as $language) {
            foreach ($this->_requestedProperties as $requestedProperty) {
                if (isset($requestedProperties[$requestedProperty][$language]) && !empty($requestedProperties[$requestedProperty][$language])) {
                    $foundProperty = $requestedProperties[$requestedProperty][$language];
                    break(2);
                }
            }
        }
        return $foundProperty;
    }

    /**
     * Resets the helper, emptying all resources, results and queries stored
     */
    abstract public function reset();

}