<?php
/**
 * 🧠 MATRIX LOCAL AI ENGINE v2.0 (Reinforcement Edition)
 * Theory: TF-IDF, Cosine Similarity & Dynamic Reinforcement Learning
 * 
 * Features:
 * - Algorithmic Pattern Matching
 * - Self-Learning / Reinforcement Mechanism
 * - Metadata Preservation (Icons, Colors, BG)
 */

class MatrixLocalAI {
    private $training_data = [];
    private $vocabulary = [];
    private $document_vectors = [];
    private $doc_freq = [];
    private $file_path = "";

    public function __construct($json_file) {
        $this->file_path = $json_file;
        if (file_exists($json_file)) {
            $json_content = file_get_contents($json_file);
            $this->training_data = json_decode($json_content, true);
            if (is_array($this->training_data)) {
                $this->train();
            }
        }
    }

    private function tokenize($text) {
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        return preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    }

    private function train() {
        $doc_index = 0;
        $this->doc_freq = [];
        $this->vocabulary = [];
        $this->document_vectors = [];

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
                    'tag' => $intent['tag'],
                    'tokens' => $tokens,
                    'responses' => $intent['responses'],
                    'metadata' => [
                        'icon' => $intent['icon'] ?? '',
                        'color' => $intent['color'] ?? '',
                        'bg' => $intent['bg'] ?? ''
                    ]
                ];
                $doc_index++;
            }
        }
        
        $total_docs = count($this->document_vectors);
        foreach ($this->document_vectors as &$doc) {
            $doc['vector'] = $this->calculate_tfidf($doc['tokens'], $total_docs);
        }
    }

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
     * Predict the intent with full metadata support
     */
    public function predict($text, $threshold = 0.25) {
        if (empty($this->training_data) || empty($this->vocabulary)) return false;
        $input_tokens = $this->tokenize($text);
        if (empty($input_tokens)) return false;

        $total_docs = count($this->document_vectors);
        $input_vector = $this->calculate_tfidf($input_tokens, $total_docs);

        $best_score = 0;
        $winner = null;

        foreach ($this->document_vectors as $doc) {
            $score = $this->cosine_similarity($input_vector, $doc['vector']);
            if ($score > $best_score) {
                $best_score = $score;
                $winner = $doc;
            }
        }

        if ($best_score >= $threshold && $winner) {
            return [
                'score' => round($best_score, 4),
                'tag' => $winner['tag'],
                'response' => $winner['responses'][array_rand($winner['responses'])],
                'metadata' => $winner['metadata']
            ];
        }
        return false;
    }

    /**
     * ⚡️ REINFORCEMENT LEARNING ENGINE
     * Teach the AI new patterns for a specific tag.
     */
    public function learn($new_pattern, $target_tag) {
        $found = false;
        foreach ($this->training_data as &$intent) {
            if ($intent['tag'] === $target_tag) {
                // Avoid duplicates
                if (!in_array($new_pattern, $intent['patterns'])) {
                    $intent['patterns'][] = $new_pattern;
                }
                $found = true;
                break;
            }
        }

        if ($found) {
            $this->persist();
            $this->train(); // Re-index the vectors
            return true;
        }
        return false;
    }

    /**
     * Persist the updated knowledge back to the JSON file
     */
    private function persist() {
        if (empty($this->file_path)) return false;
        $json_output = json_encode($this->training_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents($this->file_path, $json_output);
    }
}
