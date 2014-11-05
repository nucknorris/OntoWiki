<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2014, {@link http://aksw.org AKSW}
 * @license   http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * Fetches title properties for a set of resources at once.
 * The resources can be defined explicitly or via a SPARQL graph pattern.
 *
 * @category OntoWiki
 * @package  OntoWiki_Classes_Model
 */

require_once 'OntoWiki/Model/AbstractHelper.php';


class OntoWiki_Model_TitleHelper extends OntoWiki_Model_AbstractHelper
{
    /**
     * Whether to always search all configured title properties
     * in order to find the best language match or stop at the
     * first matching title property.
     *
     * @var boolean
     */
    protected $_alwaysSearchAllProperties = false;

    /**
     * Whether to fallback to local names instead of full
     * URIs for unknown resources
     *
     * @var boolean
     */
    protected $_alwaysUseLocalNames = false;

    /**
     * The singleton instance
     *
     * @var OntoWiki_Model_TitleHelper
     */
    private static $_instance = null;


    /**
     * Constructs a new title helper instance.
     *
     * @param Erfurt_Rdf_Model $model The model instance to operate on
     * @param Erfurt_Store $store
     * @param null $config
     * @throws Erfurt_Exception
     * @throws Erfurt_Store_Exception
     * @throws Exception
     */
    public function __construct(Erfurt_Rdf_Model $model = null, Erfurt_Store $store = null, $config = null)
    {
        if (null !== $model) {
            $this->_model = $model;
        }

        $this->_erfurtApp = Erfurt_App::getInstance();

        if (null !== $store) {
            $this->_store = $store;
        } else {
            $this->_store = $this->_erfurtApp->getStore();
        }

        if (null == $config) {
            $config = OntoWiki::getInstance()->config;
        }
        if (is_array($config)) {
            if (isset($config['titleHelper']['properties'])) { // naming properties for resources
                $this->_requestedProperties = array_values($config['titleHelper']['properties']);
            } else {
                $this->_requestedProperties = array();
            }

            // fetch mode
            if (isset($config['titleHelper']['searchMode'])) {
                $this->_alwaysSearchAllProperties = (strtolower($config['titleHelper']['searchMode']) === 'language');
            }
        } else {
            if ($config instanceof Zend_Config) {
                //its possible to define myProperties in config.ini
                if (isset($config->titleHelper->myProperties)) {
                    $this->_requestedProperties = array_values($config->titleHelper->myProperties->toArray());
                } else if (isset($config->titleHelper->properties)) { // naming properties for resources
                    $this->_requestedProperties = array_values($config->titleHelper->properties->toArray());
                } else {
                    $this->_requestedProperties = array();
                }

                // fetch mode
                if (isset($config->titleHelper->searchMode)) {
                    $this->_alwaysSearchAllProperties = (strtolower($config->titleHelper->searchMode) == 'language');
                }
            } else {
                $this->_requestedProperties = array();
            }
        }
        // always use local name for unknown resources?
        if (isset($config->titleHelper->useLocalNames)) {
            $this->_alwaysUseLocalNames = (bool)$config->titleHelper->useLocalNames;
        }
        // add localname to titleproperties
        $this->_requestedProperties[] = 'localname';

        if (null === $this->_languages) {
            $this->_languages = array();
        }
        if (isset($config->languages->locale)) {
            array_unshift($this->_languages, (string)$config->languages->locale);
            $this->_languages = array_unique($this->_languages);
        }
    }

    // ------------------------------------------------------------------------
    // --- Public methods -----------------------------------------------------
    // ------------------------------------------------------------------------
    /**
     * Singleton instance
     *
     * @return OntoWiki_Model_Instance
     */
    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Returns the title property for the resource URI in the requested language.
     * If no title property is found for that language the local part
     * of the resource URI  will be returned.
     *
     * @param string $resourceUri
     * @param string $language The preferred language for the title
     *
     * @return string
     */
    public function getTitle($resourceUri, $language = null)
    {
        return $this->getRequestedProperty($resourceUri, $language);
    }

    /**
     * Add a new title property on top of the list (most important) of title properties
     *
     * @param $propertyUri a string with the URI of the property to add
     */
    public function prependTitleProperty($propertyUri)
    {
        // check if we have a valid URI
        if (Erfurt_Uri::check($propertyUri)) {
            // remove the property from the list if it already exist
            foreach ($this->_requestedProperties as $key => $value) {
                if ($value == $propertyUri) {
                    unset($this->_requestedProperties[$key]);
                }
            }

            // rewrite the array
            $this->_requestedProperties = array_values($this->_requestedProperties);

            // prepend the new URI
            array_unshift($this->_requestedProperties, $propertyUri);

            // reset the TitleHelper to fetch resources with new title properties
            $this->reset();
        }
    }

    /**
     * Resets the title helper, emptying all resources, results and queries stored
     */
    public function reset()
    {
        $this->_resources = array();
    }

    /**
     * extract the localname from given resourceUri
     *
     * @param string resourceUri
     * @return string title
     */
    protected function _extractTitleFromLocalName($resourceUri)
    {
        $title = OntoWiki_Utils::contractNamespace($resourceUri);
        // not even namespace found?
        if ($title == $resourceUri && $this->_alwaysUseLocalNames) {
            $title = OntoWiki_Utils::getUriLocalPart($resourceUri);
        }
        return $title;
    }

    /**
     * If we dont find titles then we extract them from LocalName as a fallback solution
     */
    protected function _fetchFallbackProperties()
    {
        foreach ($this->_resources as $resourceUri => $resource) {
            if ($resource == null) {
                $this->_resources[$resourceUri]['localname']['localname']
                    = $this->_extractTitleFromLocalName($resourceUri);
            }
        }
    }
}
