<?php
/**
 * OJT Journal Assistant Helper Functions
 * Processes raw student notes and structures them into professional journal entries
 */

/**
 * Extract keywords and categorize them into skill types
 */
function journal_extract_skills(string $text): array
{
    $text_lower = strtolower($text);
    
    // Technical Skills Keywords
    $technical_keywords = [
        'coding' => ['coding', 'programming', 'python', 'javascript', 'php', 'java', 'c++', 'react', 'vue', 'angular', 'node.js', 'sql', 'database', 'api', 'rest', 'graphql', 'git', 'github', 'html', 'css', 'xml', 'json', 'framework', 'library', 'cloud', 'aws', 'azure', 'docker', 'kubernetes', 'linux', 'windows', 'debugging', 'testing', 'unit test', 'integration test'],
        'analysis' => ['data analysis', 'analytics', 'excel', 'tableau', 'power bi', 'analytics', 'metrics', 'statistical', 'analytics', 'report', 'dashboard'],
        'design' => ['ui design', 'ux design', 'figma', 'adobe', 'photoshop', 'wireframe', 'prototyping', 'design system', 'user experience'],
        'devops' => ['deployment', 'ci/cd', 'jenkins', 'devops', 'infrastructure', 'aws', 'docker', 'containerization', 'scaling'],
    ];
    
    // Soft Skills Keywords
    $soft_keywords = [
        'communication' => ['communication', 'presentation', 'spoke', 'presented', 'told', 'explained', 'discussed', 'collaborated', 'meetings', 'report writing'],
        'leadership' => ['led', 'leading', 'managed', 'team', 'coordination', 'mentored', 'supervised', 'delegated', 'organized'],
        'problem_solving' => ['solved', 'problem', 'resolved', 'fixed', 'debugging', 'troubleshoot', 'issue', 'challenge', 'overcome'],
        'time_management' => ['deadline', 'timely', 'efficient', 'optimization', 'streamlined', 'improved', 'faster', 'schedule'],
        'adaptability' => ['learned', 'adapted', 'new', 'unfamiliar', 'flexibility', 'adjusted', 'quick learner', 'agile'],
    ];
    
    $found_skills = ['technical' => [], 'soft' => []];
    
    // Technical Skills
    foreach ($technical_keywords as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($text_lower, $keyword) !== false) {
                $found_skills['technical'][] = ucfirst(str_replace('_', ' ', $category));
                break;
            }
        }
    }
    
    // Soft Skills
    foreach ($soft_keywords as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($text_lower, $keyword) !== false) {
                $found_skills['soft'][] = ucfirst(str_replace('_', ' ', $category));
                break;
            }
        }
    }

    $found_skills['technical'] = array_values(array_unique($found_skills['technical']));
    $found_skills['soft'] = array_values(array_unique($found_skills['soft']));

    return $found_skills;
}

/**
 * Extract challenges from text patterns
 */
function journal_extract_challenges(string $text): array
{
    $sentences = preg_split('/[.!?]+/', $text, flags: PREG_SPLIT_NO_EMPTY);
    $challenges = [];
    
    $challenge_indicators = ['difficult', 'challenge', 'struggled', 'issue', 'problem', 'error', 'bug', 'failed', 'encountered', 'hard', 'complex', 'troubleshoot'];
    
    foreach ($sentences as $sentence) {
        $sentence_clean = trim($sentence);
        $sentence_lower = strtolower($sentence_clean);
        
        foreach ($challenge_indicators as $indicator) {
            if (strpos($sentence_lower, $indicator) !== false) {
                $cleaned = trim(preg_replace('/^[-*•~\s]+/', '', $sentence_clean));
                if (!empty($cleaned) && !in_array($cleaned, $challenges)) {
                    $challenges[] = $cleaned;
                }
                break;
            }
        }
    }
    
    return array_slice($challenges, 0, 5); // Limit to 5 challenges
}

/**
 * Generate insights automatically from text
 */
