<?php

namespace Limelight\Plugins\Library\Romanji;

class RomanjiConverter implements RomanjiConverterInterface
{
    /**
     * Number of index values to eat.
     *
     * @var int
     */
    protected $eat;

    /**
     * Can be combined with other characters.
     *
     * @var array
     */
    protected $edible = [
        'ゃ',
        'ゅ',
        'ょ',
        'ぇ',
        'ぃ',
        'あ',
        'い',
        'う',
        'え',
        'お',
    ];

    /**
     * Romanji library.
     *
     * @var array
     */
    protected $conversions = [];

    /**
     * Conversions for 'n'.
     *
     * @var array
     */
    protected $nConversions = [];

    /**
     * Conversions for particles.
     *
     * @var array
     */
    protected $particleConversions = [];

    /**
     * Conversions for small tsu.
     *
     * @var array
     */
    protected $tsuConversions = [];

    /**
     * Acceptable verb combinations.
     *
     * @var array
     */
    protected $verbCombos = [];

    /**
     * Set variables on object.
     *
     * @param array $conversions
     */
    public function setVariables(array $conversions, array $verbCombos, array $nConversions, array $particleConversions, array $tsuConversions)
    {
        $this->conversions = $conversions;
        $this->verbCombos = $verbCombos;
        $this->nConversions = $nConversions;
        $this->particleConversions = $particleConversions;
        $this->tsuConversions = $tsuConversions;
    }

    /**
     * Convert string to romanji.
     *
     * @param string        $string
     * @param LimelightWord $word
     *
     * @return string
     */
    public function convert($string, $word)
    {
        $this->eat = 0;

        $characters = preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY);

        $count = count($characters);

        $results = '';

        for ($index = 0; $index < $count; ++$index) {
            $index = $index + $this->eat;

            if ($index >= $count) {
                break;
            }

            $this->eat = 0;

            $char = $characters[$index];

            $next = (isset($characters[$index + 1]) ?  $characters[$index + 1] : null);

            $nextNext = (isset($characters[$index + 2]) ?  $characters[$index + 2] : null);

            $charToConvert = $this->findCombos($char, $next, $nextNext);

            if ($char === 'っ' && $this->canBeRomanji($next)) {
                $results .= $this->convertSmallTsu($next);

                continue;
            }

            $convertedChar = (isset($this->conversions[$charToConvert]) ? $this->conversions[$charToConvert] : '');

            if ($convertedChar === 'n') {
                $convertedChar = $this->convertN($next, $convertedChar);
            }

            if ($this->particleCanBeConverted($word, $convertedChar)) {
                $convertedChar = $this->particleConversions[$convertedChar];
            }

            if ($this->verbCanBeCombined($convertedChar, $results, $word, $index)) {
                $convertedChar = $this->getConvertedChar($convertedChar, $results);

                $results = substr($results, 0, -1);
            }

            $results .= $convertedChar;
        }

        return $this->upperCaseNames($results, $word);
    }

    /**
     * Find combos through recursion.
     *
     * @param string $current
     * @param string $next
     * @param string $nextNext
     *
     * @return string
     */
    protected function findCombos($current, $next = null, $nextNext = null)
    {
        if ($this->isEdible($next)) {
            $combo = $current.$next;

            if ($this->canBeRomanji($combo)) {
                $current = $combo;

                $current = $this->findCombos($combo, $nextNext);

                $this->eat += 1;
            }
        }

        return $current;
    }

    /**
     * Value is in edible array.
     *
     * @param string $value
     *
     * @return bool
     */
    protected function isEdible($value)
    {
        return in_array($value, $this->edible);
    }

    /**
     * Value is in conversions array.
     *
     * @param string $value
     *
     * @return bool
     */
    protected function canBeRomanji($value)
    {
        return in_array($value, array_keys($this->conversions));
    }

    /**
     * Get the next char from the next hiragana.
     *
     * @param string $next
     *
     * @return string
     */
    private function convertSmallTsu($next)
    {
        $nextRomanji = $this->conversions[$next];

        $nextChar = preg_split('//u', $nextRomanji, -1, PREG_SPLIT_NO_EMPTY)[0];

        if (in_array($nextChar, array_keys($this->tsuConversions))) {
            return $this->tsuConversions[$nextChar];
        }

        return $nextChar;
    }

    /**
     * Convert n if possible.
     *
     * @param string $next
     * @param string $convertedChar
     *
     * @return string
     */
    private function convertN($next, $convertedChar)
    {
        $nextRomanji = (isset($this->conversions[$next]) ? $this->conversions[$next] : null);

        $nextChar = substr($nextRomanji, 0, 1);

        if (in_array($nextChar, array_keys($this->nConversions))) {
            return $this->nConversions[$nextChar];
        }

        return $convertedChar;
    }

    /**
     * Char is particle and in particle conversions array.
     *
     * @param string $word
     * @param string $convertedChar
     *
     * @return bool
     */
    private function particleCanBeConverted($word, $convertedChar)
    {
        return $word->partOfSpeech === 'postposition' && in_array($convertedChar, array_keys($this->particleConversions));
    }

    /**
     * Verb can be combined with the previous verb.
     *
     * @param string        $convertedChar
     * @param string        $results
     * @param LimelightWord $word
     * @param Int           $index
     *
     * @return bool
     */
    private function verbCanBeCombined($convertedChar, $results, $word, $index)
    {
        return $this->equalsPrevious($convertedChar, $results) && $this->inComboArray($convertedChar) && $this->hasLongSound($word, $index);
    }

    /**
     * Char equals the last char on the results string.
     *
     * @param string $convertedChar
     * @param string $results
     *
     * @return bool
     */
    private function equalsPrevious($convertedChar, $results)
    {
        return $convertedChar === substr($results, -1) || ($convertedChar === 'u' && substr($results, -1) === 'o');
    }

    /**
     * The converted char is in the verbCombos array.
     *
     * @param string $convertedChar
     *
     * @return bool
     */
    private function inComboArray($convertedChar)
    {
        return in_array($convertedChar, array_keys($this->verbCombos));
    }

    /**
     * The word pronunciation string indicates a long vowel sound.
     *
     * @param LimelightWord $word
     * @param int           $index
     *
     * @return bool
     */
    private function hasLongSound($word, $index)
    {
        return mb_substr($word->pronunciation, $index, 1) === 'ー';
    }

    /**
     * Get new char from verbCombos array.
     *
     * @param string $convertedChar
     * @param string $results
     *
     * @return string
     */
    private function getConvertedChar($convertedChar, $results)
    {
        if ($convertedChar === 'u' && substr($results, -1) === 'o') {
            return $this->verbCombos['o'];
        }

        return $this->verbCombos[$convertedChar];
    }

    /**
     * Capitalize proper nouns.
     *
     * @param string        $romanji
     * @param LimelightWord $word
     *
     * @return string
     */
    protected function upperCaseNames($romanji, $word)
    {
        if ($word->partOfSpeech === 'proper noun') {
            return mb_convert_case($romanji, MB_CASE_TITLE, 'UTF-8');
        }

        return $romanji;
    }
}