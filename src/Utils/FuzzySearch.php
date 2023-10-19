<?php

namespace PhelixJuma\DataTransformer\Utils;

use FuzzyWuzzy\Fuzz;

class FuzzySearch
{

    private $masterDataType;
    private $corpus;
    private $corpusSearchKey;
    private $corpusIdKey;
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
     * @param $masterDataType
     * @param $stopWords
     * @return $this
     */
    public function setCorpus($corpus, $corpusSearchKey, $corpusIdKey, $masterDataType, $stopWords=[]): FuzzySearch
    {

        $this->corpusSearchKey = $corpusSearchKey;
        $this->corpusIdKey = $corpusIdKey;
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
    private static function cleanText($text, $stopWords=[]): string
    {

        // Convert text to lowercase
        $text = strtolower($text);

        // Remove URLs
        $text = preg_replace('/https?:\/\/\S+/', '', $text);

        // Remove stop words
        $moreStopWords = array("and", "the", "is", "in", "to", "for", "on", "of", "with", "at", "by", "an", "be", "this", "that", "it", "from", "as", "are"); // You can expand this list

        if (is_array($stopWords) && sizeof($stopWords) > 0) {
            array_walk($stopWords, function (&$v, $k) {
                $v = strtolower($v);
            });
        } else {
            $stopWords = [];
        }

        $stopWords = array_unique(array_merge($stopWords, $moreStopWords));

        foreach ($stopWords as $word) {
            $text = preg_replace('/\b' . $word . '\b/', '', $text);
        }

        // Remove extra spaces
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
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
        return "{$matchedData[$this->corpusSearchKey]} : {$matchedData[$this->corpusIdKey]}";
    }

    /**
     * @param $matchedData
     * @return array
     */
    private function getMetaData($matchedData = null) {

        return [
            'master_data'   => $this->masterDataType,
            'value_key'     => $this->corpusSearchKey,
            'id_key'        => $this->corpusIdKey,
            'id'            => !empty($matchedData) ? $matchedData[$this->corpusIdKey] : null,
            'value'         => !empty($matchedData) ? $matchedData[$this->corpusSearchKey] : null,
            'other_details' => !empty($matchedData) ? Utils::removeKeysFromAssocArray($matchedData, [$this->corpusIdKey, $this->corpusSearchKey]) : null
        ];
    }

    /**
     * @param $dataToMatch
     * @param $searchKey
     * @param $matchKey
     * @param $corpus
     * @param $corpusSearchKey
     * @param $corpusIdKey
     * @param $masterDataType
     * @param $similarityThreshold
     * @param $topN
     * @param $scoringMethod
     * @param $stopWords
     * @return array
     */
    public function fuzzyMatch($dataToMatch, $searchKey, $matchKey, $corpus, $corpusSearchKey, $corpusIdKey, $masterDataType, $similarityThreshold=50, $topN=1, $scoringMethod="tokenSetRatio", $stopWords=[]): array
    {
        // We set the corpus
        $this->setCorpus($corpus, $corpusSearchKey, $corpusIdKey, $masterDataType, $stopWords);

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
            $response[] = $responseData;
        }
        return $response;
    }

    /**
     * @param $searchPhrase
     * @param $corpus
     * @param $corpusSearchKey
     * @param $corpusIdKey
     * @param $masterDataType
     * @param $similarityThreshold
     * @param $topN
     * @param $scoringMethod
     * @param $stopWords
     * @return mixed
     */
    public function fuzzySearch($searchPhrase, $corpus, $corpusSearchKey, $corpusIdKey, $masterDataType, $similarityThreshold=50, $topN=1, $scoringMethod="tokenSetRatio", $stopWords=[]): mixed
    {

        // We set the corpus
        $this->setCorpus($corpus, $corpusSearchKey, $corpusIdKey, $masterDataType, $stopWords);

        // matched value defaults to corpus structure with empty values
        $response =  [
            "original_value"    => $searchPhrase,
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

        return $response;
    }


}

