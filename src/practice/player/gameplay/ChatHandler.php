<?php

declare(strict_types=1);

namespace practice\player\gameplay;

use practice\PracticeUtil;

class ChatHandler{
	/** @var array */
	private array $contents = [];

	public function __construct(string $path){
		$contents = file($path);
		foreach($contents as $content){
			$content = strtolower(trim($content));
			$this->contents[$content] = true;
		}
	}

	/**
	 * @param string $msg
	 *
	 * @return string
	 */
	public function getUncensoredMessage(string $msg) : string{

		$result = $msg;

		if($this->hasCensoredWords($msg)){

			$words = $this->getCensoredWordsIn($msg);

			$replacedWords = [];

			foreach($words as $word){

				$key = strval($word);

				$val = mb_substr($key, 0, 1) . "\u{FEFF}" . mb_substr($key, 1);

				$replacedWords[$key] = $val;
			}

			$result = PracticeUtil::str_replace($result, $replacedWords);
		}
		return $result;
	}

	/**
	 * @param string $msg
	 *
	 * @return bool
	 */
	public function hasCensoredWords(string $msg) : bool{
		$censoredWords = $this->getCensoredWordsIn($msg);
		$count = count($censoredWords);
		return $count > 0;
	}

	/**
	 * @param string $msg
	 *
	 * @return array
	 */
	public function getCensoredWordsIn(string $msg) : array{
		$result = [];

		$lowerCaseMsg = strtolower($msg);

		$words = explode(" ", $lowerCaseMsg);

		foreach($words as $word){
			$lowerCaseWord = strtolower($word);

			if(isset($this->contents[$lowerCaseWord])){

				$len = strlen($lowerCaseWord);

				$indexes = PracticeUtil::str_indexes($lowerCaseWord, $lowerCaseMsg);

				foreach($indexes as $index){

					$str = substr($msg, $index, $len);

					if(!PracticeUtil::arr_contains_value($str, $result))
						$result[] = $str;
				}
			}
		}

		return $result;
	}
}