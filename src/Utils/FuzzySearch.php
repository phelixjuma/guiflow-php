<?php

namespace PhelixJuma\GUIFlow\Utils;

use FuzzyWuzzy\Fuzz;

class FuzzySearch
{

    private $masterDataType;
    private $corpus;
    private $corpusSearchKey;
    private $corpusIdKey;
    private $corpusValueKey;
    private $stopWords;

    protected $fuzz;


    /**
     *
     */
    public function __construct()
    {
        $this->fuzz = new Fuzz();
    }

    /**
     * @param $corpus
     * @param $corpusSearchKey
     * @param $corpusIdKey
     * @param $corpusValueKey
     * @param $masterDataType
     * @param $stopWords
     * @return $this
     */
    public function setCorpus($corpus, $corpusSearchKey, $corpusIdKey, $corpusValueKey, $masterDataType, $stopWords=[]): FuzzySearch
    {

        $this->corpusSearchKey = $corpusSearchKey;
        $this->corpusIdKey = $corpusIdKey;
        $this->corpusValueKey = $corpusValueKey;
        $this->masterDataType = $masterDataType;
        $this->corpus = $corpus;
        $this->stopWords = $stopWords;


        return $this;
    }

    /**
     * @param $text
     * @param $stopWords
     * @return string
     */
    public static function cleanText($text, $stopWords=[]): string
    {

        if (!is_string($text)) {
            print "arg passed to clean text is not a string: ".json_encode($text);
            return $text;
        }

        // Remove special characters except spaces
        $text = Utils::removeExtraSpaces(preg_replace('/[^a-zA-Z0-9 ]/i', '', $text));

        // Remove stop words
        $moreStopWords = array("and", "the", "is", "in", "to", "for", "on", "of", "with", "at", "by", "an", "be", "this", "that", "it", "from", "as", "are"); // You can expand this list

        // Add the more stop-words
        if (empty($stopWords)) {
            $stopWords = [];
        }
        $stopWords = array_unique(array_merge($stopWords, $moreStopWords));

        // We check whether a wtop word is plain or is a regex pattern
        array_walk($stopWords, function (&$value, $key) {
            // If it's a plain word, add word boundaries to it
            if (!preg_match('/[\\\\^$|()\[\].*+?{}]/', $value)) {
                $value = "\b" . $value . "\b";
            }
        });

        if (!empty($stopWords)) {
            foreach ($stopWords as $word) {
                $text = preg_replace('/' . $word . '/i', '', $text);
            }
        }

        // Remove extra spaces


        return Utils::removeExtraSpaces($text);
    }

    /**
     * @param $query
     * @param $target
     * @param $method
     * @return mixed
     */
    private function getSimilarity($query, $target, $method="tokenSetRatio"): mixed
    {

        // clean before search
        $query = self::cleanText($query, $this->stopWords);
        $target = self::cleanText($target, $this->stopWords);

        return match ($method) {
            'ratio' => $this->fuzz->ratio($query, $target),
            'weightedRatio' => $this->fuzz->weightedRatio($query, $target),
            'partialRatio' => $this->fuzz->partialRatio($query, $target),
            'tokenSetRatio' => $this->fuzz->tokenSetRatio($query, $target),
            'tokenSortRatio' => $this->fuzz->tokenSortRatio($query, $target),
            'tokenSortPartialRatio' => $this->fuzz->tokenSortPartialRatio($query, $target),
            'tokenSetPartialRatio' => $this->fuzz->tokenSetPartialRatio($query, $target)
        };
    }

