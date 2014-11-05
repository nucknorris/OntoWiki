<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2014, {@link http://aksw.org AKSW}
 * @license   http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

require_once 'OntoWiki/Model/AbstractHelper.php';

/**
 * Fetches comment properties for a set of resources at once.
 * The resources can be defined explicitly or via a SPARQL graph pattern.
 *
 * @category OntoWiki
 * @package  OntoWiki_Classes_Model
 */
class OntoWiki_Model_DescriptionHelper extends OntoWiki_Model_AbstractHelper
{

    /**
     * The singleton instance
     *
     * @var OntoWiki_InformationHelper_Comment
     */
    private static $_instance = null;

    /**
     * Constructs a new comment helper instance.
     *
     * @param Erfurt_Rdf_Model $model The model instance to operate on
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
            if (isset($config['descriptionHelper']['properties'])) { // naming properties for resources
                $this->_requestedProperties = array_values($config['descriptionHelper']['properties']);
            } else {
                $this->_requestedProperties = array();
            }
        } else {
            if ($config instanceof Zend_Config) {
                //its possible to define myProperties in config.ini
                if (isset($config->descriptionHelper->myProperties)) {
                    $this->_requestedProperties = array_values($config->descriptionHelper->myProperties->toArray());
                } else if (isset($config->descriptionHelper->properties)) { // naming properties for resources
                    $this->_requestedProperties = array_values($config->descriptionHelper->properties->toArray());
                } else {
                    $this->_requestedProperties = array();
                }

            } else {
                $this->_requestedProperties = array();
            }
        }


        if (null === $this->_languages) {
            $this->_languages = array();
        }
        if (isset($config->languages->locale)) {
            array_unshift($this->_languages, (string)$config->languages->locale);
            $this->_languages = array_unique($this->_languages);
        }

    }

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
     * Resets the helper, emptying all resources, results and queries stored
     */
    public function reset()
    {
        $this->_resources = array();
    }

    /**
     * No fallback comments
     */
    protected function _fetchFallbackProperties()
    {

    }
}
