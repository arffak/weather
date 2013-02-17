<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Pixweb - Abdallah
 * Date: 29/10/12
 * Time: 10:43
 * To change this template use File | Settings | File Templates.
 */
namespace ELAR\WeatherBundle\Weather;

class XMLLoader
{

    private $file;

    /**
     * @param $file
     */
    function __construct($file)
    {
        $this->file = $file;
        //echo $file;
    }

    /**
     * @return \SimpleXMLElement
     * @throws \InvalidArgumentException
     */
    public function load()
    {
        $internalErrors = libxml_use_internal_errors(true);
        $disableEntities = libxml_disable_entity_loader(true);
        libxml_clear_errors();

        $dom = new \DOMDocument();
        $dom->validateOnParse = true;
        if (!$dom->loadXML(file_get_contents($this->file), LIBXML_NONET | (defined('LIBXML_COMPACT') ? LIBXML_COMPACT : 0))) {
            libxml_disable_entity_loader($disableEntities);
            throw new \InvalidArgumentException(implode("\n", $this->getXmlErrors($internalErrors)));
        }
        $dom->normalizeDocument();

        libxml_use_internal_errors($internalErrors);
        libxml_disable_entity_loader($disableEntities);

        foreach ($dom->childNodes as $child) {
            if ($child->nodeType === XML_DOCUMENT_TYPE_NODE) {
                throw new \InvalidArgumentException('Document types are not allowed.');
            }
        }
        return simplexml_import_dom($dom);
    }
}