    /**
     * @param $query
     * @param $similarityThreshold
     * @param int $topN
     * @param $scoringMethod
     * @return array
     */
    private function search($query, $similarityThreshold, int $topN = 1, $scoringMethod="tokenSetRatio"): array
    {

        // We get cosine similarity of embeddings for every item
        $tempCorpus = $this->corpus;

        array_walk($tempCorpus, function (&$value, $key) use($query, $scoringMethod) {
            $value['similarity'] = $this->getSimilarity($value[$this->corpusSearchKey], $query, $scoringMethod);
        });

        // We sort the data by similarity
        usort($tempCorpus, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        // We pick the top n
        $topNItems = array_slice($tempCorpus, 0, $topN);

        // We filter out those whose similarities are above the threshold.
        return  array_filter($topNItems, fn($item) => $item['similarity'] >= $similarityThreshold);
    }

    /**
     * @param $matchedData
     * @return string
     */
    private function formatMatchedValue($matchedData) {

        $id = PathResolver::getValueByPath($matchedData, $this->corpusIdKey);
        $matchingValue = PathResolver::getValueByPath($matchedData, $this->corpusValueKey);
        $searchingValue = PathResolver::getValueByPath($matchedData, $this->corpusSearchKey);
        $value = !empty($matchingValue) ? $matchingValue : $searchingValue;

        return "$value: $id";
    }

    /**
     * @param $matchedData
     * @return array
     */
    private function getMetaData($matchedData = null) {

        $search = !empty($matchedData) ? PathResolver::getValueByPath($matchedData, $this->corpusSearchKey) : null;
        $value = !empty($matchedData) ? PathResolver::getValueByPath($matchedData, $this->corpusValueKey): null;

        return [
            'master_data'   => $this->masterDataType,
            'value_key'     => !empty($this->corpusValueKey) ? $this->corpusValueKey : $this->corpusSearchKey,
            'id_key'        => $this->corpusIdKey,
            'id'            => !empty($matchedData) ? PathResolver::getValueByPath($matchedData, $this->corpusIdKey) : null,
            'value'         => !empty($value) ? $value : $search,
            'matcher'       => 'fuzzy_search',
            'other_details' => $matchedData
        ];
    }

    /**
     * @param $dataToMatch
     * @param $searchKey
     * @param $matchKey
     * @param $corpus
     * @param $corpusSearchKey
     * @param $corpusIdKey
     * @param $corpusValueKey
     * @param $masterDataType
     * @param $similarityThreshold
     * @param $topN
     * @param $scoringMethod
     * @param $stopWords
     * @return array
     */
    public function fuzzyMatch($dataToMatch, $searchKey, $matchKey, $corpus, $corpusSearchKey, $corpusIdKey, $corpusValueKey, $masterDataType, $similarityThreshold=50, $topN=1, $scoringMethod="tokenSetRatio", $stopWords=[]): array
    {

        $isObject = Utils::isObject($dataToMatch);

        if ($isObject) {
            $dataToMatch = [$dataToMatch];
        }

        // We set the corpus
        $this->setCorpus($corpus, $corpusSearchKey, $corpusIdKey, $corpusValueKey, $masterDataType, $stopWords);

        // We set the match key to search key, if not set
        $matchKey = empty($matchKey) ? $searchKey : $matchKey;

        $response = [];

        foreach ($dataToMatch as $searchDatum) {

            // matched value defaults to corpus structure with empty values
            $responseData = $searchDatum;
            $responseData[$matchKey] = [
                "original_value"    => $responseData[$matchKey],
                "matched_value"     => '',
                "similarity"        => '',
                "meta_data"         => $this->getMetaData()
            ];

            if (isset($searchDatum[$searchKey])) {

                //We perform search
                $searchResponse = $this->search($searchDatum[$searchKey], $similarityThreshold, $topN, $scoringMethod);

                if (!empty($searchResponse)) {

                    // We get the top match.
                    $matchedData = $searchResponse[0];
                    $similarity = $matchedData['similarity'];

                    // remove the similarity key from the matched data
                    unset($matchedData['similarity']);

                    $responseData[$matchKey]['matched_value'] = $this->formatMatchedValue($matchedData);
                    $responseData[$matchKey]['similarity'] = $similarity;
                    $responseData[$matchKey]['meta_data'] = $this->getMetaData($matchedData);
                }
            }
            $response[] = !empty($responseData[$matchKey]['matched_value']) ? $responseData : $searchDatum;

        }
        return $isObject ? $response[0] : $response;
    }

    /**
     * @param $data
     * @param $searchKey
     * @param $matchingKey
     * @param $corpus
     * @param $corpusSearchKey
     * @param $corpusIdKey
     * @param $corpusValueKey
     * @param $masterDataType
     * @param $similarityThreshold
     * @param $topN
     * @param $scoringMethod
     * @param $stopWords
     * @return mixed
     */
    public function fuzzySearch($data, $searchKey, $matchingKey, $corpus, $corpusSearchKey, $corpusIdKey, $corpusValueKey, $masterDataType, $similarityThreshold=50, $topN=1, $scoringMethod="tokenSetRatio", $stopWords=[]): mixed
    {

        // We set the corpus
        $this->setCorpus($corpus, $corpusSearchKey, $corpusIdKey, $corpusValueKey, $masterDataType, $stopWords);

        // Get the search phrase
        $searchPhrase = PathResolver::getValueByPath($data, $searchKey);

        if (empty($searchKey) || empty($searchPhrase)) {
            $searchPhrase = $data;
        }

        // Get the match key - if not provided, we use the search key
        $matchingKey = !empty($matchingKey) ? $matchingKey : $searchKey;

        // We get the match phrase - also the original value
        $matchingPhrase = $matchingKey == $searchKey ? $searchPhrase : PathResolver::getValueByPath($data, $matchingKey);

        // matched value defaults to corpus structure with empty values
        $response =  [
            "original_value"    => !empty($matchingPhrase) ? $matchingPhrase : $searchPhrase,
            "matched_value"     => '',
            "similarity"        => '',
            "meta_data"         => $this->getMetaData()
        ];

        // We perform search
        $searchResponse = $this->search($searchPhrase, $similarityThreshold, $topN, $scoringMethod);

        if (!empty($searchResponse)) {

            // We get the top match.
            $matchedData = $searchResponse[0];
            $similarity = $matchedData['similarity'];

            // remove the similarity key from the matched data
            unset($matchedData['similarity']);

            $response['matched_value'] = $this->formatMatchedValue($matchedData);
            $response['similarity'] = $similarity;
            $response['meta_data'] = $this->getMetaData($matchedData);
        }

        // No match found, return original data
        if(empty($response['matched_value'])) {
           return $data;
        }

        // Return the response as a string
        if (is_string($data) && empty($searchKey)) {
            return $response;
        }

        // Return the repose as an object
        PathResolver::setValueByPath($data, $matchingKey, $response);
        return $data;

    }


}