function journal_generate_insights(string $text): array
{
    $insights = [];
    $sentences = preg_split('/[.!?]+/', $text, flags: PREG_SPLIT_NO_EMPTY);
    
    $learning_indicators = ['learned', 'realized', 'discovered', 'understood', 'insight', 'key takeaway', 'important', 'crucial', 'noted', 'observed', 'found that'];
    
    foreach ($sentences as $sentence) {
        $sentence_clean = trim($sentence);
        $sentence_lower = strtolower($sentence_clean);
        
        foreach ($learning_indicators as $indicator) {
            if (strpos($sentence_lower, $indicator) !== false) {
                $cleaned = trim(preg_replace('/^[-*•~\s]+/', '', $sentence_clean));
                if (!empty($cleaned) && !in_array($cleaned, $insights)) {
                    $insights[] = $cleaned;
                }
                break;
            }
        }
    }
    
    return array_slice($insights, 0, 5); // Limit to 5 insights
}

/**
 * Process raw notes and structure them into journal entry format
 */
function journal_process_raw_notes(string $raw_notes, array $ojt_record): array
{
    // Clean and normalize the input
    $notes = trim(htmlspecialchars_decode($raw_notes));
    
    if (empty($notes)) {
        return ['ok' => false, 'error' => 'No notes provided'];
    }
    
    // Split notes by lines and identify sections
    $lines = preg_split('/\r\n|\r|\n/', $notes) ?: [];
    $lines = array_values(array_filter(array_map('trim', $lines), static fn($line) => $line !== ''));
    
    // Initialize structured entry
    $entry = [
        'company_department' => $ojt_record['company_name'] ?? 'N/A',
        'tasks_accomplished' => [],
        'skills_applied_learned' => [],
        'challenges_encountered' => [],
        'solutions_actions_taken' => [],
        'key_learnings_insights' => [],
        'reflection' => ''
    ];

    $sectionKeyMap = [
        'tasks' => 'tasks_accomplished',
        'skills' => 'skills_applied_learned',
        'challenges' => 'challenges_encountered',
        'solutions' => 'solutions_actions_taken',
        'insights' => 'key_learnings_insights',
    ];

    $sectionMatchers = [
        'tasks' => '/^(?:tasks?|accomplishments?|activities?|worked on|work done|completed|did)\b[\s:\-]*/i',
        'skills' => '/^(?:skills?|learned|learning|gained?|techniques?|methods?|improved?|approach)\b[\s:\-]*/i',
        'challenges' => '/^(?:challenges?|struggles?|difficult(?:y|ies)?|issues?|problems?|bugs?|errors?)\b[\s:\-]*/i',
        'solutions' => '/^(?:solutions?|resolved?|fix(?:ed)?|actions?|steps?)\b[\s:\-]*/i',
        'insights' => '/^(?:insights?|learn(?:ing|ings)?|reali[sz](?:e|ed)|thoughts?|reflections?|conclusions?|takeaways?|notes?)\b[\s:\-]*/i',
    ];

    $current_section = 'tasks';
    $section_content = '';

    $pushSectionContent = static function(array &$entry, string $section, string &$content) use ($sectionKeyMap): void {
        $normalized = trim($content);
        if ($normalized === '') {
            $content = '';
            return;
        }

        $targetKey = $sectionKeyMap[$section] ?? 'key_learnings_insights';
        $entry[$targetKey][] = $normalized;
        $content = '';
    };
    
    foreach ($lines as $line) {
        $matchedSection = null;
        $cleanLine = trim($line);
        $strippedLine = $cleanLine;

        foreach ($sectionMatchers as $section => $pattern) {
            if (preg_match($pattern, $cleanLine) === 1) {
                $matchedSection = $section;
                $strippedLine = trim((string) preg_replace($pattern, '', $cleanLine));
                break;
            }
        }

        if ($matchedSection !== null) {
            $pushSectionContent($entry, $current_section, $section_content);
            $current_section = $matchedSection;
            if ($strippedLine !== '') {
                $section_content = $strippedLine;
            }
            continue;
        }

        if (preg_match('/^[-*•~\d+.)]+\s+/', $cleanLine) === 1) {
            $pushSectionContent($entry, $current_section, $section_content);
            $section_content = trim((string) preg_replace('/^[-*•~\d+.)]+\s*/', '', $cleanLine));
        } else {
            $section_content = $section_content === '' ? $cleanLine : ($section_content . ' ' . $cleanLine);
        }
    }
    
    // Add remaining content
    $pushSectionContent($entry, $current_section, $section_content);
    
    // If no structured sections found, treat entire content as task accomplishment
    if (empty($entry['tasks_accomplished']) && empty($entry['skills_applied_learned'])) {
        $entry['tasks_accomplished'] = [$notes];
    }
    
    // Keep reflection empty unless explicitly provided by user input.
    $entry['reflection'] = '';
    
    // Clean and deduplicate
    foreach (['tasks_accomplished', 'skills_applied_learned', 'challenges_encountered', 'solutions_actions_taken', 'key_learnings_insights'] as $key) {
        $entry[$key] = array_values(array_unique(array_filter($entry[$key])));
    }
    
    return ['ok' => true, 'entry' => $entry];
}

