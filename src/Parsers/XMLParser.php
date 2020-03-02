<?php
/**
 * Created by PhpStorm.
 * User: sergio.rodenas
 * Date: 12/5/18
 * Time: 0:09
 */

namespace Rodenastyle\StreamParser\Parsers;


use Rodenastyle\StreamParser\Exceptions\IncompleteParseException;
use Rodenastyle\StreamParser\StreamParserInterface;
use Tightenco\Collect\Support\Collection;
use XMLReader;


class XMLParser implements StreamParserInterface
{
	protected $reader,$source;

	protected $skipFirstElement = true;

	public function from(String $source): StreamParserInterface
	{
		$this->source = $source;

		return $this;
	}

	public function withoutSkippingFirstElement(){
		$this->skipFirstElement = false;

		return $this;
	}

	public function each(callable $function)
	{
		$this->start();
		while($this->reader->read()){
			$this->searchElement($function);
		}
		$this->stop();
	}

	private function searchElement(callable $function)
	{
		if($this->isElement() && ! $this->shouldBeSkipped()){
			$function($this->extractElement($this->reader->name));
		}
	}

	private function extractElement(String $elementName, $couldBeAnElementsList = false)
	{
		$elementCollection = (new Collection())->merge($this->getCurrentElementAttributes());

		if($this->isEmptyElement($elementName)) {
			return $elementCollection;
		}

		$this->reader->moveToElement();
		if(substr(preg_replace('/\s+/', '', $this->reader->readOuterXml()), -2, 2) == '/>')
			return $elementCollection;

		while($this->reader->read()) {
			if($this->isEndElement($elementName)) {
				break;
			}
			if($this->isValue()) {
				if($elementCollection->isEmpty()) {
					return trim($this->reader->value);
				} else {
					return $elementCollection->put($elementName, trim($this->reader->value));
				}
			}
			if($this->isElement()) {
				if($couldBeAnElementsList) {
					$foundElementName = $this->reader->name;
					$elementCollection->push(new Collection($this->extractElement($foundElementName)));
				} else {
					$foundElementName = $this->reader->name;
					if ($elementCollection->has($this->reader->name)) {
						$oldElement = $elementCollection->get($foundElementName);
						$wrapperElement = new Collection();

						if($oldElement instanceof Collection) {
							if(!($oldElement->first() instanceof Collection)) {
								$wrapperElement->push(new Collection($oldElement));
							} else {
								foreach ($oldElement as $key => $value) {
									$wrapperElement->push(new Collection($value));
								}
							}
						} else $wrapperElement->push(new Collection($oldElement));

						$element = new Collection($this->extractElement($foundElementName, true));
						$wrapperElement->push($element);

						$elementCollection->put($foundElementName, $wrapperElement);
					} else {
						$elementCollection->put($foundElementName, $this->extractElement($foundElementName, true));
					}
				}
			}
		}

		return $elementCollection;
	}

	private function getCurrentElementAttributes(){
		$attributes = new Collection();
		if($this->reader->hasAttributes)  {
			while($this->reader->moveToNextAttribute()) {
				$attributes->put($this->reader->name, $this->reader->value);
			}
		}
		return $attributes;
	}

	private function start()
	{
		$this->reader = new XMLReader();
		$this->reader->open($this->source);

		return $this;
	}

	private function stop()
	{
		if( ! $this->reader->close()){
			throw new IncompleteParseException();
		}
	}

	private function shouldBeSkipped(){
		if($this->skipFirstElement){
			$this->skipFirstElement = false;
			return true;
		}

		return false;
	}

	private function isElement(String $elementName = null){
		if($elementName){
			return $this->reader->nodeType == XMLReader::ELEMENT && $this->reader->name === $elementName;
		} else {
			return $this->reader->nodeType == XMLReader::ELEMENT;
		}
	}

	private function isEndElement(String $elementName = null){
		if($elementName){
			return $this->reader->nodeType == XMLReader::END_ELEMENT && $this->reader->name === $elementName;
		} else {
			return $this->reader->nodeType == XMLReader::END_ELEMENT;
		}
	}

	private function isValue(){
		return $this->reader->nodeType == XMLReader::TEXT || $this->reader->nodeType === XMLReader::CDATA;
	}

    private function isEmptyElement(String $elementName = null){
	    if($elementName) {
		    return $this->reader->isEmptyElement && $this->reader->name === $elementName;
	    } else {
		    return false;
	    }
    }
}
