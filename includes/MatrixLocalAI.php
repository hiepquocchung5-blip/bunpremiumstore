<?php
/**
 * 🧠 MATRIX LOCAL AI ENGINE (No-API Algorithmic Model)
 * Theory: Term Frequency-Inverse Document Frequency (TF-IDF) & Cosine Similarity
 * 
 * This engine allows the system to process intents locally without relying on an external API.
 * You can write your own training data in `ai_training.json`.
 */

class MatrixLocalAI {
    private $training_data = [];
    private $vocabulary = [];
    private $document_vectors = [];
    private $doc_freq = [];

    public function __construct($json_file) {
        if (file_exists($json_file)) {
            $json_content = file_get_contents($json_file);
            $this->training_data = json_decode($json_content, true);
            if (is_array($this->training_data)) {
                $this->train();
            }
        }
    }

    /**
     * Algorithmic Step 1: Tokenization
     * Breaks Burmese and English sentences down into distinct words/tokens.
     */
    private function tokenize($text) {
        $text = mb_strtolower($text, 'UTF-8');
        // Remove punctuation, keep letters, numbers, and spaces
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        // Split by whitespace
        $tokens = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return $tokens;
    }

    /**
     * Algorithmic Step 2: Training the Vector Space Model
     * Calculates the Document Frequencies across the training set.
     */
    private function train() {
        $doc_index = 0;
        $this->doc_freq = [];
        $this->vocabulary = [];

        foreach ($this->training_data as $intent) {
            foreach ($intent['patterns'] as $pattern) {
                $tokens = $this->tokenize($pattern);
                $unique_tokens = array_unique($tokens);
                
                foreach ($unique_tokens as $token) {
                    if (!isset($this->doc_freq[$token])) $this->doc_freq[$token] = 0;
                    $this->doc_freq[$token]++;
                    if (!in_array($token, $this->vocabulary)) {
                        $this->vocabulary[] = $token;
                    }
                }
                
                $this->document_vectors[$doc_index] = [
                    'intent' => $intent['tag'],
                    'tokens' => $tokens,
                    'responses' => $intent['responses']
                ];
                $doc_index++;
            }
        }
        
        $total_docs = count($this->document_vectors);
        
        // Pre-calculate TF-IDF for all training documents
        foreach ($this->document_vectors as &$doc) {
            $doc['vector'] = $this->calculate_tfidf($doc['tokens'], $total_docs);
        }
    }

    /**
     * Algorithmic Step 3: TF-IDF Formula
     * TF (Term Frequency): (Count of term in doc) / (Total terms in doc)
     * IDF (Inverse Document Frequency): log(Total docs / Number of docs containing term)
     */
    private function calculate_tfidf($tokens, $total_docs) {
        $vector = [];
        $token_counts = array_count_values($tokens);
        $total_tokens = count($tokens);

        if ($total_tokens == 0) return $vector;

        foreach ($this->vocabulary as $word) {
            $tf = isset($token_counts[$word]) ? ($token_counts[$word] / $total_tokens) : 0;
            $idf = isset($this->doc_freq[$word]) && $this->doc_freq[$word] > 0 
                    ? log($total_docs / $this->doc_freq[$word]) 
                    : 0;
            $vector[$word] = $tf * $idf;
        }
        return $vector;
    }

    /**
     * Algorithmic Step 4: Cosine Similarity Formula
     * measures the cosine of the angle between two vectors projected in a multi-dimensional space.
     */
    private function cosine_similarity($vec1, $vec2) {
        $dot_product = 0;
        $norm_a = 0;
        $norm_b = 0;
        
        foreach ($this->vocabulary as $word) {
            $val1 = $vec1[$word] ?? 0;
            $val2 = $vec2[$word] ?? 0;
            
            $dot_product += $val1 * $val2;
            $norm_a += $val1 * $val1;
            $norm_b += $val2 * $val2;
        }
        
        if ($norm_a == 0 || $norm_b == 0) return 0;
        return $dot_product / (sqrt($norm_a) * sqrt($norm_b));
    }

    /**
     * Final Execution: Predict the best matching intent
     */
    public function predict($text, $threshold = 0.25) {
        if (empty($this->training_data) || empty($this->vocabulary)) return false;

        $input_tokens = $this->tokenize($text);
        if (empty($input_tokens)) return false;

        $total_docs = count($this->document_vectors);
        $input_vector = $this->calculate_tfidf($input_tokens, $total_docs);

        $best_score = 0;
        $best_intent = null;
        $best_responses = [];

        foreach ($this->document_vectors as $doc) {
            $score = $this->cosine_similarity($input_vector, $doc['vector']);
            if ($score > $best_score) {
                $best_score = $score;
                $best_intent = $doc['intent'];
                $best_responses = $doc['responses'];
            }
        }

        // If the similarity score passes our mathematical threshold
        if ($best_score >= $threshold && !empty($best_responses)) {
            return [
                'score' => round($best_score, 4),
                'intent' => $best_intent,
                'response' => $best_responses[array_rand($best_responses)] // Pick random variety
            ];
        }

        return false;
    }
}