/**
 * Generate a personalized reflection statement
 */
function journal_generate_reflection(string $notes, array $entry): string
{
    // Determine sentiment and accomplishment tone
    $positive_words = ['successfully', 'improved', 'completed', 'achieved', 'learned', 'mastered', 'excellent', 'great', 'fantastic'];
    $challenging_words = ['struggled', 'difficult', 'challenging', 'complicated'];
    
    $notes_lower = strtolower($notes);
    $has_positive = false;
    $has_challenging = false;
    
    foreach ($positive_words as $word) {
        if (strpos($notes_lower, $word) !== false) {
            $has_positive = true;
            break;
        }
    }
    
    foreach ($challenging_words as $word) {
        if (strpos($notes_lower, $word) !== false) {
            $has_challenging = true;
            break;
        }
    }
    
    $num_tasks = count($entry['tasks_accomplished'] ?? []);
    $num_skills = count($entry['skills_applied_learned'] ?? []);
    $taskLabel = $num_tasks === 1 ? 'task' : 'tasks';
    
    if ($has_positive && $has_challenging) {
        $reflection = "Today presented both opportunities and challenges. While I successfully tackled {$num_tasks} important {$taskLabel}, I also encountered obstacles that pushed me to think creatively and problem-solve. " .
                     "This experience reinforced the value of perseverance and continuous learning in professional development.";
    } elseif ($has_positive) {
        $topSkills = implode(', ', array_slice($entry['skills_applied_learned'] ?? [], 0, 2));
        if ($topSkills !== '') {
            $reflection = "Today was productive and rewarding. I successfully completed {$num_tasks} {$taskLabel} and continued to develop my {$topSkills} skills. " .
                         "This solid progress reinforces my confidence and dedication to this internship.";
        } else {
            $reflection = "Today was productive and rewarding. I successfully completed {$num_tasks} {$taskLabel} and made meaningful progress in my internship responsibilities. " .
                         "This solid progress reinforces my confidence and dedication to this internship.";
        }
    } elseif ($has_challenging) {
        $reflection = "Today highlighted areas for growth. While facing some challenges, I remained focused and utilized problem-solving techniques to overcome them. " .
                     "These experiences are valuable learning opportunities that will contribute to my professional development.";
    } else {
        $reflection = "Today was an important day in my internship journey. I gained exposure to various aspects of the role and deepened my understanding of the company's operations. " .
                     "Each experience, no matter the complexity, contributes to my overall professional growth.";
    }
    
    return $reflection;
}

/**
 * Format journal entry for display
 */
function journal_format_entry_display(array $entry): array
{
    $formatted = [];
    
    foreach ($entry as $key => $value) {
        if (in_array($key, ['tasks_accomplished', 'skills_applied_learned', 'challenges_encountered', 'solutions_actions_taken', 'key_learnings_insights'])) {
            if (is_array($value)) {
                $formatted[$key] = array_map(function($item) {
                    return is_string($item) ? htmlspecialchars(trim($item)) : $item;
                }, $value);
            }
        } else {
            $formatted[$key] = is_string($value) ? htmlspecialchars($value) : $value;
        }
    }
    
    return $formatted;
}

/**
 * Save journal entry to database
 */
function journal_has_adviser_visibility_column(PDO $pdo): bool
{
    static $hasColumn = null;

    if ($hasColumn !== null) {
        return $hasColumn;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'ojt_journal_entries'
               AND COLUMN_NAME = 'is_visible_to_adviser'"
        );
        $stmt->execute();
        $hasColumn = $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        $hasColumn = false;
    }

    return $hasColumn;
}

