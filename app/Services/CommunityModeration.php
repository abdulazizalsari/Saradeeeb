<?php
namespace App\Services;

use App\Core\Database;

/** Rule-based moderation. The original text is never changed. */
final class CommunityModeration {
    public static function normalize(string $text): string {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text) ?? $text;
        $text = strtr($text, ['أ'=>'ا','إ'=>'ا','آ'=>'ا','ٱ'=>'ا','ى'=>'ي','ی'=>'ي','ک'=>'ك']);
        $text = preg_replace('/([^\s])\1{2,}/u', '$1$1', $text) ?? $text;
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? $text;
        return trim(mb_strtolower(preg_replace('/\s+/u', ' ', $text) ?? $text, 'UTF-8'));
    }

    /** @return array{level:string, terms:array<int,array<string,mixed>>} */
    public static function inspect(string $text): array {
        $normalized = self::normalize($text);
        if ($normalized === '') return ['level'=>'safe','terms'=>[]];
        $terms = Database::all("SELECT id,phrase,normalized_phrase,severity,exception_phrase FROM community_moderation_terms WHERE active=1 ORDER BY FIELD(severity,'block','review','warning'),id");
        $hits=[]; $rank=['safe'=>0,'warning'=>1,'review'=>2,'block'=>3]; $level='safe';
        foreach ($terms as $term) {
            $needle=(string)$term['normalized_phrase'];
            if ($needle==='' || !str_contains($normalized,$needle)) continue;
            $exception=self::normalize((string)($term['exception_phrase']??''));
            if ($exception!=='' && str_contains($normalized,$exception)) continue;
            $hits[]=['id'=>(int)$term['id'],'phrase'=>(string)$term['phrase'],'severity'=>(string)$term['severity']];
            if (($rank[$term['severity']]??0) > ($rank[$level]??0)) $level=(string)$term['severity'];
        }
        return ['level'=>$level,'terms'=>$hits];
    }

    public static function event(string $type, int $contentId, int $userId, array $result, string $excerpt): void {
        if (($result['level']??'safe') === 'safe') return;
        Database::insert('INSERT INTO community_moderation_events(content_type,content_id,user_id,severity,status,matched_terms,content_excerpt) VALUES(?,?,?,?,\'open\',?,?)',[
            $type,$contentId,$userId,$result['level'],json_encode($result['terms']??[],JSON_UNESCAPED_UNICODE),mb_substr(trim(strip_tags($excerpt)),0,1000)
        ]);
    }
}
