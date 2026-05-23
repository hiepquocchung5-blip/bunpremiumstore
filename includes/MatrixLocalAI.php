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
    private $synonyms = [
        'kpay' => 'kbzpay',
        'kbz' => 'kbzpay',
        'wave' => 'wavepay',
        'wpay' => 'wavepay',
        'cb' => 'cbpay',
        'အဆင်မပြေ' => 'error',
        'မရဘူး' => 'error',
        'စျေး' => 'price',
        'ဈေး' => 'price'
    ];

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

    /**
     * Advanced Tokenization: Unigrams + Bigrams
     */
    private function tokenize($text) {
        $text = mb_strtolower($text, 'UTF-8');
        // Apply Synonyms
        foreach ($this->synonyms as $key => $val) {
            $text = str_replace($key, $val, $text);
        }
        
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $unigrams = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        // Generate Bigrams for deeper context (e.g., "kbz pay" -> ["kbz", "pay", "kbz_pay"])
        $bigrams = [];
        for ($i = 0; $i < count($unigrams) - 1; $i++) {
            $bigrams[] = $unigrams[$i] . '_' . $unigrams[$i+1];
        }
        
        return array_merge($unigrams, $bigrams);
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
            // Boost Bigrams (tokens with underscores) as they are higher value
            $boost = (strpos($word, '_') !== false) ? 1.5 : 1.0;
            $vector[$word] = ($tf * $idf) * $boost;
        }
        return $vector;
    }

    private function cosine_similarity($vec1, $vec2) {
        $dot_product = 0; $norm_a = 0; $norm_b = 0;
        foreach ($this->vocabulary as $word) {
            $val1 = $vec1[$word] ?? 0;
            $val2 = $vec2[$word] ?? 0;
            $dot_product += $val1 * $val2;
            $norm_a += $val1 * $val1;
            $norm_b += $val2 * $val2;
        }
        return ($norm_a == 0 || $norm_b == 0) ? 0 : ($dot_product / (sqrt($norm_a) * sqrt($norm_b)));
    }

    public function predict($text, $threshold = 0.25) {
        if (empty($this->training_data) || empty($this->vocabulary)) return false;
        $input_tokens = $this->tokenize($text);
        if (empty($input_tokens)) return false;

        $total_docs = count($this->document_vectors);
        $input_vector = $this->calculate_tfidf($input_tokens, $total_docs);

        $best_score = 0; $winner = null;
        foreach ($this->document_vectors as $doc) {
            $score = $this->cosine_similarity($input_vector, $doc['vector']);
            if ($score > $best_score) { $best_score = $score; $winner = $doc; }
        }

        if ($best_score >= $threshold && $winner) {
            return [
                'score' => round($best_score, 4),
                'tag' => $winner['tag'],
                'response' => $winner['responses'][array_rand($winner['responses'])],
                'metadata' => $winner['metadata']
            ];
        }

        // 📝 REINFORCEMENT: Log unrecognized pattern for admin training
        $this->log_unrecognized($text);
        return false;
    }

    private function log_unrecognized($text) {
        $log_file = dirname($this->file_path) . '/unrecognized_patterns.json';
        $logs = file_exists($log_file) ? json_decode(file_get_contents($log_file), true) : [];
        if (!is_array($logs)) $logs = [];
        
        $text = trim($text);
        if (empty($text)) return;

        $found = false;
        foreach ($logs as &$entry) {
            if ($entry['pattern'] === $text) { $entry['hits']++; $found = true; break; }
        }
        if (!$found) $logs[] = ['pattern' => $text, 'hits' => 1, 'last_seen' => date('Y-m-d H:i:s')];
        
        // Keep only top 100 patterns
        usort($logs, function($a, $b) { return $b['hits'] <=> $a['hits']; });
        $logs = array_slice($logs, 0, 100);
        
        file_put_contents($log_file, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function learn($new_pattern, $target_tag) {
        $found = false;
        foreach ($this->training_data as &$intent) {
            if ($intent['tag'] === $target_tag) {
                if (!in_array($new_pattern, $intent['patterns'])) $intent['patterns'][] = $new_pattern;
                $found = true; break;
            }
        }
        if ($found) { $this->persist(); $this->train(); return true; }
        return false;
    }

    private function persist() {
        if (empty($this->file_path)) return false;
        return file_put_contents($this->file_path, json_encode($this->training_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