function journal_save_entry(PDO $pdo, int $record_id, array $entry, array $log_ids = []): array
{
    try {
        $log_ids_str = implode(',', array_map('intval', $log_ids));
        $isVisibleToAdviser = ((int)($entry['is_visible_to_adviser'] ?? 1) === 0) ? 0 : 1;
        $hasVisibilityColumn = journal_has_adviser_visibility_column($pdo);
        
        // Calculate quality and sentiment if not provided
        $quality_score = isset($entry['quality_score']) ? (int)$entry['quality_score'] : 0;
        $sentiment_analysis = $entry['sentiment_analysis'] ?? 'neutral';
        $productivity_score = isset($entry['productivity_score']) ? (int)$entry['productivity_score'] : 0;

        // Always create a new journal row on each save action.
        if ($hasVisibilityColumn) {
            $stmt = $pdo->prepare('
                INSERT INTO ojt_journal_entries 
                (record_id, log_ids, entry_date, company_department, tasks_accomplished, skills_applied_learned, 
                 challenges_encountered, solutions_actions_taken, key_learnings_insights, reflection, 
                 quality_score, sentiment_analysis, productivity_score, is_visible_to_adviser, created_at)
                VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([
                $record_id,
                $log_ids_str,
                $entry['company_department'] ?? '',
                json_encode($entry['tasks_accomplished'] ?? []),
                json_encode($entry['skills_applied_learned'] ?? []),
                json_encode($entry['challenges_encountered'] ?? []),
                json_encode($entry['solutions_actions_taken'] ?? []),
                json_encode($entry['key_learnings_insights'] ?? []),
                $entry['reflection'] ?? '',
                $quality_score,
                $sentiment_analysis,
                $productivity_score,
                $isVisibleToAdviser
            ]);
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO ojt_journal_entries 
                (record_id, log_ids, entry_date, company_department, tasks_accomplished, skills_applied_learned, 
                 challenges_encountered, solutions_actions_taken, key_learnings_insights, reflection, 
                 quality_score, sentiment_analysis, productivity_score, created_at)
                VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([
                $record_id,
                $log_ids_str,
                $entry['company_department'] ?? '',
                json_encode($entry['tasks_accomplished'] ?? []),
                json_encode($entry['skills_applied_learned'] ?? []),
                json_encode($entry['challenges_encountered'] ?? []),
                json_encode($entry['solutions_actions_taken'] ?? []),
                json_encode($entry['key_learnings_insights'] ?? []),
                $entry['reflection'] ?? '',
                $quality_score,
                $sentiment_analysis,
                $productivity_score
            ]);
        }

        return ['ok' => true, 'message' => 'Journal entry created', 'journal_id' => $pdo->lastInsertId()];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Load all journal entries for a record
 */
function journal_load_entries(PDO $pdo, int $record_id, int $limit = 50): array
{
    try {
        $stmt = $pdo->prepare('
            SELECT * FROM ojt_journal_entries 
            WHERE record_id = ? 
            ORDER BY entry_date DESC, journal_id DESC 
            LIMIT ?
        ');
        $stmt->bindValue(1, $record_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        // Decode JSON fields
        foreach ($entries as &$entry) {
            $entry['tasks_accomplished'] = json_decode($entry['tasks_accomplished'] ?? '[]', true) ?? [];
            $entry['skills_applied_learned'] = json_decode($entry['skills_applied_learned'] ?? '[]', true) ?? [];
            $entry['challenges_encountered'] = json_decode($entry['challenges_encountered'] ?? '[]', true) ?? [];
            $entry['solutions_actions_taken'] = json_decode($entry['solutions_actions_taken'] ?? '[]', true) ?? [];
            $entry['key_learnings_insights'] = json_decode($entry['key_learnings_insights'] ?? '[]', true) ?? [];
            $entry['is_visible_to_adviser'] = ((int)($entry['is_visible_to_adviser'] ?? 1) === 0) ? 0 : 1;
        }
        
        return $entries;
    } catch (Throwable $e) {
        return [];
    }
}

// ======================== ENHANCED NLP PROCESSING ========================

/**
 * Advanced sentiment analysis - determines overall tone and mood
 */
function journal_analyze_sentiment(string $text): array
{
    $text_lower = strtolower($text);
    
    $positive_indicators = [
        'excellent' => 2, 'great' => 2, 'fantastic' => 2, 'amazing' => 2, 'wonderful' => 2,
        'good' => 1.5, 'well' => 1.5, 'successfully' => 1.5, 'achieved' => 1.5, 'accomplished' => 1.5,
        'learned' => 1, 'improved' => 1, 'mastered' => 1, 'progressed' => 1, 'advanced' => 1,
        'confident' => 1, 'proud' => 1, 'inspired' => 1
    ];
    
    $negative_indicators = [
        'failed' => -2, 'disaster' => -2, 'terrible' => -2, 'horrible' => -2, 'awful' => -2,
        'frustrated' => -1.5, 'confused' => -1.5, 'stuck' => -1.5, 'blocked' => -1.5,
        'struggled' => -1, 'difficult' => -1, 'challenging' => -1, 'hard' => -1, 'problem' => -0.5,
        'issue' => -0.5, 'error' => -0.5, 'bug' => -0.5
    ];
    
    $positive_score = 0;
    $negative_score = 0;
    $word_count = 0;
    
    foreach ($positive_indicators as $word => $weight) {
        if (strpos($text_lower, $word) !== false) {
            $positive_score += $weight;
        }
    }
    
    foreach ($negative_indicators as $word => $weight) {
        if (strpos($text_lower, $word) !== false) {
            $negative_score += $weight;
        }
    }
    
    $net_sentiment = $positive_score + $negative_score;
    $sentiment_label = 'neutral';
    
    if ($net_sentiment > 3) {
        $sentiment_label = 'very_positive';
    } elseif ($net_sentiment > 1) {
        $sentiment_label = 'positive';
    } elseif ($net_sentiment < -3) {
        $sentiment_label = 'very_negative';
    } elseif ($net_sentiment < -1) {
        $sentiment_label = 'negative';
    }
    
    return [
        'label' => $sentiment_label,
        'score' => $net_sentiment,
        'positive_indicators' => $positive_score,
        'negative_indicators' => $negative_score
    ];
}

/**
 * Extract action verbs to identify work activity patterns
 */
function journal_extract_action_verbs(string $text): array
{
    $verbs = [
        'developed' => 'Development', 'built' => 'Development', 'coded' => 'Development', 'created' => 'Development',
        'debugged' => 'Problem Solving', 'fixed' => 'Problem Solving', 'resolved' => 'Problem Solving', 'troubleshot' => 'Problem Solving',
        'researched' => 'Research', 'investigated' => 'Research', 'analyzed' => 'Analysis', 'reviewed' => 'Review',
        'tested' => 'Testing', 'validated' => 'Testing', 'verified' => 'Testing',
        'presented' => 'Communication', 'explained' => 'Communication', 'discussed' => 'Communication', 'documented' => 'Documentation',
        'collaborated' => 'Teamwork', 'worked with' => 'Teamwork', 'coordinated' => 'Teamwork', 'mentored' => 'Leadership',
        'deployed' => 'Release', 'launched' => 'Release', 'shipped' => 'Release', 'released' => 'Release',
        'optimized' => 'Optimization', 'improved' => 'Improvement', 'refactored' => 'Code Quality'
    ];
    
    $found_verbs = [];
    $text_lower = strtolower($text);
    
    foreach ($verbs as $verb => $category) {
        if (strpos($text_lower, $verb) !== false && !in_array($category, $found_verbs)) {
            $found_verbs[] = $category;
        }
    }
    
    return $found_verbs;
}

/**
 * Calculate productivity level based on activity density
 */
function journal_calculate_productivity(string $text, array $entry): array
{
    $sentences = preg_split('/[.!?]+/', trim($text), flags: PREG_SPLIT_NO_EMPTY);
    $words = preg_split('/\s+/', trim($text), flags: PREG_SPLIT_NO_EMPTY);
    
    $num_tasks = count($entry['tasks_accomplished'] ?? []);
    $num_skills = count($entry['skills_applied_learned'] ?? []);
    $metrics = [
        'sentences' => count($sentences),
        'words' => count($words),
        'tasks' => $num_tasks,
        'skills' => $num_skills,
        'avg_task_words' => count($words) > 0 ? round(count($words) / (count($sentences) ?? 1)) : 0
    ];
    
    // Productivity score (0-100)
    $score = min(100, 
        ($num_tasks * 15) +           // 15 per task
        ($num_skills * 10) +          // 10 per skill
        min(30, count($words) / 10) + // Up to 30 for word count
        min(20, count($sentences))    // Up to 20 for sentence count
    );
    
    $level = match (true) {
        $score >= 80 => 'Very High',
        $score >= 60 => 'High',
        $score >= 40 => 'Moderate',
        $score >= 20 => 'Low',
        default => 'Minimal'
    };
    
    return [
        'score' => (int) $score,
        'level' => $level,
        'metrics' => $metrics
    ];
}

/**
 * Identify growth areas and recommendations
 */
function journal_suggest_growth_areas(array $entry, string $text): array
{
    $suggestions = [];
    $text_lower = strtolower($text);
    
    $skills = $entry['skills_applied_learned'] ?? [];
    $challenges = $entry['challenges_encountered'] ?? [];
    
    // If many challenges with few solutions, suggest problem-solving focus
    if (count($challenges) > count($entry['solutions_actions_taken'] ?? [])) {
        $suggestions[] = [
            'area' => 'Problem-Solving Strategy',
            'description' => 'Consider developing a more structured approach to problem identification and resolution',
            'priority' => 'medium'
        ];
    }
    
    // If few soft skills mentioned, suggest communication focus
    if (count($skills) < 3) {
        $suggestions[] = [
            'area' => 'Impact Documentation',
            'description' => 'Try to articulate more specific skills and competencies developed each day',
            'priority' => 'low'
        ];
    }
    
    // If lots of technical work, suggest reflection on impact
    if (preg_match_all('/\b(coding|programming|debugging|developing|testing)\b/', $text_lower) >= 3) {
        if (!preg_match('/\b(impact|result|outcome|achieved)\b/', $text_lower)) {
            $suggestions[] = [
                'area' => 'Business Impact',
                'description' => 'Focus on articulating the business value and impact of your technical work',
                'priority' => 'medium'
            ];
        }
    }
    
    // If mentions learning but no clear action items, suggest structured learning
    if (preg_match('/\b(learned|discovered|understood)\b/i', $text) && empty($entry['key_learnings_insights'])) {
        $suggestions[] = [
            'area' => 'Knowledge Retention',
            'description' => 'Document specific insights for future reference and professional development',
            'priority' => 'low'
        ];
    }
    
    return $suggestions;
}

/**
 * Generate overall entry quality score
 */
function journal_calculate_entry_quality(array $entry, string $text): array
{
    $scores = [
        'completeness' => 0,
        'detail_level' => 0,
        'clarity' => 0,
        'reflection' => 0
    ];
    
    // Completeness (has all major sections)
    $has_tasks = !empty($entry['tasks_accomplished']);
    $has_skills = !empty($entry['skills_applied_learned']);
    $has_challenges = !empty($entry['challenges_encountered']);
    $has_reflection = !empty($entry['reflection']) && strlen($entry['reflection']) > 50;
    
    $completeness_score = 0;
    if ($has_tasks) $completeness_score += 25;
    if ($has_skills) $completeness_score += 25;
    if ($has_challenges) $completeness_score += 25;
    if ($has_reflection) $completeness_score += 25;
    $scores['completeness'] = $completeness_score;
    
    // Detail level (word count and depth)
    $word_count = str_word_count($text);
    $scores['detail_level'] = min(100, ($word_count / 2));
    
    // Clarity (sentence structure, no fragments)
    $sentences = preg_split('/[.!?]+/', trim($text), flags: PREG_SPLIT_NO_EMPTY);
    $avg_sentence_length = count($sentences) > 0 ? str_word_count($text) / count($sentences) : 0;
    $scores['clarity'] = min(100, ($avg_sentence_length / 25) * 100);
    
    // Reflection (depth of personal insight)
    $reflection_text = $entry['reflection'] ?? '';
    $insight_words = ['learned', 'realized', 'important', 'valuable', 'growth', 'develop', 'professional', 'skill', 'confidence'];
    $insight_count = 0;
    foreach ($insight_words as $word) {
        if (stripos($reflection_text, $word) !== false) {
            $insight_count++;
        }
    }
    $scores['reflection'] = min(100, ($insight_count / 5) * 100);
    
    $overall = array_sum($scores) / count($scores);
    
    $quality_level = match (true) {
        $overall >= 80 => 'Excellent',
        $overall >= 60 => 'Good',
        $overall >= 40 => 'Fair',
        $overall >= 20 => 'Basic',
        default => 'Minimal'
    };
    
    return [
        'overall' => (int) $overall,
        'level' => $quality_level,
        'breakdown' => $scores
    ];
}

