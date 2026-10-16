<?php
use App\Core\Auth;
use App\Core\Database;
use App\Core\Csrf;
use App\Core\Integrations;

function config(string $key, mixed $default = null): mixed {
    $value = $GLOBALS['config'] ?? [];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) return $default;
        $value = $value[$part];
    }
    return $value;
}

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
/** A public article translation must be complete and explicitly approved. */
function article_translation_publishable_sql(string $alias='t'): string {
    if(!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/',$alias)) throw new InvalidArgumentException('Invalid SQL alias.');
    return "{$alias}.status='published' AND {$alias}.review_state='approved' AND ({$alias}.locale='ar' OR {$alias}.completeness_state='passed') AND TRIM(COALESCE({$alias}.title,''))<>'' AND TRIM(COALESCE({$alias}.slug,''))<>'' AND TRIM(COALESCE({$alias}.content,''))<>''
        AND EXISTS (SELECT 1 FROM articles source_article JOIN article_translations source_ar ON source_ar.article_id=source_article.id AND source_ar.locale='ar'
            WHERE source_article.id={$alias}.article_id AND source_article.status='published' AND (source_article.published_at IS NULL OR source_article.published_at<=CURRENT_TIMESTAMP)
            AND source_ar.status='published' AND source_ar.review_state='approved' AND TRIM(COALESCE(source_ar.title,''))<>'' AND TRIM(COALESCE(source_ar.slug,''))<>'' AND TRIM(COALESCE(source_ar.content,''))<>''
            AND EXISTS (SELECT 1 FROM article_translations source_original WHERE source_original.article_id=source_article.id AND source_original.locale=COALESCE(NULLIF(source_article.original_locale,''),'ar')
                AND source_original.status='published' AND source_original.review_state='approved' AND TRIM(COALESCE(source_original.title,''))<>'' AND TRIM(COALESCE(source_original.slug,''))<>'' AND TRIM(COALESCE(source_original.content,''))<>''))";
}

/**
 * One publication decision governs an original literary work and its required
 * Arabic version. This is deliberately server-side so UI controls and bulk
 * actions cannot expose only one half of a foreign-language work.
 */
function article_publication_ready(int $articleId): bool {
    if($articleId<=0) return false;
    $article=Database::one('SELECT original_locale FROM articles WHERE id=?',[$articleId]);
    if(!$article) return false;
    $original=(string)($article['original_locale']??'ar');
    if(!isset(article_locales()[$original])) $original='ar';
    $source=Database::one('SELECT title,slug,content,review_state FROM article_translations WHERE article_id=? AND locale=?',[$articleId,$original]);
    $arabic=Database::one("SELECT title,slug,content,review_state FROM article_translations WHERE article_id=? AND locale='ar'",[$articleId]);
    $complete=static fn(?array $row): bool => $row && ($row['review_state']??'')==='approved'
        && trim((string)($row['title']??''))!=='' && trim((string)($row['slug']??''))!==''
        && trim(strip_tags((string)($row['content']??'')))!=='';
    return $complete($source) && $complete($arabic);
}
/** Article source language is independent of the site's default interface language. */
function article_locales(): array {
    $languages=enabled_locales();
    return ['ar'=>all_locales()['ar'] ?? ['name'=>'العربية','dir'=>'rtl']] + $languages;
}
function article_translation_state(?array $translation): string {
    if(!$translation) return 'غير مترجمة';
    return ($translation['status']??'')==='published' && ($translation['review_state']??'')==='approved'
        && trim((string)($translation['title']??''))!=='' && trim((string)($translation['slug']??''))!=='' && trim(strip_tags((string)($translation['content']??'')))!=='' ? 'منشورة' : 'مسودة';
}
function url(string $path = ''): string {
    $path = '/' . ltrim($path, '/');
    $default = default_locale();
    if ($path === '/' . $default) $path = '/';
    elseif (str_starts_with($path, '/' . $default . '/')) $path = substr($path, strlen($default) + 1) ?: '/';

    if (PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string)$_SERVER['HTTP_HOST']) ?: 'localhost';
        return ($https ? 'https' : 'http') . '://' . $host . ($path === '/' ? '/' : $path);
    }
    $base = rtrim((string)config('base_url', ''), '/');
    // CLI jobs and tests must not silently generate links to a fixed domain.
    // Configure APP_BASE_URL (or local.php) whenever an absolute URL is needed.
    return $base . ($path === '/' ? '/' : $path);
}

function locale_url(string $locale, string $path = ''): string {
    $default = default_locale();
    $suffix = trim($path, '/');
    if ($locale === $default) return url('/' . $suffix);
    return url('/' . $locale . ($suffix !== '' ? '/' . $suffix : ''));
}

/** Build a language link for the page currently being viewed.
 *  Content routes use their translated slug when one exists; otherwise the
 *  user is taken to that locale's home page instead of a misleading 404.
 */
function localized_current_url(string $targetLocale, bool $strict=false): string {
    $requestPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $query = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_QUERY) ?: '');
    $default = default_locale();
    $segments = array_values(array_filter(explode('/', trim($requestPath, '/')), static fn($v) => $v !== ''));
    $sourceLocale = $default;
    if ($segments && valid_locale((string)$segments[0]) && (string)$segments[0] !== $default) $sourceLocale = (string)array_shift($segments);
    $kind = (string)($segments[0] ?? '');
    $slug = (string)($segments[1] ?? '');
    $path = null;
    try {
        if ($kind === 'article' && $slug !== '') {
            // A switch may only originate from an actually public article too.
            // This keeps a stale/draft URL from producing a fake translated link.
            $source = Database::one("SELECT article_id FROM article_translations WHERE locale=? AND slug=? AND ".article_translation_publishable_sql('article_translations')." LIMIT 1", [$sourceLocale, $slug]);
            if ($source) {
                $translated = Database::one("SELECT slug FROM article_translations WHERE article_id=? AND locale=? AND ".article_translation_publishable_sql('article_translations')." LIMIT 1", [(int)$source['article_id'], $targetLocale]);
                if ($translated && trim((string)$translated['slug']) !== '') $path = 'article/' . $translated['slug'];
            }
        } elseif ($kind === 'page' && $slug !== '') {
            $source = Database::one("SELECT page_id FROM page_translations WHERE locale=? AND slug=? AND ".page_translation_publishable_sql('page_translations')." LIMIT 1", [$sourceLocale, $slug]);
            if ($source) {
                $translated = Database::one("SELECT slug FROM page_translations WHERE page_id=? AND locale=? AND ".page_translation_publishable_sql('page_translations')." LIMIT 1", [(int)$source['page_id'], $targetLocale]);
                if ($translated) $path = 'page/' . $translated['slug'];
            }
        } elseif ($kind === 'category' && $slug !== '') {
            $source = Database::one('SELECT category_id FROM category_translations WHERE locale=? AND slug=? LIMIT 1', [$sourceLocale, $slug]);
            if ($source) {
                $translated = Database::one("SELECT slug FROM category_translations WHERE category_id=? AND locale=? AND status='published' AND TRIM(slug)<>'' LIMIT 1", [(int)$source['category_id'], $targetLocale]);
                if ($translated) $path = 'category/' . $translated['slug'];
            }
        } elseif ($kind === 'podcast' && $slug !== '') {
            $source = Database::one('SELECT episode_id FROM podcast_episode_translations WHERE locale=? AND slug=? LIMIT 1', [$sourceLocale, $slug]);
            if ($source) {
                $translated = Database::one("SELECT slug FROM podcast_episode_translations WHERE episode_id=? AND locale=? AND status='published' LIMIT 1", [(int)$source['episode_id'], $targetLocale]);
                if ($translated) $path = 'podcast/' . $translated['slug'];
            }
        } elseif ($kind === 'khawatir' && $slug !== '') {
            $source = Database::one('SELECT khawatir_id FROM khawatir_translations WHERE locale=? AND slug=? LIMIT 1', [$sourceLocale, $slug]);
            if ($source) {
                $translated = Database::one("SELECT slug FROM khawatir_translations WHERE khawatir_id=? AND locale=? AND status='published' LIMIT 1", [(int)$source['khawatir_id'], $targetLocale]);
                if ($translated) $path = 'khawatir/' . $translated['slug'];
            }
        } elseif ($kind === 'writer' && $slug !== '') {
            $path = 'writer/' . $slug;
        } elseif ($kind === 'behind-saradeeb') {
            // Retain the legacy short URL only as a redirect target.  The
            // editable page itself has an Arabic, content-owned canonical URL.
            $behind = \App\Services\BehindPage::published($targetLocale);
            $path = $behind ? 'page/'.$behind['slug'] : null;
        } elseif (($managedKey=\App\Services\ManagedPages::pathKey('/'.implode('/',$segments)))!==null) {
            $path=\App\Services\ManagedPages::forKey($managedKey,$targetLocale)?implode('/',$segments):null;
        } elseif (in_array($kind, ['podcast','khawatir','writers','contact','login','register','forgot-password','community'], true)) {
            $path = implode('/', $segments);
        } elseif ($kind === 'search') {
            $path = 'search';
        } elseif ($kind === '') {
            $path = '';
        }
    } catch (Throwable) {}
    if ($path === null && $strict) return '';
    $href = locale_url($targetLocale, $path ?? '');
    return $query !== '' ? $href . '?' . $query : $href;
}

function current_hreflang_urls(): array {
    $urls=[];
    foreach(enabled_locales() as $code=>$info){
        $href=localized_current_url($code,true);
        if($href!=='') $urls[$code]=$href;
    }
    if(isset($urls[default_locale()])) $urls['x-default']=$urls[default_locale()];
    return $urls;
}

function default_locale(): string {
    try {
        $saved = trim(setting('default_locale', ''));
        if ($saved !== '' && valid_locale($saved)) return $saved;
    } catch (Throwable) {}
    return (string)config('default_locale', 'ar');
}

function all_locales(): array {
    try {
        $rows = Database::all("SELECT code,name,flag,dir,enabled,is_default,sort_order FROM languages ORDER BY sort_order,id");
        if ($rows) {
            $out=[];
            foreach ($rows as $r) $out[$r['code']]=[
                'name'=>$r['name'], 'flag'=>$r['flag'] ?: '🌐', 'dir'=>$r['dir']==='rtl'?'rtl':'ltr',
                'locale'=>$r['code'], 'enabled'=>(int)$r['enabled'], 'is_default'=>(int)$r['is_default'], 'sort_order'=>(int)$r['sort_order']
            ];
            uasort($out,static fn($a,$b)=>(int)($a['sort_order']??0)<=>(int)($b['sort_order']??0));
            return $out;
        }
    } catch (Throwable) {}
    $all=config('locales', []);
    foreach($all as $code=>&$info){ $info['flag']=$info['flag']??(['ar'=>'🇸🇾','en'=>'🇬🇧','es'=>'🇪🇸','fr'=>'🇫🇷'][$code]??'🌐'); $info['enabled']=1; $info['is_default']=$code===(string)config('default_locale','ar')?1:0; }
    return $all;
}

function enabled_locales(): array {
    $all=all_locales(); $out=[];
    foreach($all as $code=>$info) if((int)($info['enabled']??1)===1) $out[$code]=$info;
    $default=(string)config('default_locale','ar');
    try { foreach($all as $code=>$info) if(!empty($info['is_default'])) { $default=$code; break; } } catch(Throwable) {}
    if(isset($all[$default]) && !isset($out[$default])) $out=[$default=>$all[$default]]+$out;
    return $out ?: $all;
}

function language_name(string $locale): string {
    $info=locale_info($locale); return (string)($info['name'] ?? strtoupper($locale));
}
function localized_language_name(string $language,string $uiLocale): string {
    $names=[
        'ar'=>['ar'=>'العربية','en'=>'Arabic','es'=>'Arabe','fr'=>'Arabe','ru'=>'Арабский'],
        'en'=>['ar'=>'الإنجليزية','en'=>'English','es'=>'Ingles','fr'=>'Anglais','ru'=>'Английский'],
        'es'=>['ar'=>'الإسبانية','en'=>'Spanish','es'=>'Espanol','fr'=>'Espagnol','ru'=>'Испанский'],
        'fr'=>['ar'=>'الفرنسية','en'=>'French','es'=>'Frances','fr'=>'Francais','ru'=>'Французский'],
        'ru'=>['ar'=>'الروسية','en'=>'Russian','es'=>'Ruso','fr'=>'Russe','ru'=>'Русский'],
    ];
    return $names[$language][$uiLocale] ?? language_name($language);
}
function language_flag(string $locale): string {
    $info=locale_info($locale); return (string)($info['flag'] ?? '🌐');
}

function language_flag_country(string $locale): string {
    $raw=trim(language_flag($locale));
    $prefix=strtolower(substr($locale,0,2));

    // هوية سراديب العربية: استخدم علم سوريا الجديد (الأخضر/الأبيض/الأسود مع ثلاث نجوم حمراء).
    // هذا يتجاوز القيمة القديمة sa إذا كانت محفوظة في قاعدة البيانات، بدون الحاجة لتعديلها يدويًا.
    if ($prefix === 'ar') return 'sy';

    if (preg_match('/^[A-Za-z]{2}$/', $raw)) return strtolower($raw);
    $emojiMap=[
        '🇸🇦'=>'sa','🇸🇾'=>'sy','🇬🇧'=>'gb','🇺🇸'=>'us','🇪🇸'=>'es','🇷🇺'=>'ru','🇫🇷'=>'fr','🇹🇷'=>'tr','🇩🇪'=>'de','🇮🇹'=>'it','🇵🇹'=>'pt','🇨🇳'=>'cn','🇯🇵'=>'jp','🇰🇷'=>'kr'
    ];
    if(isset($emojiMap[$raw])) return $emojiMap[$raw];
    $localeMap=['en'=>'gb','es'=>'es','ru'=>'ru','fr'=>'fr','tr'=>'tr','de'=>'de','it'=>'it','pt'=>'pt','zh'=>'cn','ja'=>'jp','ko'=>'kr'];
    return $localeMap[$prefix] ?? 'globe';
}

function asset(string $path): string { return url('assets/' . ltrim($path, '/')); }
function language_flag_src(string $locale): string {
    $raw=trim(language_flag($locale));
    if($raw!=='' && preg_match('#^(?:https?://|data:image/|/)#i',$raw)) return $raw;
    $code=language_flag_country($locale);
    return asset('flags/'.$code.'.svg');
}
function locale_info(string $locale): array { $all=all_locales(); return $all[$locale] ?? ($all[default_locale()] ?? ['name'=>$locale,'flag'=>'🌐','dir'=>'ltr','locale'=>$locale]); }
function valid_locale(string $locale): bool { return array_key_exists($locale, enabled_locales()); }
function csrf_field(): string { return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">'; }
function auth_user(): ?array { return Auth::user(); }
function is_admin(): bool { return Auth::checkRole(['admin']); }
function is_editor(): bool { return Auth::checkRole(['admin', 'editor']); }
function is_writer(): bool { return Auth::checkRole(['admin', 'editor', 'writer', 'trusted_writer']); }
function flash(string $key, ?string $value = null): ?string {
    if ($value !== null) { $_SESSION['_flash'][$key] = $value; return null; }
    $v = $_SESSION['_flash'][$key] ?? null; unset($_SESSION['_flash'][$key]); return $v;
}
function old(string $key, string $default=''): string { return e($_SESSION['_old'][$key] ?? $default); }
function set_old(array $data): void { $_SESSION['_old'] = $data; }
function clear_old(): void { unset($_SESSION['_old']); }
function redirect(string $to, int $status=302): never { header('Location: ' . $to, true, $status); exit; }

function slugify(string $text): string {
    $text = trim(mb_strtolower($text));
    $text = preg_replace('/[^\p{L}\p{N}\s\-_]+/u', '', $text) ?? '';
    $text = preg_replace('/[\s_]+/u', '-', $text) ?? '';
    $text = preg_replace('/-+/u', '-', $text) ?? '';
    return trim($text, '-') ?: bin2hex(random_bytes(4));
}


function normalize_writer_username(string $value): string {
    $value=strtolower(trim($value));
    $value=preg_replace('/[^a-z0-9_-]+/','',$value) ?? '';
    $value=preg_replace('/[-_]{2,}/','-', $value) ?? '';
    return trim($value,'-_');
}

function writer_username_reserved(string $username): bool {
    $reserved=['admin','administrator','root','system','api','login','logout','register','writer','writers','author','authors','account','profile','settings','dashboard','editor','support','contact','about','privacy','terms','search','category','article','page','community','saradeeeb'];
    return in_array(strtolower($username),$reserved,true);
}

function valid_writer_username(string $username): bool {
    $username=normalize_writer_username($username);
    return (bool)preg_match('/^[a-z][a-z0-9_-]{2,29}$/',$username) && !writer_username_reserved($username);
}

function writer_url(string $username, ?string $locale=null): string {
    $locale=$locale ?: default_locale();
    return locale_url($locale,'writer/'.rawurlencode($username));
}

function request_locale(): string {
    $path=(string)(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');
    $first=(string)(explode('/',trim($path,'/'))[0]??'');
    return $first!==''&&valid_locale($first)?$first:default_locale();
}
function writer_badges_html(array $user,?string $locale=null): string {
    $locale=$locale?:request_locale();
    $labels=[
        'ar'=>['verified'=>'كاتب موثق','honored'=>'كاتب مميز في سراديب'],
        'en'=>['verified'=>'Verified writer','honored'=>'Featured Saradeeeb writer'],
        'es'=>['verified'=>'Autor verificado','honored'=>'Autor destacado de Saradeeeb'],
        'fr'=>['verified'=>'Auteur verifie','honored'=>'Auteur Saradeeeb a l honneur'],
        'ru'=>['verified'=>'Проверенный автор','honored'=>'Избранный автор Saradeeeb'],
    ][$locale]??['verified'=>'Verified writer','honored'=>'Featured Saradeeeb writer'];
    $out='';
    if(!empty($user['writer_verified'])) $out.='<span class="writer-badge writer-badge-verified" title="'.e($labels['verified']).'" aria-label="'.e($labels['verified']).'">✓</span>';
    if(!empty($user['writer_honored'])) $out.='<span class="writer-badge writer-badge-honored" title="'.e($labels['honored']).'" aria-label="'.e($labels['honored']).'">●</span>';
    return $out;
}

function sanitize_html(string $html): string {
    // Prefer a DOM allow-list sanitizer; fall back to conservative strip_tags when DOM is unavailable.
    if (!class_exists('DOMDocument')) {
        $allowed = '<p><br><h2><h3><h4><strong><b><em><i><u><ul><ol><li><blockquote><a><img><figure><figcaption><hr><code><pre>';
        $html = strip_tags($html, $allowed);
        $html = preg_replace('/\s+(?:on\w+|style|srcdoc|formaction)\s*=\s*(["\']).*?\1/isu', '', $html) ?? $html;
        $html = preg_replace('/(href|src)\s*=\s*(["\'])\s*(?:javascript|vbscript):.*?\2/isu', '$1="#"', $html) ?? $html;
        return $html;
    }
    $allowedTags = ['p','br','h2','h3','h4','strong','b','em','i','u','ul','ol','li','blockquote','a','img','figure','figcaption','hr','code','pre'];
    $allowedAttrs = [
        'a' => ['href','title','target','rel'],
        'img' => ['src','alt','title','width','height','loading'],
        'code' => ['class'], 'pre' => ['class']
    ];
    $dom = new DOMDocument('1.0','UTF-8');
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?><div id="sar-wrap">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors(); libxml_use_internal_errors($previous);
    $wrap = $dom->getElementById('sar-wrap'); if (!$wrap) return '';
    $walk = function($node) use (&$walk,$allowedTags,$allowedAttrs) {
        for ($child=$node->firstChild; $child;) {
            $next=$child->nextSibling;
            if ($child instanceof DOMElement) {
                $tag=strtolower($child->tagName);
                if (!in_array($tag,$allowedTags,true)) {
                    $walk($child); while($child->firstChild) $node->insertBefore($child->firstChild,$child);
                    $node->removeChild($child); $child=$next; continue;
                }
                $keep=$allowedAttrs[$tag]??[];
                $remove=[]; foreach($child->attributes as $attr) if(!in_array(strtolower($attr->name),$keep,true)) $remove[]=$attr->name;
                foreach($remove as $name) $child->removeAttribute($name);
                foreach(['href','src'] as $attr){
                    if(!$child->hasAttribute($attr)) continue; $v=trim($child->getAttribute($attr));
                    $ok=str_starts_with($v,'/')||str_starts_with($v,'#')||preg_match('#^https?://#i',$v)||($attr==='href'&&preg_match('#^mailto:#i',$v));
                    if(!$ok) $child->removeAttribute($attr);
                }
                if($tag==='a' && $child->getAttribute('target')==='_blank') $child->setAttribute('rel','noopener noreferrer');
                if($tag==='img' && !$child->hasAttribute('loading')) $child->setAttribute('loading','lazy');
                $walk($child);
            }
            $child=$next;
        }
    };
    $walk($wrap);
    $out=''; foreach(iterator_to_array($wrap->childNodes) as $child) $out.=$dom->saveHTML($child);
    return $out;
}

/** Rich but constrained sanitizer used only by the visual page editor. */
function sanitize_page_html(string $html): string {
    if (!class_exists('DOMDocument')) return sanitize_html($html);
    $allowedTags=['p','br','div','span','h1','h2','h3','h4','h5','h6','strong','b','em','i','u','s','strike','ul','ol','li','blockquote','a','img','figure','figcaption','hr','code','pre'];
    $allowedAttrs=[
        'a'=>['href','title','target','rel','class','style'],
        'img'=>['src','alt','title','width','height','loading','class'],
        'code'=>['class'],'pre'=>['class'],
        'p'=>['style','class'],'div'=>['style','class'],'span'=>['style','class'],
        'h1'=>['style','class'],'h2'=>['style','class'],'h3'=>['style','class'],'h4'=>['style','class'],'h5'=>['style','class'],'h6'=>['style','class'],
        'blockquote'=>['style','class'],'figure'=>['class'],'figcaption'=>['style','class']
    ];
    $safeStyle=function(string $style): string {
        $out=[];
        foreach(explode(';',$style) as $decl){
            if(!str_contains($decl,':')) continue;
            [$prop,$val]=array_map('trim',explode(':',$decl,2)); $prop=strtolower($prop); $val=trim($val);
            if(in_array($prop,['color','background-color'],true) && preg_match('/^(#[0-9a-f]{3,8}|rgba?\([0-9.,%\s]+\)|transparent)$/i',$val)) $out[]=$prop.':'.$val;
            elseif($prop==='font-size' && preg_match('/^(1[0-9]|[2-6][0-9]|72)px$/',$val)) $out[]='font-size:'.$val;
            elseif($prop==='text-align' && in_array(strtolower($val),['left','right','center','justify'],true)) $out[]='text-align:'.strtolower($val);
            elseif($prop==='line-height' && preg_match('/^(1(?:\.\d+)?|2(?:\.\d+)?)$/',$val)) $out[]='line-height:'.$val;
            elseif($prop==='letter-spacing' && preg_match('/^-?[0-3](?:\.\d+)?px$/',$val)) $out[]='letter-spacing:'.$val;
        }
        return implode(';',$out);
    };
    $dom=new DOMDocument('1.0','UTF-8'); $previous=libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?><div id="sar-page-wrap">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);
    libxml_clear_errors(); libxml_use_internal_errors($previous); $wrap=$dom->getElementById('sar-page-wrap'); if(!$wrap)return '';
    $walk=function($node) use (&$walk,$allowedTags,$allowedAttrs,$safeStyle){
        for($child=$node->firstChild;$child;){$next=$child->nextSibling;
            if($child instanceof DOMElement){$tag=strtolower($child->tagName);
                if(!in_array($tag,$allowedTags,true)){if(in_array($tag,['script','style','iframe','object','embed','form','svg','math'],true)){$node->removeChild($child);$child=$next;continue;}$walk($child);while($child->firstChild)$node->insertBefore($child->firstChild,$child);$node->removeChild($child);$child=$next;continue;}
                $keep=$allowedAttrs[$tag]??[]; $remove=[]; foreach($child->attributes as $attr)if(!in_array(strtolower($attr->name),$keep,true))$remove[]=$attr->name; foreach($remove as $name)$child->removeAttribute($name);
                foreach(['href','src'] as $attr){if(!$child->hasAttribute($attr))continue;$v=trim($child->getAttribute($attr));$ok=str_starts_with($v,'/')||str_starts_with($v,'#')||preg_match('#^https?://#i',$v)||($attr==='href'&&preg_match('#^mailto:#i',$v));if(!$ok)$child->removeAttribute($attr);}
                if($child->hasAttribute('style')){$st=$safeStyle($child->getAttribute('style'));if($st==='')$child->removeAttribute('style');else$child->setAttribute('style',$st);}
                if($child->hasAttribute('class')){$classes=preg_split('/\s+/',trim($child->getAttribute('class')))?:[];$allowedClasses=['btn','btn-primary','btn-ghost','text-small','text-lead','align-center','align-right','align-left'];$classes=array_values(array_intersect($classes,$allowedClasses));if($classes)$child->setAttribute('class',implode(' ',$classes));else$child->removeAttribute('class');}
                if($tag==='a'&&$child->getAttribute('target')==='_blank')$child->setAttribute('rel','noopener noreferrer');
                if($tag==='img'&&!$child->hasAttribute('loading'))$child->setAttribute('loading','lazy'); $walk($child);
            }$child=$next;
        }
    }; $walk($wrap); $out=''; foreach(iterator_to_array($wrap->childNodes) as $child)$out.=$dom->saveHTML($child); return $out;
}



function reader_heat(int $views, ?string $locale=null): array {
    $locale=$locale?:default_locale();
    $defaults=[
      'ar'=>['cold'=>'بارد','warm'=>'دافئ','hot'=>'حار','blazing'=>'مشتعل'],
      'en'=>['cold'=>'Cold','warm'=>'Warm','hot'=>'Hot','blazing'=>'Blazing'],
      'es'=>['cold'=>'Frío','warm'=>'Templado','hot'=>'Caliente','blazing'=>'Ardiendo'],
      'fr'=>['cold'=>'Froid','warm'=>'Tiède','hot'=>'Chaud','blazing'=>'Brûlant'],
    ];
    $d=$defaults[$locale]??$defaults['en'];
    if ($views < 100) return ['label'=>translation_text('article','heat_cold',$locale,$d['cold']),'icon'=>'❄️','class'=>'cold'];
    if ($views < 500) return ['label'=>translation_text('article','heat_warm',$locale,$d['warm']),'icon'=>'🌤️','class'=>'warm'];
    if ($views < 2000) return ['label'=>translation_text('article','heat_hot',$locale,$d['hot']),'icon'=>'🔥','class'=>'hot'];
    return ['label'=>translation_text('article','heat_blazing',$locale,$d['blazing']),'icon'=>'🔥🔥','class'=>'blazing'];
}

function reading_time(string $html): int {
    $text = trim(strip_tags($html));
    $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return max(1, (int)ceil(count($words) / 220));
}

function render_podcast_player(array $episode, string $locale='ar'): string {
    $audio=trim((string)($episode['audio_url']??''));
    if($audio==='') return '';
    $id='podcast-player-'.(int)($episode['id']??random_int(1000,9999));
    $title=(string)($episode['title']??'');
    $num=!empty($episode['episode_number']) ? '#'.(int)$episode['episode_number'] : '';
    $duration=(string)($episode['duration']??'');
    $playerLabels=['ar'=>['brand'=>'بودكاست سراديب','toggle'=>'تشغيل أو إيقاف الحلقة','progress'=>'شريط تقدم الحلقة','volume'=>'الصوت'],'en'=>['brand'=>'Saradeeeb podcast','toggle'=>'Play or pause episode','progress'=>'Episode progress','volume'=>'Volume'],'es'=>['brand'=>'Podcast Saradeeeb','toggle'=>'Reproducir o pausar episodio','progress'=>'Progreso del episodio','volume'=>'Volumen'],'fr'=>['brand'=>'Podcast Saradeeeb','toggle'=>'Lire ou mettre en pause','progress'=>'Progression de l’épisode','volume'=>'Volume'],'ru'=>['brand'=>'Подкаст Saradeeeb','toggle'=>'Воспроизвести или поставить на паузу','progress'=>'Прогресс выпуска','volume'=>'Громкость']][$locale]??['brand'=>'Saradeeeb podcast','toggle'=>'Play or pause episode','progress'=>'Episode progress','volume'=>'Volume'];
    ob_start(); ?>
    <section class="sar-podcast-player" data-podcast-player id="<?=e($id)?>">
      <div class="sar-podcast-player-head">
        <span class="sar-podcast-icon"><?=navigation_icon_svg('podcast')?></span>
        <div><span class="sar-podcast-label"><?=e($playerLabels['brand'])?></span><h3><?=e($title)?></h3><small><?php if($num):?><b><?=e($num)?></b><?php endif;?><?php if($duration):?><span><?=e($duration)?></span><?php endif;?></small></div>
      </div>
      <audio preload="metadata" src="<?=e($audio)?>"></audio>
      <div class="sar-podcast-controls">
        <button class="sar-podcast-play" type="button" data-podcast-toggle aria-label="<?=e($playerLabels['toggle'])?>"><?=navigation_icon_svg('play')?></button>
        <span class="sar-podcast-time" data-podcast-current>00:00</span>
        <input class="sar-podcast-range" data-podcast-seek type="range" min="0" max="100" value="0" step="0.1" aria-label="<?=e($playerLabels['progress'])?>">
        <span class="sar-podcast-time" data-podcast-duration><?=e($duration ?: '00:00')?></span>
        <input class="sar-podcast-volume" data-podcast-volume type="range" min="0" max="1" step="0.05" value="1" aria-label="<?=e($playerLabels['volume'])?>">
      </div>
    </section>
    <?php return (string)ob_get_clean();
}

function podcast_source_type(string $url): string {
    $url=trim($url); $host=strtolower((string)parse_url($url,PHP_URL_HOST)); $host=preg_replace('/^www\./','',$host); $path=strtolower((string)parse_url($url,PHP_URL_PATH));
    if(in_array($host,['youtu.be','youtube.com','m.youtube.com','music.youtube.com','youtube-nocookie.com'],true)) return 'youtube';
    if(preg_match('/\.(mp3|m4a|ogg|wav)(?:$|\?)/i',$url)) return 'direct';
    if(preg_match('/(?:rss|feed|podcast)|\.(xml|rss)(?:$|\?)/i',$url)) return 'rss';
    return 'cdn';
}

function podcast_youtube_id(string $url): string {
    $host=strtolower((string)parse_url($url,PHP_URL_HOST)); $host=preg_replace('/^www\./','',$host); $path=trim((string)parse_url($url,PHP_URL_PATH),'/'); $segments=array_values(array_filter(explode('/',$path),static fn($part)=>$part!==''));
    if($host==='youtu.be') $id=$segments[0]??'';
    elseif(in_array($host,['youtube.com','m.youtube.com','music.youtube.com','youtube-nocookie.com'],true)&&in_array(strtolower($segments[0]??''),['shorts','embed','live'],true)) $id=$segments[1]??'';
    else { parse_str((string)parse_url($url,PHP_URL_QUERY),$q); $id=(string)($q['v']??''); }
    $id=trim(explode('?',explode('&',$id)[0])[0]);
    return preg_match('/^[A-Za-z0-9_-]{6,20}$/',$id)?$id:'';
}

function render_podcast_media(array $episode,string $locale='ar'): string {
    $mediaLabels=[
        'ar'=>['brand'=>'بودكاست سراديب','rss'=>'مصدر Podcast / RSS','rss_text'=>'هذه الحلقة مرتبطة بخدمة بودكاست خارجية.','open'=>'فتح المصدر الرسمي','failed'=>'تعذر تشغيل الحلقة داخل الصفحة','invalid'=>'رابط YouTube غير صالح أو لا يحتوي على رقم فيديو معروف.'],
        'en'=>['brand'=>'Saradeeeb podcast','rss'=>'Podcast / RSS source','rss_text'=>'This episode is linked to an external podcast service.','open'=>'Open official source','failed'=>'The episode cannot be played here','invalid'=>'The YouTube URL is invalid or has no recognizable video ID.'],
        'es'=>['brand'=>'Podcast Saradeeeb','rss'=>'Fuente Podcast / RSS','rss_text'=>'Este episodio esta vinculado a un servicio externo.','open'=>'Abrir fuente oficial','failed'=>'No se puede reproducir el episodio aqui','invalid'=>'El enlace de YouTube no es valido.'],
        'fr'=>['brand'=>'Podcast Saradeeeb','rss'=>'Source Podcast / RSS','rss_text'=>'Cet episode est lie a un service externe.','open'=>'Ouvrir la source officielle','failed'=>'Impossible de lire cet episode ici','invalid'=>'Le lien YouTube est invalide.'],
        'ru'=>['brand'=>'Подкаст Saradeeeb','rss'=>'Источник Podcast / RSS','rss_text'=>'Выпуск связан с внешним сервисом подкастов.','open'=>'Открыть официальный источник','failed'=>'Не удалось воспроизвести выпуск','invalid'=>'Ссылка YouTube недействительна.'],
    ][$locale]??['brand'=>'Saradeeeb podcast','rss'=>'Podcast / RSS source','rss_text'=>'This episode is linked to an external podcast service.','open'=>'Open official source','failed'=>'The episode cannot be played here','invalid'=>'The YouTube URL is invalid.'];
    $audioUrl=trim((string)($episode['audio_url']??'')); $storedType=(string)($episode['source_type']??''); $detectedType=podcast_source_type($audioUrl);
    // A valid YouTube URL wins over stale source_type values saved by older versions.
    $type=$detectedType==='youtube'?'youtube':(($storedType===''||$storedType==='auto')?$detectedType:$storedType);
    if($type==='rss') return '<section class="sar-podcast-rss"><span>'.navigation_icon_svg('podcast').'</span><div><b>'.e($mediaLabels['rss']).'</b><p>'.e($mediaLabels['rss_text']).'</p><a target="_blank" rel="noopener noreferrer" href="'.e((string)$episode['audio_url']).'">'.e($mediaLabels['open']).'</a></div></section>';
    if($type!=='youtube') return render_podcast_player($episode,$locale);
    $id=podcast_youtube_id($audioUrl);
    if($id==='') return '<section class="sar-podcast-fallback"><b>'.e($mediaLabels['failed']).'</b><p>'.e($mediaLabels['invalid']).'</p></section>';
    $embed='https://www.youtube-nocookie.com/embed/'.rawurlencode($id).'?rel=0&modestbranding=1&playsinline=1';
    ob_start(); ?><section class="sar-podcast-youtube"><header><span><?=e($mediaLabels['brand'])?></span><h3><?=e($episode['title']??'')?></h3><?php if(!empty($episode['episode_number'])):?><small>#<?=(int)$episode['episode_number']?></small><?php endif;?></header><div class="sar-podcast-video"><iframe src="<?=e($embed)?>" title="<?=e($mediaLabels['brand'].' - '.($episode['title']??''))?>" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div></section><?php return (string)ob_get_clean();
}

function podcast_public_url(array $episode,string $locale='ar'): string {
    $slug=trim((string)($episode['slug']??''));
    $status=(string)($episode['status']??'published');
    if($slug==='' || ($status!=='' && $status!=='published')) return '';
    return locale_url($locale,'podcast/'.$slug);
}

function podcast_qr_src(array $episode,string $locale='ar'): string {
    $target=podcast_public_url($episode,$locale);
    if($target==='') return '';
    // The QR data is always the Saradeeeb episode page, never the media source.
    return 'https://api.qrserver.com/v1/create-qr-code/?size=360x360&ecc=H&margin=12&data='.rawurlencode($target);
}

function render_podcast_cover(array $episode,string $category='',string $locale='ar'): string {
    // Podcast artwork has one official identity; legacy template values are ignored.
    $template='cinematic';
    $coverImage=(string)($episode['cover_image']??''); $duration=trim((string)($episode['duration']??'')); $qr=podcast_qr_src($episode,$locale);
    $coverLabels=['ar'=>['brand'=>'بودكاست سراديب','episode'=>'الحلقة','new'=>'حلقة جديدة','qr'=>'رمز QR لفتح صفحة الحلقة','scan'=>'امسح للاستماع','pending'=>'سيتم إنشاء QR بعد نشر الحلقة'],'en'=>['brand'=>'Saradeeeb podcast','episode'=>'Episode','new'=>'New episode','qr'=>'QR code to open the episode page','scan'=>'Scan to listen','pending'=>'The QR code will be created after publishing'],'es'=>['brand'=>'Podcast Saradeeeb','episode'=>'Episodio','new'=>'Nuevo episodio','qr'=>'Código QR para abrir el episodio','scan'=>'Escanea para escuchar','pending'=>'El código QR se creará tras publicar'],'fr'=>['brand'=>'Podcast Saradeeeb','episode'=>'Épisode','new'=>'Nouvel épisode','qr'=>'Code QR pour ouvrir l’épisode','scan'=>'Scanner pour écouter','pending'=>'Le code QR sera créé après publication'],'ru'=>['brand'=>'Подкаст Saradeeeb','episode'=>'Выпуск','new'=>'Новый выпуск','qr'=>'QR-код для страницы выпуска','scan'=>'Сканировать для прослушивания','pending'=>'QR-код появится после публикации']][$locale]??['brand'=>'Saradeeeb podcast','episode'=>'Episode','new'=>'New episode','qr'=>'QR code to open the episode page','scan'=>'Scan to listen','pending'=>'The QR code will be created after publishing'];
    $episodeLabel=!empty($episode['episode_number'])?$coverLabels['episode'].' '.str_pad((string)(int)$episode['episode_number'],2,'0',STR_PAD_LEFT):$coverLabels['new'];
    $brandMark=setting('brand_mark_text','س')?:'س'; if($locale!=='ar'&&preg_match('/[\x{0600}-\x{06FF}]/u',$brandMark))$brandMark='S';
    ob_start(); ?><div class="sar-podcast-generated-cover cover-<?=e($template)?>" data-podcast-cover<?= $coverImage!==''?' style="--podcast-cover-image:url(\''.e($coverImage).'\')"':'' ?>><div class="podcast-cover-decor" aria-hidden="true"></div><header class="podcast-cover-header"><div class="podcast-cover-brand"><span><?=e($brandMark)?></span><b><?=e($coverLabels['brand'])?></b></div></header><main class="podcast-cover-main"><small class="podcast-cover-main-number"><?=e($episodeLabel)?></small><h3 data-cover-title><?=e($episode['title']??'Episode title')?></h3><?php if(!empty($episode['excerpt'])):?><p><?=e($episode['excerpt'])?></p><?php endif;?></main><footer class="podcast-cover-footer"><div class="podcast-cover-footer-meta"><?php if($category):?><em><?=e($category)?></em><?php endif;?><?php if($duration):?><span><?=e($duration)?></span><?php endif;?></div><?php if($qr):?><div class="podcast-cover-qr"><img src="<?=e($qr)?>" alt="<?=e($coverLabels['qr'])?>" crossorigin="anonymous" loading="eager" draggable="false"><span><?=e($coverLabels['scan'])?><small>saradeeeb.com</small></span></div><?php else:?><span class="podcast-cover-qr-pending"><?=e($coverLabels['pending'])?></span><?php endif;?><div class="podcast-cover-audio"><span><?=navigation_icon_svg('podcast')?></span><b><?=e($coverLabels['brand'])?></b></div></footer></div><?php return (string)ob_get_clean();
}


function translation_text(string $namespace,string $key,string $locale,string $fallback=''): string {
    static $cache=[];
    $cacheKey=$namespace.'|'.$key.'|'.$locale;
    if(array_key_exists($cacheKey,$cache)) return $cache[$cacheKey];
    try {
        $row=Database::one('SELECT value FROM translation_strings WHERE namespace=? AND text_key=? AND locale=? LIMIT 1',[$namespace,$key,$locale]);
        if($row && array_key_exists('value',$row) && trim((string)$row['value'])!=='') return $cache[$cacheKey]=(string)$row['value'];
    } catch(Throwable) {}
    return $cache[$cacheKey]=$fallback;
}

/** Arabic is the source language; non-Arabic UI must opt into Arabic fallback. */
function translation_fallback_enabled(string $locale): bool {
    if ($locale === default_locale()) return true;
    try {
        $specific = setting('translation_fallback_'.$locale, '');
        if ($specific !== '') return $specific === '1';
        return setting('translation_fallback_enabled', '0') === '1';
    } catch (Throwable) { return false; }
}

function translation_field_definitions(): array {
    $defs=[];
    $add=function(string $group,string $namespace,array $fields) use (&$defs): void {
        foreach($fields as $key=>$meta){
            if(is_string($meta)) $meta=['label'=>$meta,'fallback'=>$meta];
            $fallback=(string)($meta['fallback']??'');
            $defs[]=['group'=>$group,'namespace'=>$namespace,'key'=>$key,'label'=>(string)($meta['label']??$key),'fallback'=>$fallback,'type'=>(string)($meta['type']??(mb_strlen($fallback)>75?'textarea':'text'))];
        }
    };

    $coreKeys=[
      'home'=>'الرئيسية','explore'=>'استكشف','write'=>'اكتب في سراديب','dashboard'=>'لوحتي','search'=>'بحث','search_ph'=>'نص حقل البحث','languages'=>'اللغات','writers'=>'الكتابة معنا','latest'=>'الأحدث','latest_title'=>'عنوان أحدث المنشورات','view_all'=>'مشاهدة الكل','platform'=>'وصف المنصة','hero_title'=>'عنوان الواجهة الرئيسي','explore_cta'=>'زر الاستكشاف','write_story'=>'زر كتابة القصة','featured'=>'قصة مختارة','read_now'=>'اقرأ الآن','choose_path'=>'اختر ممرك','sections'=>'أقسام سراديب','stories'=>'قصة ومقال','have_story'=>'لديك قصة؟','open_sardeeb'=>'افتح سردابك الخاص','join_desc'=>'وصف الانضمام للكتّاب','join_writers'=>'انضم إلى الكتّاب','copyright'=>'حقوق النشر','read_in'=>'اقرأ باللغة','minutes'=>'دقائق قراءة','views'=>'مشاهدة','author'=>'الكاتب','all_articles'=>'جميع مقالات الكاتب','similar'=>'مقالات مشابهة','toc'=>'فهرس المقال','results'=>'نتائج البحث','results_for'=>'نتائج البحث عن','search_intro'=>'وصف البحث','no_results'=>'لا توجد نتائج','try_again'=>'جرّب مرة أخرى','welcome_back'=>'مرحبًا بعودتك','login'=>'تسجيل الدخول','login_desc'=>'وصف تسجيل الدخول','email'=>'البريد الإلكتروني','password'=>'كلمة المرور','enter'=>'دخول','no_account'=>'ليس لديك حساب؟','join_writer'=>'انضم ككاتب','writers_label'=>'الكتّاب','join_title'=>'عنوان الانضمام للكتّاب','join_sub'=>'وصف الانضمام للكتّاب','name'=>'الاسم','username'=>'اسم المستخدم','bio'=>'نبذة عنك','send_request'=>'إرسال الطلب','password_hint'=>'مساعدة كلمة المرور','story'=>'أرسل قصتك','community'=>'منتدى سراديب','podcast'=>'بودكاست سراديب','khawatir'=>'خواطر'
    ];
    foreach($coreKeys as $key=>$label) $defs[]=['group'=>'الواجهة العامة','namespace'=>'core','key'=>$key,'label'=>$label,'fallback'=>tr($key,'ar'),'type'=>'text'];

    $add('الهيدر: عناصر التنقل','nav',[
      'story'=>['label'=>'رابط إرسال القصة','fallback'=>'أرسل قصتك'],
      'community'=>['label'=>'رابط منتدى سراديب','fallback'=>'منتدى سراديب'],
      'podcast'=>['label'=>'رابط البودكاست','fallback'=>'بودكاست سراديب'],
      'khawatir'=>['label'=>'رابط الخواطر','fallback'=>'خواطر'],
    ]);

    $add('الصفحة الرئيسية','home',[
      'top'=>['label'=>'عنوان المقالات المهمة','fallback'=>'أهم المقالات'],
      'top_sub'=>['label'=>'وصف المقالات المهمة','fallback'=>'مختارات من سراديب'],
      'latest'=>['label'=>'عنوان أحدث المقالات','fallback'=>'أحدث المقالات'],
      'latest_sub'=>['label'=>'وصف أحدث المقالات','fallback'=>'جديد سراديب'],
      'categories'=>['label'=>'عنوان التصنيفات','fallback'=>'تصفح حسب الفئة'],
      'categories_sub'=>['label'=>'وصف التصنيفات','fallback'=>'اختر سِردابك'],
      'discover'=>['label'=>'عنوان الاستكشاف','fallback'=>'واصل الاستكشاف'],
      'discover_sub'=>['label'=>'وصف الاستكشاف','fallback'=>'من عمق سراديب'],
      'random'=>['label'=>'عنوان البطاقة العشوائية','fallback'=>'قصة مقترحة لك'],
      'read'=>['label'=>'زر قراءة القصة','fallback'=>'اقرأ القصة'],
      'write_title'=>['label'=>'عنوان دعوة إرسال قصة','fallback'=>'لديك قصة لا تُنسى؟'],
      'write_text'=>['label'=>'وصف دعوة إرسال قصة','fallback'=>'اكتب قصتك وأرسلها إلى سراديب. بعد المراجعة يمكن أن تصبح جزءًا من أرشيف الحكايات.','type'=>'textarea'],
      'write_cta'=>['label'=>'زر إرسال القصة','fallback'=>'أرسل قصتك'],
      'popular'=>['label'=>'الأكثر قراءة','fallback'=>'الأكثر قراءة'],
      'close'=>['label'=>'إغلاق البطاقة العشوائية','fallback'=>'إغلاق'],
    ]);

    $add('الهيدر والفوتر والرسائل','layout',[
      'skip'=>['label'=>'تجاوز إلى المحتوى','fallback'=>'تجاوز إلى المحتوى'],'social'=>['label'=>'الشبكات الاجتماعية','fallback'=>'الشبكات الاجتماعية'],'theme'=>['label'=>'تبديل المظهر','fallback'=>'تبديل المظهر'],'menu'=>['label'=>'القائمة','fallback'=>'القائمة'],'close'=>['label'=>'إغلاق','fallback'=>'إغلاق'],'subscribe'=>['label'=>'زر الاشتراك','fallback'=>'اشتراك'],'consent_news'=>['label'=>'موافقة النشرة','fallback'=>'أوافق على استلام جديد القصص ويمكنني إلغاء الاشتراك لاحقًا.'],'social_links'=>['label'=>'روابط التواصل','fallback'=>'روابط التواصل'],'privacy'=>['label'=>'إعدادات الخصوصية','fallback'=>'إعدادات الخصوصية'],'top'=>['label'=>'العودة للأعلى','fallback'=>'العودة للأعلى'],'notice'=>['label'=>'إشعار سراديب','fallback'=>'إشعار من سراديب'],'more'=>['label'=>'معرفة المزيد','fallback'=>'معرفة المزيد'],'adblock_title'=>['label'=>'عنوان مانع الإعلانات','fallback'=>'يبدو أن مانع الإعلانات مفعّل.'],'adblock_strict'=>['label'=>'رسالة مانع الإعلانات الإلزامية','fallback'=>'عطّل مانع الإعلانات لهذا الموقع ثم أعد تحميل الصفحة لمتابعة القراءة.','type'=>'textarea'],'adblock_soft'=>['label'=>'رسالة مانع الإعلانات اللطيفة','fallback'=>'الإعلانات تساعد في دعم سراديب. نقدّر إضافة الموقع إلى الاستثناءات.','type'=>'textarea'],'hide'=>['label'=>'إخفاء','fallback'=>'إخفاء'],'necessary'=>['label'=>'ضروري','fallback'=>'ضروري'],'analytics'=>['label'=>'تحليلات','fallback'=>'تحليلات'],'marketing'=>['label'=>'تسويق','fallback'=>'تسويق'],'ads'=>['label'=>'إعلانات','fallback'=>'إعلانات'],'accept'=>['label'=>'قبول الكل','fallback'=>'قبول الكل'],'save'=>['label'=>'حفظ الاختيارات','fallback'=>'حفظ اختياري'],'reject'=>['label'=>'رفض غير الضروري','fallback'=>'رفض غير الضروري'],'privacy_policy'=>['label'=>'سياسة الخصوصية','fallback'=>'سياسة الخصوصية']
    ]);

    $add('الفوتر','footer',[
      'title'=>['label'=>'اسم/عنوان الفوتر','fallback'=>'سراديب'],'description'=>['label'=>'وصف الفوتر','fallback'=>'منصة القصص والمعرفة الغامضة','type'=>'textarea'],'menu'=>['label'=>'عنوان قائمة الاستكشاف','fallback'=>'استكشف'],'languages'=>['label'=>'عنوان اللغات','fallback'=>'اللغات'],'categories'=>['label'=>'عنوان الأقسام','fallback'=>'الأقسام'],'cta_title'=>['label'=>'عنوان الدعوة','fallback'=>'ابقَ قريبًا من الغموض'],'cta_text'=>['label'=>'نص الدعوة','fallback'=>'تابع أحدث القصص والملفات التي تنشرها سراديب.','type'=>'textarea'],'cta_label'=>['label'=>'زر الدعوة','fallback'=>'تواصل معنا'],'story_cta'=>['label'=>'زر إرسال القصة','fallback'=>'أرسل قصتك'],'writer_cta'=>['label'=>'زر طلب عضوية كاتب','fallback'=>'قدّم طلب عضوية كاتب'],'newsletter_title'=>['label'=>'عنوان النشرة البريدية','fallback'=>'القصص القادمة إلى بريدك'],'newsletter_text'=>['label'=>'وصف النشرة البريدية','fallback'=>'اشترك ليصلك جديد سراديب دون إزعاج.','type'=>'textarea'],'designer_prefix'=>['label'=>'النص قبل اسم المصمم','fallback'=>'تم تصميم هذا الموقع بواسطة'],'copyright'=>['label'=>'حقوق النشر','fallback'=>'جميع الحقوق محفوظة.']
    ]);

    $add('صفحة المقال','article',[
      'community_label'=>['label'=>'عنوان المنتدى','fallback'=>'منتدى سراديب'],'community_title'=>['label'=>'دعوة نقاش المقال','fallback'=>'لديك رأي أو سؤال حول هذه المقالة؟'],'community_text'=>['label'=>'وصف دعوة النقاش','fallback'=>'انقل الحوار إلى المنتدى وشارك القرّاء والكتّاب أفكارك بدون نظام تعليقات متداخل.','type'=>'textarea'],'community_cta'=>['label'=>'زر نقاش المقال','fallback'=>'ناقش هذا المقال في منتدى سراديب'],'share_label'=>['label'=>'عنوان مشاركة المقال','fallback'=>'شارك المقال'],'share_kicker'=>['label'=>'كلمة المشاركة','fallback'=>'مشاركة'],'copy_link'=>['label'=>'نسخ الرابط','fallback'=>'نسخ الرابط'],'hashtags_label'=>['label'=>'وصف الهشتاغات','fallback'=>'هشتاغات المقال'],'writer_bio'=>['label'=>'نبذة افتراضية للكاتب','fallback'=>'كاتب في سراديب.'],'thanks_badge'=>['label'=>'بطاقة الشكر','fallback'=>'بطاقة شكر'],'thanks_prefix'=>['label'=>'بادئة الشكر','fallback'=>'شكرًا'],'summary_kicker'=>['label'=>'عنوان الخلاصة الصغير','fallback'=>'الخلاصة'],'summary_title'=>['label'=>'عنوان الملخص','fallback'=>'ملخص سريع'],'toc_kicker'=>['label'=>'عنوان التنقل الصغير','fallback'=>'التنقل'],'reading_kicker'=>['label'=>'عنوان القراءة الصغير','fallback'=>'القراءة'],'heat_cold'=>['label'=>'تفاعل القراءة: بارد','fallback'=>'بارد'],'heat_warm'=>['label'=>'تفاعل القراءة: دافئ','fallback'=>'دافئ'],'heat_hot'=>['label'=>'تفاعل القراءة: حار','fallback'=>'حار'],'heat_blazing'=>['label'=>'تفاعل القراءة: مشتعل','fallback'=>'مشتعل']
    ]);

    $add('صفحة تواصل معنا','contact',[
      'title'=>['label'=>'عنوان الصفحة','fallback'=>'تواصل معنا'],'desc'=>['label'=>'وصف الصفحة','fallback'=>'أرسل رسالتك إلى فريق سراديب.','type'=>'textarea'],'name'=>['label'=>'حقل الاسم','fallback'=>'الاسم'],'email'=>['label'=>'حقل البريد','fallback'=>'البريد الإلكتروني'],'subject'=>['label'=>'حقل الموضوع','fallback'=>'الموضوع'],'message'=>['label'=>'حقل الرسالة','fallback'=>'الرسالة'],'send'=>['label'=>'زر الإرسال','fallback'=>'إرسال الرسالة'],'privacy'=>['label'=>'ملاحظة الخصوصية','fallback'=>'سيتم حفظ الرسالة لدى الإدارة للمتابعة.','type'=>'textarea'],'invalid'=>['label'=>'رسالة خطأ البيانات','fallback'=>'تحقق من الاسم والبريد واكتب رسالة واضحة.'],'wait'=>['label'=>'رسالة الانتظار','fallback'=>'انتظر قليلًا قبل إرسال رسالة أخرى.'],'ok'=>['label'=>'رسالة نجاح الإرسال','fallback'=>'تم إرسال رسالتك بنجاح.']
    ]);

    $add('صفحة أرسل قصتك','send_story',[
      'title'=>['label'=>'عنوان الصفحة','fallback'=>'أرسل قصتك'],'desc'=>['label'=>'وصف الصفحة','fallback'=>'لديك تجربة أو قصة غامضة؟ أرسلها إلى فريق سراديب للمراجعة.','type'=>'textarea'],'name'=>['label'=>'حقل الاسم','fallback'=>'الاسم'],'email'=>['label'=>'حقل البريد','fallback'=>'البريد الإلكتروني'],'phone'=>['label'=>'حقل الهاتف','fallback'=>'الهاتف — اختياري'],'story_title'=>['label'=>'حقل عنوان القصة','fallback'=>'عنوان القصة'],'story'=>['label'=>'حقل نص القصة','fallback'=>'نص القصة'],'image'=>['label'=>'حقل الصورة','fallback'=>'صورة اختيارية'],'rights'=>['label'=>'نص الموافقة على الحقوق','fallback'=>'أؤكد أن لدي الحق في إرسال هذا المحتوى للنشر أو المراجعة.','type'=>'textarea'],'send'=>['label'=>'زر إرسال القصة','fallback'=>'إرسال القصة'],'invalid'=>['label'=>'رسالة خطأ البيانات','fallback'=>'أكمل الاسم والبريد والعنوان، واجعل نص القصة 120 حرفًا على الأقل.'],'wait'=>['label'=>'رسالة الانتظار','fallback'=>'انتظر دقيقة قبل إرسال قصة أخرى.'],'ok'=>['label'=>'رسالة نجاح الإرسال','fallback'=>'وصلت قصتك إلى سراديب. سنراجعها من لوحة الإدارة.']
    ]);

    $add('النشرة البريدية','newsletter',[
      'invalid'=>['label'=>'رسالة البريد غير الصحيح','fallback'=>'أدخل بريدًا صحيحًا ووافق على الاشتراك.'],'ok'=>['label'=>'رسالة نجاح الاشتراك','fallback'=>'تم اشتراك بريدك بنجاح.'],'fail'=>['label'=>'رسالة تعذر الاشتراك','fallback'=>'تعذر حفظ الاشتراك الآن. حاول مجددًا.']
    ]);

    $add('صفحة 404','404',[
      'eyebrow'=>['label'=>'السطر الصغير','fallback'=>'هذا الممر لا يقود إلى مكان'],'title'=>['label'=>'العنوان الرئيسي','fallback'=>'يبدو أنك وصلت إلى صفحة اختفت في الظلام'],'text'=>['label'=>'الوصف','fallback'=>'ربما تغيّر الرابط أو نُقلت القصة. استخدم البحث أو عُد إلى سراديب واستكشف حكاية أخرى.','type'=>'textarea'],'search'=>['label'=>'زر البحث','fallback'=>'بحث'],'placeholder'=>['label'=>'نص حقل البحث','fallback'=>'ابحث عن قصة أو موضوع...'],'home'=>['label'=>'زر الرئيسية','fallback'=>'العودة إلى الرئيسية'],'send'=>['label'=>'زر أرسل قصتك','fallback'=>'أرسل قصتك'],'discover'=>['label'=>'عنوان روابط الاستكشاف','fallback'=>'استكشف:']
    ]);
    return $defs;
}

function tr(string $key, ?string $locale = null): string {
    $locale = $locale ?: (string)config('default_locale', 'ar');
    $dict = [
        'ar' => [
            'home'=>'الرئيسية','explore'=>'استكشف','write'=>'اكتب في سراديب','dashboard'=>'لوحتي','search'=>'بحث','search_ph'=>'ابحث...',
            'languages'=>'اللغات','writers'=>'الكتابة معنا','latest'=>'الأحدث','latest_title'=>'أحدث ما نُشر في سراديب','view_all'=>'مشاهدة الكل',
            'platform'=>'منصة القصص والمعرفة الغامضة','hero_title'=>'ادخل إلى سراديب الحكايات التي لا تنتهي عند ظاهرها.','explore_cta'=>'استكشف السراديب','write_story'=>'اكتب قصتك',
            'featured'=>'قصة مختارة','read_now'=>'اقرأ الآن','choose_path'=>'اختر ممرك','sections'=>'أقسام سراديب','stories'=>'قصة ومقال','have_story'=>'لديك قصة؟','open_sardeeb'=>'افتح سردابك الخاص.',
            'join_desc'=>'أنشئ حساب كاتب، اكتب قصتك، وأرسلها إلى فريق التحرير للمراجعة والنشر.','join_writers'=>'انضم إلى الكتّاب','copyright'=>'جميع الحقوق محفوظة.',
            'read_in'=>'اقرأ باللغة:','minutes'=>'دقائق قراءة','views'=>'مشاهدة','author'=>'الكاتب','all_articles'=>'جميع مقالاته','similar'=>'سراديب مشابهة','toc'=>'في هذا المقال','uncategorized'=>'بدون تصنيف',
            'results'=>'نتائج البحث','results_for'=>'نتائج البحث عن:','search_intro'=>'اكتب كلمة للبحث في سراديب.','no_results'=>'لا توجد نتائج بعد','try_again'=>'جرّب كلمة أخرى أو عد لاحقًا.',
            'welcome_back'=>'مرحبًا بعودتك','login'=>'تسجيل الدخول','login_desc'=>'ادخل إلى لوحة الكاتب أو الإدارة.','email'=>'البريد الإلكتروني','password'=>'كلمة المرور','enter'=>'دخول','no_account'=>'ليس لديك حساب؟','join_writer'=>'انضم ككاتب',
            'writers_label'=>'الكتّاب','join_title'=>'انضم إلى كتّاب سراديب','join_sub'=>'أنشئ حسابًا وأرسل قصصك إلى فريق التحرير.','name'=>'الاسم','username'=>'اسم المستخدم','bio'=>'نبذة عنك','send_request'=>'إرسال طلب الانضمام','password_hint'=>'12 محرفًا على الأقل.'
        ],
        'en' => [
            'home'=>'Home','explore'=>'Explore','write'=>'Write for Saradeeeb','dashboard'=>'Dashboard','search'=>'Search','search_ph'=>'Search...',
            'languages'=>'Languages','writers'=>'Write with us','latest'=>'Latest','latest_title'=>'Latest stories from Saradeeeb','view_all'=>'View all',
            'platform'=>'Stories from the hidden side of knowledge','hero_title'=>'Enter stories that never end at what you can see.','explore_cta'=>'Explore Saradeeeb','write_story'=>'Write your story',
            'featured'=>'Featured story','read_now'=>'Read now','choose_path'=>'Choose a passage','sections'=>'Saradeeeb sections','stories'=>'stories & articles','have_story'=>'Have a story?','open_sardeeb'=>'Open your own passage.',
            'join_desc'=>'Create a writer account, write your story, and send it to the editorial team for review.','join_writers'=>'Join the writers','copyright'=>'All rights reserved.',
            'read_in'=>'Read in:','minutes'=>'min read','views'=>'views','author'=>'Author','all_articles'=>'All articles','similar'=>'Related stories','toc'=>'In this article','uncategorized'=>'Uncategorized',
            'results'=>'Search results','results_for'=>'Results for:','search_intro'=>'Enter a word to search Saradeeeb.','no_results'=>'No results yet','try_again'=>'Try another keyword or come back later.',
            'welcome_back'=>'Welcome back','login'=>'Sign in','login_desc'=>'Access your writer or admin dashboard.','email'=>'Email','password'=>'Password','enter'=>'Sign in','no_account'=>'No account yet?','join_writer'=>'Join as a writer',
            'writers_label'=>'Writers','join_title'=>'Join Saradeeeb writers','join_sub'=>'Create an account and submit your stories to the editorial team.','name'=>'Name','username'=>'Username','bio'=>'About you','send_request'=>'Send application','password_hint'=>'At least 12 characters.'
        ],
        'es' => [
            'home'=>'Inicio','explore'=>'Explorar','write'=>'Escribe en Saradeeeb','dashboard'=>'Mi panel','search'=>'Buscar','search_ph'=>'Buscar...',
            'languages'=>'Idiomas','writers'=>'Escribe con nosotros','latest'=>'Últimos','latest_title'=>'Lo último publicado en Saradeeeb','view_all'=>'Ver todo',
            'platform'=>'Historias del lado oculto del conocimiento','hero_title'=>'Entra en historias que no terminan en lo que se ve.','explore_cta'=>'Explorar Saradeeeb','write_story'=>'Escribe tu historia',
            'featured'=>'Historia destacada','read_now'=>'Leer ahora','choose_path'=>'Elige un pasaje','sections'=>'Secciones de Saradeeeb','stories'=>'historias y artículos','have_story'=>'¿Tienes una historia?','open_sardeeb'=>'Abre tu propio pasaje.',
            'join_desc'=>'Crea una cuenta de autor, escribe tu historia y envíala al equipo editorial para revisión.','join_writers'=>'Únete a los autores','copyright'=>'Todos los derechos reservados.',
            'read_in'=>'Leer en:','minutes'=>'min de lectura','views'=>'vistas','author'=>'Autor','all_articles'=>'Todos sus artículos','similar'=>'Historias relacionadas','toc'=>'En este artículo','uncategorized'=>'Sin categoría',
            'results'=>'Resultados de búsqueda','results_for'=>'Resultados para:','search_intro'=>'Escribe una palabra para buscar en Saradeeeb.','no_results'=>'Aún no hay resultados','try_again'=>'Prueba otra palabra o vuelve más tarde.',
            'welcome_back'=>'Bienvenido de nuevo','login'=>'Iniciar sesión','login_desc'=>'Accede al panel de autor o administración.','email'=>'Correo electrónico','password'=>'Contraseña','enter'=>'Entrar','no_account'=>'¿Aún no tienes cuenta?','join_writer'=>'Únete como autor',
            'writers_label'=>'Autores','join_title'=>'Únete a los autores de Saradeeeb','join_sub'=>'Crea una cuenta y envía tus historias al equipo editorial.','name'=>'Nombre','username'=>'Usuario','bio'=>'Sobre ti','send_request'=>'Enviar solicitud','password_hint'=>'Al menos 12 caracteres.'
        ],
        'fr' => [
            'home'=>'Accueil','explore'=>'Explorer','write'=>'Écrire pour Saradeeeb','dashboard'=>'Mon tableau de bord','search'=>'Rechercher','search_ph'=>'Rechercher...',
            'languages'=>'Langues','writers'=>'Écrire avec nous','latest'=>'Nouveautés','latest_title'=>'Les dernières histoires de Saradeeeb','view_all'=>'Voir tout',
            'platform'=>'Histoires du côté caché du savoir','hero_title'=>'Entrez dans des histoires qui ne s’arrêtent pas à ce qui est visible.','explore_cta'=>'Explorer Saradeeeb','write_story'=>'Écrire votre histoire',
            'featured'=>'Histoire à la une','read_now'=>'Lire maintenant','choose_path'=>'Choisissez votre passage','sections'=>'Rubriques de Saradeeeb','stories'=>'histoires et articles','have_story'=>'Vous avez une histoire ?','open_sardeeb'=>'Ouvrez votre propre passage.',
            'join_desc'=>'Créez un compte auteur, écrivez votre histoire puis envoyez-la à la rédaction pour validation.','join_writers'=>'Rejoindre les auteurs','copyright'=>'Tous droits réservés.',
            'read_in'=>'Lire en :','minutes'=>'min de lecture','views'=>'vues','author'=>'Auteur','all_articles'=>'Tous les articles','similar'=>'Histoires similaires','toc'=>'Sans catégorie',
            'results'=>'Résultats de recherche','results_for'=>'Résultats pour :','search_intro'=>'Saisissez un mot pour rechercher dans Saradeeeb.','no_results'=>'Aucun résultat','try_again'=>'Essayez un autre mot-clé ou revenez plus tard.',
            'welcome_back'=>'Bon retour','login'=>'Se connecter','login_desc'=>'Accédez à votre espace auteur ou administrateur.','email'=>'E-mail','password'=>'Mot de passe','enter'=>'Connexion','no_account'=>'Pas encore de compte ?','join_writer'=>'Devenir auteur',
            'writers_label'=>'Auteurs','join_title'=>'Rejoignez les auteurs de Saradeeeb','join_sub'=>'Créez un compte et envoyez vos histoires à la rédaction.','name'=>'Nom','username'=>'Nom d’utilisateur','bio'=>'À propos de vous','send_request'=>'Envoyer la demande','password_hint'=>'12 caractères minimum.'
        ],
        'ru' => [
            'home'=>'Главная','explore'=>'Исследовать','write'=>'Писать для Saradeeeb','dashboard'=>'Моя панель','search'=>'Поиск','search_ph'=>'Поиск...',
            'languages'=>'Языки','writers'=>'Стать автором','latest'=>'Новое','latest_title'=>'Последние публикации Saradeeeb','view_all'=>'Смотреть все',
            'platform'=>'Истории со скрытой стороны знания','hero_title'=>'Войдите в истории, которые не заканчиваются видимой стороной.','explore_cta'=>'Исследовать Saradeeeb','write_story'=>'Написать историю',
            'featured'=>'Избранная история','read_now'=>'Читать','choose_path'=>'Выберите путь','sections'=>'Разделы Saradeeeb','stories'=>'историй и статей','have_story'=>'Есть история?','open_sardeeb'=>'Откройте свой собственный проход.',
            'join_desc'=>'Создайте аккаунт автора, напишите историю и отправьте её редакции на проверку.','join_writers'=>'Стать автором','copyright'=>'Все права защищены.',
            'read_in'=>'Читать на:','minutes'=>'мин чтения','views'=>'просмотров','author'=>'Автор','all_articles'=>'Все статьи','similar'=>'Похожие истории','toc'=>'В этой статье','uncategorized'=>'Без категории',
            'results'=>'Результаты поиска','results_for'=>'Результаты для:','search_intro'=>'Введите слово для поиска по Saradeeeb.','no_results'=>'Результатов пока нет','try_again'=>'Попробуйте другое слово или вернитесь позже.',
            'welcome_back'=>'С возвращением','login'=>'Войти','login_desc'=>'Войдите в панель автора или администратора.','email'=>'Эл. почта','password'=>'Пароль','enter'=>'Войти','no_account'=>'Нет аккаунта?','join_writer'=>'Стать автором',
            'writers_label'=>'Авторы','join_title'=>'Станьте автором Saradeeeb','join_sub'=>'Создайте аккаунт и отправляйте истории в редакцию.','name'=>'Имя','username'=>'Имя пользователя','bio'=>'О себе','send_request'=>'Отправить заявку','password_hint'=>'Не менее 12 символов.'
        ],
    ];
    $fallback=$dict[$locale][$key] ?? $dict['ar'][$key] ?? $key;
    return translation_text('core',$key,$locale,$fallback);
}

function inject_article_ads(string $html, string $locale): string {
    $placements = [3 => ad_slot('inside_article_3', $locale), 7 => ad_slot('inside_article_7', $locale)];
    if (!array_filter($placements)) return $html;
    $count = 0;
    return preg_replace_callback('/<\/p>/iu', function($m) use (&$count, $placements) {
        $count++; return $m[0] . ($placements[$count] ?? '');
    }, $html) ?? $html;
}

function setting(string $key, string $default=''): string {
    try {
        $row = Database::one('SELECT value FROM settings WHERE `key` = ?', [$key]);
        return $row ? (string)$row['value'] : $default;
    } catch (Throwable) { return $default; }
}

function typography_catalog(): array {
    return [
        'h1'=>'H1','h2'=>'H2','h3'=>'H3','h4'=>'H4','body'=>'نص المقال','card_title'=>'عناوين البطاقات','card_text'=>'وصف البطاقات','button'=>'أزرار الموقع','header'=>'الهيدر','menu'=>'القوائم','mega'=>'Mega Menu','footer'=>'الفوتر','category'=>'التصنيفات','breadcrumbs'=>'Breadcrumbs','article_meta'=>'بيانات المقال','author'=>'اسم الكاتب','author_card'=>'بطاقة الكاتب','khawatir'=>'خواطر','podcast'=>'البودكاست','form'=>'النماذج','writer'=>'لوحة الكاتب'
    ];
}
function typography_defaults(): array {
    $base=['family'=>'plex','size'=>16,'weight'=>400,'line'=>1.75,'letter'=>0,'word'=>0];
    $sets=[]; foreach(['desktop','tablet','mobile'] as $device) foreach(typography_catalog() as $key=>$label) $sets[$key][$device]=$base;
    $values=['h1'=>[[44,800,1.25],[36,700,1.3],[28,700,1.35]],'h2'=>[[34,800,1.35],[30,700,1.35],[25,700,1.4]],'h3'=>[[26,700,1.45],[23,700,1.45],[21,700,1.5]],'h4'=>[[21,700,1.5],[19,700,1.5],[18,700,1.5]],'body'=>[[18,400,2.0],[17,400,1.9],[16,400,1.85]],'card_title'=>[[20,750,1.45],[18,700,1.45],[17,700,1.5]],'card_text'=>[[14,400,1.75],[14,400,1.7],[13,400,1.7]],'button'=>[[14,800,1.2],[14,800,1.2],[13,800,1.2]],'header'=>[[13,700,1.3],[13,700,1.3],[13,700,1.3]],'menu'=>[[14,700,1.3],[14,700,1.3],[16,800,1.3]],'mega'=>[[14,700,1.45],[14,700,1.45],[14,700,1.45]],'footer'=>[[14,400,1.8],[14,400,1.8],[13,400,1.75]],'category'=>[[14,800,1.4],[14,800,1.4],[13,800,1.4]],'breadcrumbs'=>[[12,500,1.5],[12,500,1.5],[11,500,1.5]],'article_meta'=>[[12,500,1.5],[12,500,1.5],[11,500,1.5]],'author'=>[[16,800,1.4],[16,800,1.4],[15,800,1.4]],'author_card'=>[[14,500,1.7],[14,500,1.7],[13,500,1.7]],'khawatir'=>[[22,500,1.8],[20,500,1.8],[18,500,1.75]],'podcast'=>[[16,600,1.6],[15,600,1.6],[15,600,1.6]],'form'=>[[15,400,1.5],[15,400,1.5],[15,400,1.5]],'writer'=>[[15,400,1.65],[15,400,1.65],[14,400,1.65]]];
    foreach($values as $key=>$devices) foreach(['desktop','tablet','mobile'] as $i=>$device){[$sets[$key][$device]['size'],$sets[$key][$device]['weight'],$sets[$key][$device]['line']]=$devices[$i];}
    return $sets;
}
function typography_css_variables(): string {
    $defaults=typography_defaults(); $families=['plex'=>'"IBM Plex Sans Arabic","Segoe UI",Arial,sans-serif','system'=>'system-ui,"Segoe UI",Arial,sans-serif','serif'=>'Georgia,"Times New Roman",serif','mono'=>'ui-monospace,Consolas,monospace'];
    try{$rows=Database::all("SELECT `key`,value FROM settings WHERE `key` LIKE 'typography_%'");$saved=[];foreach($rows as $r)$saved[$r['key']]=$r['value'];}catch(Throwable){$saved=[];}
    $out=''; foreach($defaults as $element=>$devices) foreach($devices as $device=>$fields) foreach($fields as $field=>$default){$key="typography_{$element}_{$device}_{$field}";$value=$saved[$key]??$default;if($field==='family')$value=$families[$value]??$families['plex'];elseif($field==='size')$value=max(10,min(120,(int)$value)).'px';elseif($field==='weight')$value=in_array((int)$value,[300,400,500,600,700,800,900],true)?$value:400;elseif($field==='line')$value=max(.8,min(3,(float)$value));else $value=max(-5,min(30,(float)$value)).'px';$out.="--typo-{$element}-{$field}-{$device}:{$value};";}
    return $out;
}


function integration_head_markup(): string { return Integrations::headMarkup(); }
function integration_body_start_markup(): string { return Integrations::bodyStartMarkup(); }
function integration_body_end_markup(): string { return Integrations::bodyEndMarkup(); }
function tracking_consent_required(): bool { return Integrations::consentRequired(); }
function tracking_consent_state(): array { return Integrations::consentState(); }

function ad_slot(string $slot, string $locale): string {
    if (setting('ad_slot_enabled_'.$slot,'1') === '0') return '';
    try {
        $device = preg_match('/Mobile|Android|iPhone|iPad/i',(string)($_SERVER['HTTP_USER_AGENT']??'')) ? 'mobile' : 'desktop';
        $ad = Database::one("SELECT * FROM ads WHERE slot=? AND active=1 AND (locale=? OR locale='*') AND (starts_at IS NULL OR starts_at<=CURRENT_TIMESTAMP) AND (ends_at IS NULL OR ends_at>=CURRENT_TIMESTAMP) AND (device='all' OR device=?) ORDER BY locale DESC, id DESC LIMIT 1", [$slot, $locale, $device]);
        if (!$ad) return '';
        try { Database::exec('UPDATE ads SET views=views+1 WHERE id=?',[(int)$ad['id']]); } catch(Throwable) {}
        if ($ad['type'] === 'image') {
            $imageUrl=(string)($ad['image_url']??'');
            if (Integrations::consentRequired() && !Integrations::consentGranted('ads') && preg_match('#^https?://#i',$imageUrl)) {
                $adHost=strtolower((string)(parse_url($imageUrl,PHP_URL_HOST)??''));
                $siteHost=strtolower(preg_replace('/:\d+$/','',(string)($_SERVER['HTTP_HOST']??''))??'');
                if($adHost!=='' && $siteHost!=='' && $adHost!==$siteHost) return '';
            }
            $img = '<img src="'.e($imageUrl).'" alt="'.e($ad['label'] ?: 'إعلان').'" loading="lazy">';
            $href = !empty($ad['target_url']) ? url('/ad/click/'.(int)$ad['id']) : '';
            return '<aside class="ad-slot" aria-label="Advertisement" data-ad-id="'.(int)$ad['id'].'">' . ($href ? '<a rel="sponsored noopener" href="'.e($href).'">'.$img.'</a>' : $img) . '</aside>';
        }
        if (Integrations::consentRequired() && !Integrations::consentGranted('ads')) return '';
        return '<aside class="ad-slot ad-code" aria-label="Advertisement" data-ad-id="'.(int)$ad['id'].'">' . $ad['code'] . '</aside>';
    } catch (Throwable) { return ''; }
}


function news_ticker_items(string $locale, int $limit = 8): array {
    try {
        $limit=max(1,min(20,$limit));
        $source=setting('news_ticker_source','latest');
        $category=(int)setting('news_ticker_category','0');
        $where="t.locale=? AND ".article_translation_publishable_sql('t')." AND a.status='published'"; $args=[$locale];
        if($source==='category' && $category>0){ $where.=' AND a.category_id=?'; $args[]=$category; }
        $order=$source==='featured'?'a.is_featured DESC,a.published_at DESC':'a.published_at DESC,a.id DESC';
        return Database::all("SELECT t.title,t.slug FROM article_translations t JOIN articles a ON a.id=t.article_id WHERE {$where} ORDER BY {$order} LIMIT {$limit}",$args);
    } catch (Throwable) { return []; }
}

function site_blocks(string $slot,string $locale): array {
    try { return Database::all("SELECT b.*,a.label ad_label FROM site_blocks b LEFT JOIN ads a ON a.id=b.ad_id WHERE b.slot=? AND b.active=1 AND (b.locale=? OR b.locale='*') ORDER BY b.sort_order,b.id",[$slot,$locale]); }
    catch(Throwable){ return []; }
}
function render_site_blocks(string $slot,string $locale): string {
    $items=site_blocks($slot,$locale); if(!$items)return '';
    $html='<section class="managed-blocks managed-blocks-'.e($slot).'"><div class="container managed-blocks-grid">';
    foreach($items as $b){
        if($b['type']==='ad' && !empty($b['ad_id'])){ try{$a=Database::one('SELECT slot FROM ads WHERE id=?',[(int)$b['ad_id']]); if($a)$html.=ad_slot((string)$a['slot'],$locale);}catch(Throwable){} continue; }
        $img='<img src="'.e($b['image_url']).'" alt="'.e($b['title']??'').'" loading="lazy">';
        if(!empty($b['target_url']))$img='<a href="'.e($b['target_url']).'">'.$img.'</a>';
        $html.='<article class="managed-image-card">'.$img.(!empty($b['title'])?'<strong>'.e($b['title']).'</strong>':'').'</article>';
    }
    return $html.'</div></section>';
}


function smtp_read_response($socket): string {
    $data=''; while (($line=fgets($socket,515))!==false){$data.=$line;if(strlen($line)<4||$line[3]!=='-')break;} return $data;
}
function smtp_command($socket,string $command,array $ok=[250]): bool {
    if($command!=='') fwrite($socket,$command."\r\n"); $resp=smtp_read_response($socket); return in_array((int)substr($resp,0,3),$ok,true);
}
function smtp_send_site_mail(string $to,string $subject,string $body): bool {
    $host=trim(setting('smtp_host','')); $port=max(1,(int)setting('smtp_port','587')); $user=trim(setting('smtp_username','')); $pass=(string)setting('smtp_password','');
    $enc=strtolower(trim(setting('smtp_encryption','tls'))); $from=trim(setting('smtp_from_email',setting('contact_email',''))); $fromName=trim(setting('smtp_from_name',setting('site_name',config('name'))));
    if($host===''||!filter_var($from,FILTER_VALIDATE_EMAIL))return false;
    $socket=@stream_socket_client(($enc==='ssl'?'ssl://':'').$host.':'.$port,$errno,$errstr,12,STREAM_CLIENT_CONNECT); if(!$socket)return false; stream_set_timeout($socket,12);
    $hello=preg_replace('/[^A-Za-z0-9.\-]/','',(string)($_SERVER['HTTP_HOST']??'localhost'))?:'localhost'; $ok=(int)substr(smtp_read_response($socket),0,3)===220;
    if($ok)$ok=smtp_command($socket,'EHLO '.$hello,[250]); if($ok&&$enc==='tls'){ $ok=smtp_command($socket,'STARTTLS',[220]); if($ok)$ok=@stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT); if($ok)$ok=smtp_command($socket,'EHLO '.$hello,[250]); }
    if($ok&&$user!==''){ $ok=smtp_command($socket,'AUTH LOGIN',[334]); if($ok)$ok=smtp_command($socket,base64_encode($user),[334]); if($ok)$ok=smtp_command($socket,base64_encode($pass),[235]); }
    if($ok)$ok=smtp_command($socket,'MAIL FROM:<'.$from.'>',[250]); if($ok)$ok=smtp_command($socket,'RCPT TO:<'.$to.'>',[250,251]); if($ok)$ok=smtp_command($socket,'DATA',[354]);
    if($ok){ $safe=preg_replace('/(?m)^\./','..',str_replace(["\r\n","\r"],"\n",$body)); $headers=['From: =?UTF-8?B?'.base64_encode($fromName).'?= <'.$from.'>','To: <'.$to.'>','Subject: =?UTF-8?B?'.base64_encode($subject).'?=','MIME-Version: 1.0','Content-Type: text/plain; charset=UTF-8','Content-Transfer-Encoding: 8bit','Date: '.date(DATE_RFC2822),'Message-ID: <'.bin2hex(random_bytes(12)).'@'.$hello.'>']; fwrite($socket,implode("\r\n",$headers)."\r\n\r\n".str_replace("\n","\r\n",$safe)."\r\n.\r\n"); $ok=(int)substr(smtp_read_response($socket),0,3)===250; }
    @smtp_command($socket,'QUIT',[221,250]); fclose($socket); return (bool)$ok;
}
function send_site_mail(string $to, string $subject, string $body): bool {
    if(!filter_var($to,FILTER_VALIDATE_EMAIL))return false;
    if(setting('smtp_enabled','0')==='1'&&trim(setting('smtp_host',''))!==''&&smtp_send_site_mail($to,$subject,$body))return true;
    $headers=['MIME-Version: 1.0','Content-Type: text/plain; charset=UTF-8']; $from=setting('smtp_from_email',setting('contact_email','')); $fromName=setting('smtp_from_name',setting('site_name',config('name')));
    if(filter_var($from,FILTER_VALIDATE_EMAIL))$headers[]='From: =?UTF-8?B?'.base64_encode($fromName).'?= <'.$from.'>';
    return @mail($to,'=?UTF-8?B?'.base64_encode($subject).'?=',$body,implode("\r\n",$headers));
}

function send_logged_site_mail(string $event,string $to,string $subject,string $body,string $relatedType='',?int $relatedId=null): bool {
    $attemptId=null; try{$attemptId=\App\Core\Database::insert('INSERT INTO email_delivery_attempts(event_key,recipient,subject,status,attempts,related_type,related_id) VALUES(?,?,?,\'queued\',0,?,?)',[$event,$to,$subject,$relatedType,$relatedId]);}catch(\Throwable){}
    $ok=false; $error=''; try{$ok=send_site_mail($to,$subject,$body);if(!$ok)$error='Mail transport rejected the message';}catch(\Throwable $e){$error=$e->getMessage();}
    if($attemptId){try{\App\Core\Database::exec('UPDATE email_delivery_attempts SET status=?,attempts=attempts+1,error=?,sent_at=IF(?=1,NOW(),NULL) WHERE id=?',[$ok?'accepted':'failed',$error,$ok?1:0,$attemptId]);}catch(\Throwable){}}
    return $ok;
}


function base32_decode_saradeeeb(string $secret): string {
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $secret=strtoupper(preg_replace('/[^A-Z2-7]/i','',$secret)??''); $bits='';
    foreach(str_split($secret) as $c){$v=strpos($alphabet,$c); if($v===false)continue; $bits.=str_pad(decbin($v),5,'0',STR_PAD_LEFT);}
    $out=''; for($i=0;$i+8<=strlen($bits);$i+=8)$out.=chr(bindec(substr($bits,$i,8))); return $out;
}
function totp_code(string $secret, ?int $time=null): string {
    $counter=intdiv($time??time(),30); $bin=pack('N*',0).pack('N*',$counter); $hash=hash_hmac('sha1',$bin,base32_decode_saradeeeb($secret),true); $offset=ord($hash[19])&15; $num=((ord($hash[$offset])&127)<<24)|((ord($hash[$offset+1])&255)<<16)|((ord($hash[$offset+2])&255)<<8)|(ord($hash[$offset+3])&255); return str_pad((string)($num%1000000),6,'0',STR_PAD_LEFT);
}
function totp_verify(string $secret,string $code): bool { $code=preg_replace('/\D/','',$code)??''; if(strlen($code)!==6)return false; $now=time(); foreach([-30,0,30] as $d)if(hash_equals(totp_code($secret,$now+$d),$code))return true; return false; }
function generate_base32_secret(int $length=32): string { $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $out=''; for($i=0;$i<$length;$i++)$out.=$alphabet[random_int(0,31)]; return $out; }

function audit_log(string $action, string $targetType='', ?int $targetId=null, string $details=''): void {
    try {
        $u = auth_user();
        Database::insert(
            'INSERT INTO audit_logs(user_id,action,target_type,target_id,details,ip_address,user_agent) VALUES(?,?,?,?,?,?,?)',
            [$u['id'] ?? null,$action,$targetType,$targetId,$details,substr((string)($_SERVER['REMOTE_ADDR'] ?? ''),0,64),substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,255)]
        );
    } catch (Throwable) {}
}

function active_site_popup(string $locale): ?array {
    try {
        return Database::one(
            "SELECT * FROM site_notifications WHERE active=1 AND id NOT IN (SELECT COALESCE(notification_id,0) FROM writer_thanks) AND (locale=? OR locale='*') AND (starts_at IS NULL OR starts_at<=CURRENT_TIMESTAMP) AND (ends_at IS NULL OR ends_at>=CURRENT_TIMESTAMP) ORDER BY locale DESC,id DESC LIMIT 1",
            [$locale]
        );
    } catch (Throwable) { return null; }
}

function active_writer_awards(string $locale): array {
    try { return Database::all("SELECT t.*,u.name writer_name,u.username,u.avatar,u.writer_verified,u.writer_honored,p.professional_title FROM writer_thanks t JOIN users u ON u.id=t.user_id LEFT JOIN writer_profiles p ON p.user_id=u.id WHERE t.active=1 AND t.announce_on_site=1 AND (t.locale=? OR t.locale='*') AND (t.starts_at IS NULL OR t.starts_at<=CURRENT_TIMESTAMP) AND (t.ends_at IS NULL OR t.ends_at>=CURRENT_TIMESTAMP) ORDER BY t.id",[$locale]); }
    catch(Throwable){ return []; }
}

function writer_thank(int $userId): ?array {
    try { return Database::one("SELECT * FROM writer_thanks WHERE user_id=? AND active=1 AND (starts_at IS NULL OR starts_at<=CURRENT_TIMESTAMP) AND (ends_at IS NULL OR ends_at>=CURRENT_TIMESTAMP) ORDER BY id DESC LIMIT 1",[$userId]); }
    catch (Throwable) { return null; }
}

function writer_awards(int $userId): array {
    try { return Database::all("SELECT * FROM writer_thanks WHERE user_id=? AND active=1 ORDER BY created_at DESC,id DESC",[$userId]); }
    catch (Throwable) { return []; }
}




function time_ago(string $date,string $locale='ar'): string {
    $ts=strtotime($date); if(!$ts)return $date; $d=max(0,time()-$ts);
    if($locale!=='ar'){
        if($d<60)return 'just now'; if($d<3600)return floor($d/60).' min ago'; if($d<86400)return floor($d/3600).' h ago'; if($d<604800)return floor($d/86400).' d ago'; return date('Y-m-d',$ts);
    }
    if($d<60)return 'الآن'; if($d<3600)return 'منذ '.floor($d/60).' دقيقة'; if($d<86400)return 'منذ '.floor($d/3600).' ساعة'; if($d<604800)return 'منذ '.floor($d/86400).' يوم'; return date('Y-m-d',$ts);
}

function community_ui(string $locale): array {
    $copy=[
        'ar'=>['brand'=>'منتدى سراديب','hero'=>'مكان واحد للنقاش والكتابة وتبادل الخبرات.','description'=>'مساحة تجمع القراء والكتاب والهواة للنقاش وتبادل الأفكار والخبرات.','start'=>'ابدأ نقاشا','register'=>'أنشئ حسابا وشارك','login'=>'تسجيل الدخول','filter_label'=>'تصفية النقاشات','latest'=>'الأحدث','discussed'=>'الأكثر نقاشا','views_filter'=>'الأكثر مشاهدة','unanswered'=>'بلا ردود','active'=>'الأكثر نشاطا','following'=>'أتابعها','search'=>'ابحث في النقاشات والردود والأعضاء...','week'=>'نقاش الأسبوع','enter'=>'دخول للنقاش','sections'=>'الأقسام','choose'=>'اختر المساحة المناسبة','discussion_count'=>'نقاش','discussions'=>'النقاشات','results'=>'نتائج البحث','now'=>'ما الذي يدور الآن؟','saved'=>'المحفوظات','notifications'=>'الإشعارات','empty'=>'لا توجد نقاشات هنا بعد.','empty_text'=>'يمكنك أن تكون أول من يبدأ الحديث.','reply'=>'رد','views'=>'مشاهدة','pinned'=>'مثبت','section'=>'قسم','back'=>'منتدى سراديب','empty_section'=>'لا توجد نقاشات منشورة بهذا القسم حتى الآن.','previous'=>'السابق','next'=>'التالي','calm'=>'هادئ','warm'=>'دافئ','hot'=>'ساخن','blazing'=>'مشتعل'],
        'en'=>['brand'=>'Saradeeeb community','hero'=>'One place to discuss, write and exchange experience.','description'=>'A space for readers, writers and enthusiasts to exchange ideas and experience.','start'=>'Start a discussion','register'=>'Create an account and join','login'=>'Sign in','filter_label'=>'Filter discussions','latest'=>'Latest','discussed'=>'Most discussed','views_filter'=>'Most viewed','unanswered'=>'Unanswered','active'=>'Most active','following'=>'Following','search'=>'Search discussions, replies and members...','week'=>'Discussion of the week','enter'=>'Open discussion','sections'=>'Sections','choose'=>'Choose the right space','discussion_count'=>'discussions','discussions'=>'Discussions','results'=>'Search results','now'=>'What is happening now?','saved'=>'Saved','notifications'=>'Notifications','empty'=>'No discussions here yet.','empty_text'=>'You can be the first to start one.','reply'=>'replies','views'=>'views','pinned'=>'Pinned','section'=>'Section','back'=>'Saradeeeb community','empty_section'=>'No published discussions in this section yet.','previous'=>'Previous','next'=>'Next','calm'=>'Calm','warm'=>'Warm','hot'=>'Hot','blazing'=>'Trending'],
        'es'=>['brand'=>'Comunidad Saradeeeb','hero'=>'Un espacio para debatir, escribir y compartir experiencias.','description'=>'Un espacio para lectores, autores y aficionados que comparten ideas y experiencias.','start'=>'Iniciar un debate','register'=>'Crea una cuenta y participa','login'=>'Iniciar sesion','filter_label'=>'Filtrar debates','latest'=>'Recientes','discussed'=>'Mas debatidos','views_filter'=>'Mas vistos','unanswered'=>'Sin respuestas','active'=>'Mas activos','following'=>'Siguiendo','search'=>'Buscar debates, respuestas y miembros...','week'=>'Debate de la semana','enter'=>'Abrir debate','sections'=>'Secciones','choose'=>'Elige el espacio adecuado','discussion_count'=>'debates','discussions'=>'Debates','results'=>'Resultados','now'=>'Que esta pasando ahora?','saved'=>'Guardados','notifications'=>'Notificaciones','empty'=>'Aun no hay debates.','empty_text'=>'Puedes ser la primera persona en iniciar uno.','reply'=>'respuestas','views'=>'vistas','pinned'=>'Fijado','section'=>'Seccion','back'=>'Comunidad Saradeeeb','empty_section'=>'Aun no hay debates publicados en esta seccion.','previous'=>'Anterior','next'=>'Siguiente','calm'=>'Tranquilo','warm'=>'Activo','hot'=>'Popular','blazing'=>'Tendencia'],
        'fr'=>['brand'=>'Communaute Saradeeeb','hero'=>'Un espace pour discuter, ecrire et partager les experiences.','description'=>'Un espace pour les lecteurs, auteurs et passionnes qui echangent idees et experiences.','start'=>'Lancer une discussion','register'=>'Creer un compte et participer','login'=>'Se connecter','filter_label'=>'Filtrer les discussions','latest'=>'Recentes','discussed'=>'Plus discutees','views_filter'=>'Plus vues','unanswered'=>'Sans reponse','active'=>'Plus actives','following'=>'Suivies','search'=>'Rechercher discussions, reponses et membres...','week'=>'Discussion de la semaine','enter'=>'Ouvrir la discussion','sections'=>'Rubriques','choose'=>'Choisissez le bon espace','discussion_count'=>'discussions','discussions'=>'Discussions','results'=>'Resultats','now'=>'Que se passe-t-il maintenant ?','saved'=>'Enregistrees','notifications'=>'Notifications','empty'=>'Aucune discussion pour le moment.','empty_text'=>'Vous pouvez lancer la premiere.','reply'=>'reponses','views'=>'vues','pinned'=>'Epingle','section'=>'Rubrique','back'=>'Communaute Saradeeeb','empty_section'=>'Aucune discussion publiee dans cette rubrique.','previous'=>'Precedent','next'=>'Suivant','calm'=>'Calme','warm'=>'Active','hot'=>'Populaire','blazing'=>'Tendance'],
        'ru'=>['brand'=>'Сообщество Saradeeeb','hero'=>'Единое пространство для обсуждений, текстов и обмена опытом.','description'=>'Пространство для читателей, авторов и энтузиастов, где можно делиться идеями и опытом.','start'=>'Начать обсуждение','register'=>'Создать аккаунт','login'=>'Войти','filter_label'=>'Фильтр обсуждений','latest'=>'Новые','discussed'=>'Обсуждаемые','views_filter'=>'Просматриваемые','unanswered'=>'Без ответов','active'=>'Активные','following'=>'Отслеживаемые','search'=>'Поиск по обсуждениям, ответам и участникам...','week'=>'Обсуждение недели','enter'=>'Открыть обсуждение','sections'=>'Разделы','choose'=>'Выберите подходящий раздел','discussion_count'=>'обсуждений','discussions'=>'Обсуждения','results'=>'Результаты поиска','now'=>'Что обсуждают сейчас?','saved'=>'Сохраненные','notifications'=>'Уведомления','empty'=>'Обсуждений пока нет.','empty_text'=>'Вы можете начать первым.','reply'=>'ответов','views'=>'просмотров','pinned'=>'Закреплено','section'=>'Раздел','back'=>'Сообщество Saradeeeb','empty_section'=>'В этом разделе пока нет публикаций.','previous'=>'Назад','next'=>'Далее','calm'=>'Спокойно','warm'=>'Активно','hot'=>'Популярно','blazing'=>'В тренде'],
    ];
    return $copy[$locale]??$copy['en'];
}

function community_detail_ui(string $locale): array {
    $en=[
        'brand'=>'Saradeeeb community','views'=>'views','replies'=>'replies','closed'=>'Closed','article_discussion'=>'Article discussion','open_article'=>'Open original article','like'=>'Like','reply'=>'Reply','saved'=>'Saved','save'=>'Save','following'=>'Following','follow'=>'Follow discussion','login_interact'=>'Sign in to reply and interact','share'=>'Share','report'=>'Report','abuse'=>'Abusive content','spam'=>'Spam','offtopic'=>'Off topic','copyright'=>'Copyright','other'=>'Other reason','optional_details'=>'Optional details','send_report'=>'Send report','no_replies'=>'No replies yet.','first_reply'=>'Start the discussion with the first opinion.','reply_to'=>'Reply to','replying_to'=>'Replying to','reply_placeholder'=>'Add your reply... Use @username to mention a member.','publish_reply'=>'Publish reply','in_discussion'=>'In this discussion','last_activity'=>'Last activity','author'=>'Author','my_notifications'=>'My notifications','saved_topics'=>'Saved topics','alert_settings'=>'Notification settings','my_profile'=>'My profile',
        'back'=>'Back to community','new_discussion'=>'New discussion','start_discussion'=>'Start a discussion','start_intro'=>'Write clearly and leave room for others to add their views and experiences.','discussion_title'=>'Discussion title','discussion_title_ph'=>'What idea would you like to discuss?','section'=>'Section','choose_section'=>'Choose a section','list'=>'List','quote'=>'Quote','link'=>'Link','image'=>'Image','content'=>'Discussion content','content_ph'=>'Write the discussion details here...','optional_image'=>'Optional image','optional_tags'=>'Optional tags','tags_ph'=>'writing, experience, question','publish_discussion'=>'Publish discussion',
        'notifications'=>'Community notifications','mark_read'=>'Mark all as read','no_notifications'=>'No notifications yet.','saved_private'=>'This list is private and is not visible to others.','no_saved'=>'You have not saved any topic yet.','notification_settings'=>'Community notification settings','notification_intro'=>'In-site notifications remain available. Control which important alerts reach your email.','email_replies'=>'Reply notifications','email_mentions'=>'Mention notifications','email_follows'=>'Updates from discussions I follow','email_admin'=>'Administration messages','email_hint'=>'Send important alerts only to your email.','save_settings'=>'Save settings',
        'your_account'=>'Your account','account_title'=>'My Saradeeeb community profile','account_intro'=>'Your photo, bio and name appear with your discussions and replies. Username and email are fixed here to protect account links.','public_profile'=>'View public profile','change_photo'=>'Change photo','name'=>'Name','username'=>'Username','email'=>'Email','bio'=>'Short bio','bio_ph'=>'Briefly introduce yourself to the Saradeeeb community...','save_profile'=>'Save profile','password'=>'Change or recover password','member_default'=>'Saradeeeb community member.','joined'=>'Joined','topics'=>'discussions','articles'=>'articles','topic_tab'=>'Discussions','reply_tab'=>'Replies','article_tab'=>'Articles','reply_in'=>'Reply in','article'=>'Article',
        'login_required'=>'Sign in first to participate in the Saradeeeb community.','inactive_account'=>'The account is not active.','banned'=>'Your participation in the Saradeeeb community has been suspended.','ban_reason'=>'Reason:','limit_topics'=>'You have reached the temporary discussion limit. Try again later.','invalid_topic'=>'Enter a clear title, choose a section and add enough content.','too_many_links'=>'The discussion contains more links than allowed.','invalid_section'=>'The section is invalid.','topic_missing'=>'The topic was not found.','topic_closed'=>'This discussion is closed.','limit_replies'=>'The temporary reply limit has been reached. Try again later.','empty_reply'=>'Write a reply before sending.','invalid_report'=>'The item you want to report is unavailable.','duplicate_report'=>'Your report about this item is already under review.','report_sent'=>'The report was sent to the moderation team.','preferences_saved'=>'Notification settings were saved.','invalid_profile'=>'Check the name and bio.','profile_saved'=>'Your community profile was updated.'
    ];
    $copy=[
        'ar'=>[
            'brand'=>'منتدى سراديب','views'=>'مشاهدة','replies'=>'رد','closed'=>'مغلق','article_discussion'=>'نقاش حول مقال','open_article'=>'فتح المقالة الأصلية','like'=>'إعجاب','reply'=>'رد','saved'=>'محفوظ','save'=>'حفظ','following'=>'تتابعه','follow'=>'متابعة النقاش','login_interact'=>'سجّل الدخول للرد والتفاعل','share'=>'مشاركة','report'=>'إبلاغ','abuse'=>'محتوى مسيء','spam'=>'سبام','offtopic'=>'خارج الموضوع','copyright'=>'حقوق نشر','other'=>'سبب آخر','optional_details'=>'تفاصيل اختيارية','send_report'=>'إرسال البلاغ','no_replies'=>'لا توجد ردود بعد.','first_reply'=>'ابدأ النقاش بأول رأي.','reply_to'=>'رد على','replying_to'=>'ردًا على','reply_placeholder'=>'أضف ردك... استخدم @username لذكر عضو.','publish_reply'=>'نشر الرد','in_discussion'=>'في هذا النقاش','last_activity'=>'آخر نشاط','author'=>'الكاتب','my_notifications'=>'إشعاراتي','saved_topics'=>'المحفوظات','alert_settings'=>'إعدادات التنبيهات','my_profile'=>'ملفي الشخصي',
            'back'=>'العودة للمنتدى','new_discussion'=>'نقاش جديد','start_discussion'=>'ابدأ نقاشًا','start_intro'=>'اكتب بوضوح واترك مساحة للآخرين لإضافة آرائهم وتجاربهم.','discussion_title'=>'عنوان النقاش','discussion_title_ph'=>'ما الفكرة التي تريد مناقشتها؟','section'=>'القسم','choose_section'=>'اختر القسم','list'=>'قائمة','quote'=>'اقتباس','link'=>'رابط','image'=>'صورة','content'=>'محتوى النقاش','content_ph'=>'اكتب تفاصيل النقاش هنا...','optional_image'=>'صورة اختيارية','optional_tags'=>'وسوم اختيارية','tags_ph'=>'كتابة، تجربة، سؤال','publish_discussion'=>'نشر النقاش',
            'notifications'=>'إشعارات المنتدى','mark_read'=>'تعليم الكل كمقروء','no_notifications'=>'لا توجد إشعارات حتى الآن.','saved_private'=>'هذه القائمة خاصة بك ولا تظهر للآخرين.','no_saved'=>'لم تحفظ أي موضوع بعد.','notification_settings'=>'إعدادات إشعارات المنتدى','notification_intro'=>'الإشعارات داخل الموقع تبقى متاحة. يمكنك هنا التحكم بما يصلك عبر البريد.','email_replies'=>'إشعارات الردود','email_mentions'=>'إشعارات المنشن','email_follows'=>'تحديثات النقاشات التي أتابعها','email_admin'=>'رسائل الإدارة','email_hint'=>'إرسال التنبيهات المهمة فقط إلى بريدك.','save_settings'=>'حفظ الإعدادات',
            'your_account'=>'حسابك','account_title'=>'ملفي في منتدى سراديب','account_intro'=>'الصورة والنبذة والاسم تظهر مع نقاشاتك وردودك. اسم المستخدم والبريد ثابتان هنا لحماية الروابط والحساب.','public_profile'=>'عرض ملفي العام','change_photo'=>'تغيير الصورة','name'=>'الاسم','username'=>'اسم المستخدم','email'=>'البريد الإلكتروني','bio'=>'نبذة قصيرة','bio_ph'=>'عرّف أعضاء سراديب بنفسك باختصار...','save_profile'=>'حفظ الملف الشخصي','password'=>'تغيير/استعادة كلمة المرور','member_default'=>'عضو في منتدى سراديب.','joined'=>'انضم في','topics'=>'نقاش','articles'=>'مقال','topic_tab'=>'النقاشات','reply_tab'=>'الردود','article_tab'=>'المقالات','reply_in'=>'رد في','article'=>'مقال',
            'login_required'=>'سجّل الدخول أولًا للمشاركة في منتدى سراديب.','inactive_account'=>'الحساب غير نشط.','banned'=>'تم تعليق مشاركتك في منتدى سراديب.','ban_reason'=>'السبب:','limit_topics'=>'وصلت إلى الحد المؤقت لإنشاء النقاشات. حاول لاحقًا.','invalid_topic'=>'اكتب عنوانًا واضحًا، اختر قسمًا، وأضف محتوى كافيًا.','too_many_links'=>'عدد الروابط في النقاش أكبر من الحد المسموح.','invalid_section'=>'القسم غير صالح.','topic_missing'=>'الموضوع غير موجود.','topic_closed'=>'هذا النقاش مغلق.','limit_replies'=>'تم بلوغ حد الردود المؤقت. حاول لاحقًا.','empty_reply'=>'اكتب ردًا قبل الإرسال.','invalid_report'=>'العنصر الذي تريد الإبلاغ عنه غير متاح.','duplicate_report'=>'بلاغك عن هذا العنصر قيد المراجعة بالفعل.','report_sent'=>'تم إرسال البلاغ إلى فريق الإشراف.','preferences_saved'=>'تم حفظ إعدادات الإشعارات.','invalid_profile'=>'تحقق من الاسم والنبذة.','profile_saved'=>'تم تحديث ملفك في منتدى سراديب.'
        ],
        'es'=>['brand'=>'Comunidad Saradeeeb','views'=>'vistas','replies'=>'respuestas','closed'=>'Cerrado','article_discussion'=>'Debate del articulo','open_article'=>'Abrir articulo original','like'=>'Me gusta','reply'=>'Responder','saved'=>'Guardado','save'=>'Guardar','following'=>'Siguiendo','follow'=>'Seguir debate','login_interact'=>'Inicia sesion para responder','share'=>'Compartir','report'=>'Reportar','no_replies'=>'Aun no hay respuestas.','first_reply'=>'Inicia el debate con la primera opinion.','back'=>'Volver a la comunidad','new_discussion'=>'Nuevo debate','start_discussion'=>'Iniciar un debate','discussion_title'=>'Titulo del debate','section'=>'Seccion','choose_section'=>'Elige una seccion','content'=>'Contenido','publish_discussion'=>'Publicar debate','notifications'=>'Notificaciones','mark_read'=>'Marcar todo como leido','no_notifications'=>'Aun no hay notificaciones.','saved_private'=>'Esta lista es privada.','no_saved'=>'No has guardado ningun tema.','notification_settings'=>'Configuracion de notificaciones','save_settings'=>'Guardar configuracion','your_account'=>'Tu cuenta','account_title'=>'Mi perfil en la comunidad','public_profile'=>'Ver perfil publico','change_photo'=>'Cambiar foto','name'=>'Nombre','username'=>'Usuario','email'=>'Correo','bio'=>'Biografia breve','save_profile'=>'Guardar perfil','joined'=>'Se unio','topic_tab'=>'Debates','reply_tab'=>'Respuestas','article_tab'=>'Articulos','article'=>'Articulo'],
        'fr'=>['brand'=>'Communaute Saradeeeb','views'=>'vues','replies'=>'reponses','closed'=>'Fermee','article_discussion'=>'Discussion de l article','open_article'=>'Ouvrir l article original','like'=>'J aime','reply'=>'Repondre','saved'=>'Enregistree','save'=>'Enregistrer','following'=>'Suivie','follow'=>'Suivre la discussion','login_interact'=>'Connectez-vous pour repondre','share'=>'Partager','report'=>'Signaler','no_replies'=>'Aucune reponse pour le moment.','first_reply'=>'Lancez la discussion avec le premier avis.','back'=>'Retour a la communaute','new_discussion'=>'Nouvelle discussion','start_discussion'=>'Lancer une discussion','discussion_title'=>'Titre','section'=>'Rubrique','choose_section'=>'Choisir une rubrique','content'=>'Contenu','publish_discussion'=>'Publier','notifications'=>'Notifications','mark_read'=>'Tout marquer comme lu','no_notifications'=>'Aucune notification.','saved_private'=>'Cette liste est privee.','no_saved'=>'Aucun sujet enregistre.','notification_settings'=>'Parametres de notification','save_settings'=>'Enregistrer','your_account'=>'Votre compte','account_title'=>'Mon profil communautaire','public_profile'=>'Voir le profil public','change_photo'=>'Changer la photo','name'=>'Nom','username'=>'Identifiant','email'=>'E-mail','bio'=>'Courte biographie','save_profile'=>'Enregistrer le profil','joined'=>'Inscrit en','topic_tab'=>'Discussions','reply_tab'=>'Reponses','article_tab'=>'Articles','article'=>'Article'],
        'ru'=>['brand'=>'Сообщество Saradeeeb','views'=>'просмотров','replies'=>'ответов','closed'=>'Закрыто','article_discussion'=>'Обсуждение статьи','open_article'=>'Открыть статью','like'=>'Нравится','reply'=>'Ответить','saved'=>'Сохранено','save'=>'Сохранить','following'=>'Отслеживается','follow'=>'Следить','login_interact'=>'Войдите, чтобы ответить','share'=>'Поделиться','report'=>'Пожаловаться','no_replies'=>'Ответов пока нет.','first_reply'=>'Начните обсуждение первым.','back'=>'Назад в сообщество','new_discussion'=>'Новое обсуждение','start_discussion'=>'Начать обсуждение','discussion_title'=>'Заголовок','section'=>'Раздел','choose_section'=>'Выберите раздел','content'=>'Содержание','publish_discussion'=>'Опубликовать','notifications'=>'Уведомления','mark_read'=>'Отметить все прочитанными','no_notifications'=>'Уведомлений пока нет.','saved_private'=>'Этот список виден только вам.','no_saved'=>'Сохраненных тем пока нет.','notification_settings'=>'Настройки уведомлений','save_settings'=>'Сохранить настройки','your_account'=>'Ваш аккаунт','account_title'=>'Мой профиль сообщества','public_profile'=>'Открыть профиль','change_photo'=>'Изменить фото','name'=>'Имя','username'=>'Логин','email'=>'Эл. почта','bio'=>'Краткая биография','save_profile'=>'Сохранить профиль','joined'=>'На сайте с','topic_tab'=>'Обсуждения','reply_tab'=>'Ответы','article_tab'=>'Статьи','article'=>'Статья']
    ];
    return array_replace($en,$copy[$locale]??[]);
}

function auth_ui(string $locale): array {
    $en=['brand'=>'Saradeeeb','login_visual_title'=>'Enter the editorial room.','login_visual_text'=>'Manage articles, translations and writers in one place.','back_site'=>'Back to the site','forgot'=>'Forgot your password?','create_account'=>'Create account','secure'=>'Secure connection','session'=>'Protected session','member_brand'=>'Saradeeeb community','member_title'=>'One account for reading and discussion.','member_visual_text'=>'Join discussions, save topics and follow what matters to you with one Saradeeeb account.','back_community'=>'Back to the community','member'=>'Member account','register_title'=>'Create a Saradeeeb account','register_intro'=>'We will email you a link to verify your address and create a password. The community uses the same site account.','name'=>'Name','username'=>'Username','email'=>'Email','create_verify'=>'Create account and verify email','want_writer'=>'Want to write officially for Saradeeeb?','apply_writer'=>'Apply as a writer','account_note'=>'One Saradeeeb account; no separate community account','recover'=>'Account recovery','forgot_title'=>'Forgot your password?','forgot_intro'=>'Enter your email and we will send a link valid for one hour.','send_reset'=>'Send recovery link','back_login'=>'Back to sign in','writer_eyebrow'=>'Write for Saradeeeb','writer_title'=>'Apply to join as a writer','writer_intro'=>'Submitting this form does not create an account automatically. The admin team reviews your application and, if approved, sends you an email verification and password setup link.','full_name'=>'Full name','phone'=>'Phone / WhatsApp','country'=>'Country','city'=>'City','topics'=>'Topics you prefer to write about','topics_ph'=>'Example: myths, crime, history...','experience'=>'Writing experience','sample'=>'Previous writing sample URL - optional','bio'=>'Tell us about yourself and why you want to write for Saradeeeb','consent'=>'I agree that Saradeeeb may use this information to review my application and contact me.','submit_application'=>'Send application to Saradeeeb','invalid_login'=>'The credentials are incorrect or the account has not been activated.','register_blocked'=>'Account creation is temporarily blocked from this connection. Try again later.','invalid_register'=>'Enter a clear name, a username of at least 3 characters and a valid email.','account_exists'=>'The email or username is already in use.','register_ok'=>'Account created. Open the verification email to create your password.','invalid_application'=>'Enter your name, email, phone number and a clear introduction.','application_ok'=>'Application saved. Verify your email first; it will not reach the admin team before verification.','invalid_link'=>'The verification link is invalid.','expired_link'=>'The verification link is invalid or has expired.','application_verified'=>'Your email is verified and the application has reached the Saradeeeb team.','forgot_ok'=>'If the email belongs to an account, a recovery link valid for one hour has been sent.','reset_title'=>'New password'];
    $copy=[
        'ar'=>['brand'=>'سراديب','login_visual_title'=>'ادخل إلى غرفة التحرير.','login_visual_text'=>'إدارة المقالات والترجمات والكتاب من مكان واحد.','back_site'=>'العودة إلى الموقع','forgot'=>'نسيت كلمة المرور؟','create_account'=>'إنشاء حساب','secure'=>'اتصال آمن','session'=>'الجلسة محمية','member_brand'=>'منتدى سراديب','member_title'=>'حساب واحد للقراءة والنقاش.','member_visual_text'=>'شارك في النقاشات واحفظ المواضيع وتابع ما يهمك من حساب سراديب واحد.','back_community'=>'العودة إلى المنتدى','member'=>'حساب عضو','register_title'=>'إنشاء حساب سراديب','register_intro'=>'سنرسل لك رابطا لتأكيد البريد وإنشاء كلمة المرور. يستخدم المنتدى حساب الموقع نفسه.','name'=>'الاسم','username'=>'اسم المستخدم','email'=>'البريد الإلكتروني','create_verify'=>'إنشاء الحساب وتأكيد البريد','want_writer'=>'تريد الكتابة رسميا في سراديب؟','apply_writer'=>'قدم طلب كاتب','account_note'=>'حساب سراديب واحد؛ لا يوجد حساب منفصل للمنتدى','recover'=>'استعادة الحساب','forgot_title'=>'نسيت كلمة المرور؟','forgot_intro'=>'أدخل بريدك وسنرسل رابطا صالحا لمدة ساعة.','send_reset'=>'إرسال رابط الاستعادة','back_login'=>'العودة إلى تسجيل الدخول','writer_eyebrow'=>'الكتابة في سراديب','writer_title'=>'أرسل طلب الانضمام ككاتب','writer_intro'=>'إرسال الطلب لا ينشئ حسابا تلقائيا. تراجع الإدارة طلبك، وإذا تمت الموافقة يصلك رابط تأكيد البريد وإنشاء كلمة المرور.','full_name'=>'الاسم الكامل','phone'=>'رقم الهاتف / واتساب','country'=>'الدولة','city'=>'المدينة','topics'=>'المجالات التي تفضل الكتابة عنها','topics_ph'=>'مثال: أساطير، جرائم، تاريخ...','experience'=>'خبرتك في الكتابة','sample'=>'رابط لنموذج كتابة سابق - اختياري','bio'=>'عرفنا بنفسك ولماذا تريد الكتابة في سراديب','consent'=>'أوافق على استخدام هذه البيانات لمراجعة طلبي والتواصل معي.','submit_application'=>'إرسال الطلب إلى إدارة سراديب','invalid_login'=>'بيانات الدخول غير صحيحة، أو الحساب لم يفعل بعد.','register_blocked'=>'تم إيقاف إنشاء الحسابات مؤقتا من هذا الاتصال. حاول لاحقا.','invalid_register'=>'أدخل اسما واضحا واسم مستخدم من 3 محارف على الأقل وبريدا صحيحا.','account_exists'=>'البريد أو اسم المستخدم مستخدم بالفعل.','register_ok'=>'تم إنشاء الحساب. افتح رسالة التفعيل لإنشاء كلمة المرور.','invalid_application'=>'أدخل الاسم والبريد ورقم الهاتف ونبذة واضحة عنك.','application_ok'=>'تم حفظ الطلب. أكد بريدك أولا؛ لن يصل الطلب إلى الإدارة قبل التأكيد.','invalid_link'=>'رابط التأكيد غير صالح.','expired_link'=>'رابط التأكيد غير صالح أو انتهت صلاحيته.','application_verified'=>'تم تأكيد بريدك ووصل طلبك إلى إدارة سراديب.','forgot_ok'=>'إذا كان البريد مرتبطا بحساب، أرسلنا رابط استعادة صالحا لمدة ساعة.','reset_title'=>'كلمة مرور جديدة'],
        'en'=>$en,
        'es'=>array_replace($en,['brand'=>'Saradeeeb','login_visual_title'=>'Entra en la sala editorial.','login_visual_text'=>'Gestiona articulos, traducciones y autores en un solo lugar.','back_site'=>'Volver al sitio','forgot'=>'Olvidaste tu contrasena?','create_account'=>'Crear cuenta','secure'=>'Conexion segura','session'=>'Sesion protegida','member_brand'=>'Comunidad Saradeeeb','member_title'=>'Una cuenta para leer y debatir.','member_visual_text'=>'Participa, guarda temas y sigue tus intereses con una sola cuenta.','back_community'=>'Volver a la comunidad','member'=>'Cuenta de miembro','register_title'=>'Crear una cuenta Saradeeeb','register_intro'=>'Te enviaremos un enlace para verificar el correo y crear la contrasena.','name'=>'Nombre','username'=>'Usuario','email'=>'Correo electronico','create_verify'=>'Crear cuenta y verificar correo','want_writer'=>'Quieres escribir oficialmente en Saradeeeb?','apply_writer'=>'Solicitar ser autor','account_note'=>'Una sola cuenta de Saradeeeb','recover'=>'Recuperar cuenta','forgot_title'=>'Olvidaste tu contrasena?','forgot_intro'=>'Introduce tu correo y enviaremos un enlace valido por una hora.','send_reset'=>'Enviar enlace','back_login'=>'Volver al inicio de sesion','writer_eyebrow'=>'Escribe en Saradeeeb','writer_title'=>'Solicitud para ser autor','writer_intro'=>'Enviar la solicitud no crea una cuenta automaticamente. El equipo la revisara y, si se aprueba, recibiras un enlace para verificar el correo y crear la contrasena.','full_name'=>'Nombre completo','phone'=>'Telefono / WhatsApp','country'=>'Pais','city'=>'Ciudad','topics'=>'Temas sobre los que prefieres escribir','topics_ph'=>'Ejemplo: mitos, crimen, historia...','experience'=>'Experiencia de escritura','sample'=>'Enlace a una muestra - opcional','bio'=>'Cuentanos sobre ti y por que quieres escribir','consent'=>'Acepto que Saradeeeb use estos datos para revisar mi solicitud y contactarme.','submit_application'=>'Enviar solicitud','invalid_login'=>'Los datos son incorrectos o la cuenta no esta activada.','register_blocked'=>'La creacion de cuentas esta temporalmente bloqueada.','invalid_register'=>'Introduce un nombre, un usuario de al menos 3 caracteres y un correo valido.','account_exists'=>'El correo o el usuario ya esta en uso.','register_ok'=>'Cuenta creada. Abre el correo de verificacion para crear la contrasena.','invalid_application'=>'Introduce nombre, correo, telefono y una presentacion clara.','application_ok'=>'Solicitud guardada. Verifica tu correo antes de enviarla al equipo.','invalid_link'=>'El enlace no es valido.','expired_link'=>'El enlace no es valido o ha caducado.','application_verified'=>'Tu correo esta verificado y el equipo ha recibido la solicitud.','forgot_ok'=>'Si el correo pertenece a una cuenta, enviamos un enlace valido por una hora.','reset_title'=>'Nueva contrasena']),
        'fr'=>array_replace($en,['login_visual_title'=>'Entrez dans la salle de redaction.','login_visual_text'=>'Gerez articles, traductions et auteurs au meme endroit.','back_site'=>'Retour au site','forgot'=>'Mot de passe oublie ?','create_account'=>'Creer un compte','secure'=>'Connexion securisee','session'=>'Session protegee','member_brand'=>'Communaute Saradeeeb','member_title'=>'Un compte pour lire et discuter.','member_visual_text'=>'Participez, enregistrez des sujets et suivez vos centres d interet.','back_community'=>'Retour a la communaute','member'=>'Compte membre','register_title'=>'Creer un compte Saradeeeb','register_intro'=>'Nous vous enverrons un lien pour verifier votre adresse et creer le mot de passe.','name'=>'Nom','username'=>'Nom d utilisateur','email'=>'E-mail','create_verify'=>'Creer et verifier le compte','want_writer'=>'Vous souhaitez ecrire pour Saradeeeb ?','apply_writer'=>'Devenir auteur','account_note'=>'Un seul compte Saradeeeb','recover'=>'Recuperation du compte','forgot_title'=>'Mot de passe oublie ?','forgot_intro'=>'Saisissez votre e-mail pour recevoir un lien valable une heure.','send_reset'=>'Envoyer le lien','back_login'=>'Retour a la connexion','writer_eyebrow'=>'Ecrire pour Saradeeeb','writer_title'=>'Demande pour devenir auteur','writer_intro'=>'La demande ne cree pas automatiquement un compte. L equipe la verifie puis envoie, si elle est approuvee, un lien de confirmation et de creation du mot de passe.','full_name'=>'Nom complet','phone'=>'Telephone / WhatsApp','country'=>'Pays','city'=>'Ville','topics'=>'Sujets que vous preferez traiter','topics_ph'=>'Exemple : mythes, crimes, histoire...','experience'=>'Experience de redaction','sample'=>'Lien vers un texte - facultatif','bio'=>'Presentez-vous et expliquez pourquoi vous souhaitez ecrire','consent'=>'J accepte que Saradeeeb utilise ces informations pour examiner ma demande et me contacter.','submit_application'=>'Envoyer la demande','invalid_login'=>'Les identifiants sont incorrects ou le compte n est pas active.','register_blocked'=>'La creation de comptes est temporairement bloquee.','invalid_register'=>'Saisissez un nom, un identifiant de 3 caracteres minimum et un e-mail valide.','account_exists'=>'L e-mail ou l identifiant est deja utilise.','register_ok'=>'Compte cree. Consultez l e-mail de verification pour creer le mot de passe.','invalid_application'=>'Saisissez votre nom, e-mail, telephone et une presentation claire.','application_ok'=>'Demande enregistree. Verifiez votre e-mail avant son envoi a l equipe.','invalid_link'=>'Le lien de verification est invalide.','expired_link'=>'Le lien est invalide ou expire.','application_verified'=>'Votre e-mail est verifie et l equipe a recu la demande.','forgot_ok'=>'Si cet e-mail correspond a un compte, un lien valable une heure a ete envoye.','reset_title'=>'Nouveau mot de passe']),
        'ru'=>array_replace($en,['login_visual_title'=>'Войдите в редакционную систему.','login_visual_text'=>'Управляйте статьями, переводами и авторами в одном месте.','back_site'=>'Вернуться на сайт','forgot'=>'Забыли пароль?','create_account'=>'Создать аккаунт','secure'=>'Безопасное соединение','session'=>'Защищенная сессия','member_brand'=>'Сообщество Saradeeeb','member_title'=>'Один аккаунт для чтения и обсуждений.','member_visual_text'=>'Участвуйте в обсуждениях, сохраняйте темы и следите за интересами.','back_community'=>'Вернуться в сообщество','member'=>'Аккаунт участника','register_title'=>'Создать аккаунт Saradeeeb','register_intro'=>'Мы отправим ссылку для подтверждения почты и создания пароля.','name'=>'Имя','username'=>'Имя пользователя','email'=>'Эл. почта','create_verify'=>'Создать аккаунт и подтвердить почту','want_writer'=>'Хотите писать для Saradeeeb?','apply_writer'=>'Подать заявку автора','account_note'=>'Один аккаунт Saradeeeb для всего сайта','recover'=>'Восстановление аккаунта','forgot_title'=>'Забыли пароль?','forgot_intro'=>'Введите почту, и мы отправим ссылку сроком на один час.','send_reset'=>'Отправить ссылку','back_login'=>'Вернуться ко входу','writer_eyebrow'=>'Пишите для Saradeeeb','writer_title'=>'Заявка автора','writer_intro'=>'Заявка не создает аккаунт автоматически. Команда проверит ее и после одобрения отправит ссылку для подтверждения почты и создания пароля.','full_name'=>'Полное имя','phone'=>'Телефон / WhatsApp','country'=>'Страна','city'=>'Город','topics'=>'Предпочитаемые темы','topics_ph'=>'Например: мифы, преступления, история...','experience'=>'Опыт письма','sample'=>'Ссылка на пример текста - необязательно','bio'=>'Расскажите о себе и почему хотите писать для Saradeeeb','consent'=>'Я согласен на использование этих данных для проверки заявки и связи со мной.','submit_application'=>'Отправить заявку','invalid_login'=>'Данные неверны или аккаунт еще не активирован.','register_blocked'=>'Создание аккаунтов временно заблокировано.','invalid_register'=>'Введите имя, логин не короче 3 символов и действующий адрес почты.','account_exists'=>'Почта или логин уже используются.','register_ok'=>'Аккаунт создан. Откройте письмо, чтобы создать пароль.','invalid_application'=>'Введите имя, почту, телефон и понятное описание.','application_ok'=>'Заявка сохранена. Сначала подтвердите почту.','invalid_link'=>'Ссылка подтверждения недействительна.','expired_link'=>'Ссылка недействительна или просрочена.','application_verified'=>'Почта подтверждена, команда получила заявку.','forgot_ok'=>'Если почта связана с аккаунтом, отправлена ссылка сроком на один час.','reset_title'=>'Новый пароль']),
    ];
    $result=$copy[$locale]??$en;
    $writerIntro=[
        'ar'=>'عند إرسال طلب العضوية، يتم إنشاء حسابك مباشرة بحالة انتظار الموافقة. يمكنك تأكيد بريدك الإلكتروني وتسجيل الدخول لاستكمال ملفك الشخصي ومتابعة حالة طلبك. لن تتفعّل صلاحيات الكاتب إلا بعد موافقة إدارة سراديب.',
        'en'=>'When you submit your membership application, your account is created immediately and placed in a pending approval state. You can verify your email, sign in, complete your profile, and follow your application status. Writer permissions are activated only after Saradeeeb management approves your application.',
        'es'=>'Al enviar la solicitud de membresía, tu cuenta se crea de inmediato y queda pendiente de aprobación. Puedes confirmar tu correo, iniciar sesión, completar tu perfil y consultar el estado de la solicitud. Los permisos de autor se activan solo después de la aprobación de la administración de Saradeeeb.',
        'fr'=>'Lorsque vous envoyez votre demande d’adhésion, votre compte est créé immédiatement en attente d’approbation. Vous pouvez confirmer votre e-mail, vous connecter, compléter votre profil et suivre l’état de votre demande. Les droits d’auteur ne sont activés qu’après approbation par l’administration de Saradeeeb.',
        'ru'=>'После отправки заявки на членство аккаунт создаётся сразу и получает статус ожидания одобрения. Вы можете подтвердить почту, войти, заполнить профиль и следить за статусом заявки. Права автора активируются только после одобрения администрацией Saradeeeb.',
    ];
    // Writer membership policy: email is retained for account recovery only.
    $writerIntro=[
        'ar'=>'البريد الإلكتروني إلزامي لاستعادة كلمة المرور أو تغييرها عند الحاجة فقط. يمكنك تسجيل الدخول إلى حسابك مباشرة بعد التسجيل دون الحاجة إلى تأكيد البريد الإلكتروني. جميع إشعارات العضوية وقرارات الإدارة والمراسلات تصلك داخل حسابك في سراديب، وليس عبر البريد الإلكتروني.',
        'en'=>'Email is required only for password recovery or a password change when needed. You can sign in immediately after registration without confirming your email. All membership notices, administrative decisions, and messages appear inside your Saradeeeb account, not by email.',
        'es'=>'El correo electrónico es obligatorio solo para recuperar o cambiar la contraseña cuando sea necesario. Puedes iniciar sesión inmediatamente después de registrarte sin confirmar tu correo. Todas las notificaciones de membresía, decisiones administrativas y mensajes aparecen dentro de tu cuenta de Saradeeeb, no por correo.',
        'fr'=>'L’e-mail est requis uniquement pour récupérer ou modifier le mot de passe si nécessaire. Vous pouvez vous connecter immédiatement après l’inscription sans confirmer votre e-mail. Toutes les notifications d’adhésion, décisions administratives et messages apparaissent dans votre compte Saradeeeb, et non par e-mail.',
        'ru'=>'Электронная почта требуется только для восстановления или изменения пароля при необходимости. Вы можете войти сразу после регистрации без подтверждения почты. Все уведомления о членстве, решения администрации и сообщения доступны внутри вашего аккаунта Saradeeeb, а не по электронной почте.',
    ];
    $result['writer_intro']=$writerIntro[$locale]??$writerIntro['en'];
    $result['password']=$locale==='ar'?'كلمة المرور':'Password';
    $result['password_confirmation']=$locale==='ar'?'تأكيد كلمة المرور':'Confirm password';
    if($locale==='ar'){
        $result=array_replace($result,[
            'brand'=>'سراديب','login_visual_title'=>'ادخل إلى غرفة التحرير.','login_visual_text'=>'إدارة المقالات والترجمات والكتّاب من مكان واحد.','back_site'=>'العودة إلى الموقع','forgot'=>'نسيت كلمة المرور؟','create_account'=>'إنشاء حساب','secure'=>'اتصال آمن','session'=>'جلسة محمية','member_brand'=>'منتدى سراديب','member_title'=>'حساب واحد للقراءة والنقاش.','back_community'=>'العودة إلى المنتدى','member'=>'حساب عضو','register_title'=>'إنشاء حساب سراديب','password'=>'كلمة المرور','enter'=>'دخول','no_account'=>'ليس لديك حساب؟'
        ]);
    }
    return $result;
}

function community_pagination(array $pg,string $base,array $query=[]): string {
    $pages=(int)($pg['pages']??1); $page=(int)($pg['page']??1); if($pages<=1)return '';
    $ui=community_ui(request_locale());
    $html='<nav class="community-pagination" aria-label="'.e($ui['filter_label']).'">';
    $from=max(1,$page-2); $to=min($pages,$page+2);
    if($page>1){$q=http_build_query(array_filter($query,static fn($v)=>$v!==''&&$v!==null)+['page'=>$page-1]);$html.='<a href="'.e($base.'?'.$q).'">'.e($ui['previous']).'</a>';}
    for($i=$from;$i<=$to;$i++){ $q=http_build_query(array_filter($query,static fn($v)=>$v!==''&&$v!==null)+['page'=>$i]); $html.='<a class="'.($i===$page?'active':'').'" href="'.e($base.'?'.$q).'">'.$i.'</a>'; }
    if($page<$pages){$q=http_build_query(array_filter($query,static fn($v)=>$v!==''&&$v!==null)+['page'=>$page+1]);$html.='<a href="'.e($base.'?'.$q).'">'.e($ui['next']).'</a>';}
    return $html.'</nav>';
}

function community_member_badge(array $user,?int $publishedArticles=null): string {
    $labels=[
        'ar'=>['admin'=>'الإدارة','editor'=>'مشرف','writer'=>'كاتب سراديب','member'=>'عضو'],
        'en'=>['admin'=>'Administration','editor'=>'Moderator','writer'=>'Saradeeeb writer','member'=>'Member'],
        'es'=>['admin'=>'Administracion','editor'=>'Moderador','writer'=>'Autor Saradeeeb','member'=>'Miembro'],
        'fr'=>['admin'=>'Administration','editor'=>'Moderateur','writer'=>'Auteur Saradeeeb','member'=>'Membre'],
        'ru'=>['admin'=>'Администрация','editor'=>'Модератор','writer'=>'Автор Saradeeeb','member'=>'Участник'],
    ];
    $L=$labels[request_locale()]??$labels['en'];
    $role=(string)($user['role']??'');
    if($role==='admin')return $L['admin'];
    if($role==='editor')return $L['editor'];
    if($role==='trusted_writer')return $L['writer'];
    if($publishedArticles!==null && $publishedArticles>0)return $L['writer'];
    return $L['member'];
}

function community_render_content(string $html,string $locale): string {
    $safe=sanitize_html($html);
    $parts=preg_split('/(<[^>]+>)/u',$safe,-1,PREG_SPLIT_DELIM_CAPTURE) ?: [$safe];
    foreach($parts as $i=>$part){
        if($part==='' || str_starts_with($part,'<')) continue;
        $parts[$i]=preg_replace_callback('/(?<![\p{L}\p{N}_])@([\p{L}\p{N}_.-]{2,120})/u',static function($m)use($locale){
            return '<a class="community-mention" href="'.e(locale_url($locale,'community/member/'.$m[1])).'">@'.e($m[1]).'</a>';
        },$part) ?? $part;
    }
    return implode('',$parts);
}

function community_unread_notifications(?int $userId=null): int {
    $u=auth_user(); $id=$userId?:((int)($u['id']??0)); if(!$id)return 0;
    try{return (int)(Database::one("SELECT COUNT(*) c FROM notifications WHERE user_id=? AND is_read=0 AND type LIKE 'community_%'",[$id])['c']??0);}catch(Throwable){return 0;}
}

/** Public navigation items controlled from the administration panel. */
function site_navigation_catalog(string $locale): array {
    $navLabel = static function(string $key, string $fallback) use ($locale): string {
        $fallbacks = [
            'story' => ['ar'=>'أرسل قصتك','en'=>'Send your story','es'=>'Envía tu historia','fr'=>'Envoyez votre histoire','ru'=>'Отправить историю'],
            'community' => ['ar'=>'منتدى سراديب','en'=>'Saradeeeb community','es'=>'Comunidad Saradeeeb','fr'=>'Communauté Saradeeeb','ru'=>'Сообщество Saradeeeb'],
            'podcast' => ['ar'=>'بودكاست سراديب','en'=>'Saradeeeb podcast','es'=>'Podcast Saradeeeb','fr'=>'Podcast Saradeeeb','ru'=>'Подкаст Saradeeeb'],
            'khawatir' => ['ar'=>'خواطر','en'=>'Reflections','es'=>'Reflexiones','fr'=>'Réflexions','ru'=>'Размышления'],
            'behind' => ['ar'=>'من خلف سراديب','en'=>'About Saradeeeb','es'=>'Acerca de Saradeeeb','fr'=>'À propos de Saradeeeb','ru'=>'О Saradeeeb'],
        ];
        $fallbacks['contact']=['ar'=>'اتصل بنا','en'=>'Contact us','es'=>'Contáctanos','fr'=>'Contactez-nous','ru'=>'Связаться с нами'];
        $label=translation_text('nav', $key, $locale, $fallbacks[$key][$locale] ?? $fallback);
        // Keep existing custom labels, but update the legacy Arabic About wording.
        return $key==='behind' && $locale==='ar' && trim($label)==='عن سراديب' ? 'من خلف سراديب' : $label;
    };
    $items = [
        'home' => ['label'=>tr('home',$locale),'url'=>locale_url($locale),'icon'=>'home','target'=>'_self'],
        'explore' => ['label'=>tr('explore',$locale),'url'=>locale_url($locale,'search'),'icon'=>'compass','target'=>'_self'],
        'write' => ['label'=>tr('write',$locale),'url'=>locale_url($locale,'writer-application'),'icon'=>'edit','target'=>'_self'],
        'story' => ['label'=>$navLabel('story','أرسل قصتك'),'url'=>locale_url($locale,'send-story'),'icon'=>'edit','target'=>'_self'],
        'categories' => ['label'=>tr('sections',$locale),'url'=>locale_url($locale).'#categories-showcase','icon'=>'grid','target'=>'_self'],
        'community' => ['label'=>$navLabel('community','Saradeeeb community'),'url'=>locale_url($locale,'community'),'icon'=>'community','target'=>'_self'],
        'podcast' => ['label'=>$navLabel('podcast','بودكاست سراديب'),'url'=>locale_url($locale,'podcast'),'icon'=>'podcast','target'=>'_self'],
        'khawatir' => ['label'=>$navLabel('khawatir','خواطر'),'url'=>locale_url($locale,'khawatir'),'icon'=>'quote','target'=>'_self'],
        // Resolve the editable page by identity so renamed slugs stay linked.
        'behind' => ['label'=>$navLabel('behind','من خلف سراديب'),'url'=>\App\Services\BehindPage::url($locale),'icon'=>'book','target'=>'_self'],
        'contact' => ['label'=>$navLabel('contact','اتصل بنا'),'url'=>locale_url($locale,'contact'),'icon'=>'mail','target'=>'_self'],
        'login' => ['label'=>tr('login',$locale),'url'=>locale_url($locale,'login'),'icon'=>'login','target'=>'_self'],
        'dashboard' => ['label'=>tr('dashboard',$locale),'url'=>is_editor()?url('/admin'):(((auth_user()['role']??'')==='member')?locale_url($locale,'community/member/'.(auth_user()['username']??'')):url('/writer')),'icon'=>'dashboard','target'=>'_self'],
    ];
    try {
        $pages=Database::all("SELECT p.id,pt.title,pt.slug FROM pages p JOIN page_translations pt ON pt.page_id=p.id AND pt.locale=? WHERE p.status='published' AND p.deleted_at IS NULL AND ".page_translation_publishable_sql('pt')." ORDER BY p.sort_order,p.id",[$locale]);
        foreach($pages as $page){
            $items['page:'.$page['id']]=['label'=>$page['title'],'url'=>\App\Services\ManagedPages::url((int)$page['id'],$locale,$page['slug']),'icon'=>'page','target'=>'_self'];
        }
    } catch (Throwable) {}

    foreach(\App\Services\ManagedPages::ids() as $key=>$id){
        $managed=\App\Services\ManagedPages::forKey($key,$locale);
        unset($items['page:'.$id]);
        if(!$managed || $key==='newsletter'){ unset($items[$key]); continue; }
        $items[$key]=['label'=>$managed['title'],'url'=>\App\Services\ManagedPages::url((int)$id,$locale,$managed['slug']),'icon'=>$key==='community'?'community':($key==='explore'?'compass':'edit'),'target'=>'_self','page_id'=>(int)$id];
    }
    if ($items['behind']['url']==='') unset($items['behind']);
    else { $items['behind']['page_id']=\App\Services\BehindPage::id(); unset($items['page:'.\App\Services\BehindPage::id()]); }
    foreach($items as $key=>&$item) if(str_starts_with($key,'page:')) $item['page_id']=(int)substr($key,5);
    unset($item);
    $customRaw=setting('custom_navigation_json','');
    $custom=json_decode($customRaw,true);
    if(is_array($custom)){
        foreach($custom as $item){
            if(!is_array($item)) continue;
            $id=preg_replace('/[^A-Za-z0-9_-]/','',(string)($item['id']??'')) ?: '';
            $label=trim((string)($item['label']??''));
            $labels=is_array($item['labels']??null)?$item['labels']:[];
            $localizedLabel=trim((string)($labels[$locale]??''));
            if($localizedLabel==='' && $locale==='ar') $localizedLabel=$label;
            $href=trim((string)($item['url']??''));
            if($id==='' || $localizedLabel==='' || $href==='') continue;
            if(!preg_match('~^(?:https?://|/(?!/)|#|mailto:|tel:)~i',$href)) $href='/'.ltrim($href,'/');
            $href=\App\Services\ManagedPages::navigationUrl($href,$locale);
            if($href===null || $href==='') continue;
            $items['custom:'.$id]=[
                'label'=>$localizedLabel,
                'url'=>$href,
                'icon'=>(string)($item['icon']??'link'),
                'target'=>!empty($item['new_tab'])?'_blank':'_self',
                'custom'=>true,
                'display'=>(string)($item['display']??'both'),
                'style_type'=>(string)($item['style_type']??'link'),
                'bg'=>(string)($item['bg']??'#157176'),
                'text_color'=>(string)($item['text_color']??'#ffffff'),
                'hover_bg'=>(string)($item['hover_bg']??'#0f5f63'),
                'border_color'=>(string)($item['border_color']??'#157176'),
                'radius'=>(int)($item['radius']??10),
                'size'=>(string)($item['size']??'md'),
            ];
        }
    }

    // Footer Studio owns these links independently from the global navigation
    // builder, so an administrator can curate the footer without replacing
    // header or mobile-menu custom items.
    $footerCustom=json_decode(setting('footer_custom_links_json',''),true);
    if(is_array($footerCustom)){
        foreach($footerCustom as $item){
            if(!is_array($item)) continue;
            $id=preg_replace('/[^A-Za-z0-9_-]/','',(string)($item['id']??'')) ?: '';
            $label=trim((string)($item['label']??''));
            $labels=is_array($item['labels']??null)?$item['labels']:[];
            $localizedLabel=trim((string)($labels[$locale]??''));
            if($localizedLabel==='' && $locale==='ar') $localizedLabel=$label;
            $href=trim((string)($item['url']??''));
            if($id==='' || $localizedLabel==='' || $href==='') continue;
            if(!preg_match('~^(?:https?://|/(?!/)|#|mailto:|tel:)~i',$href)) $href='/'.ltrim($href,'/');
            $href=\App\Services\ManagedPages::navigationUrl($href,$locale);
            if($href===null || $href==='') continue;
            $items['footer_custom:'.$id]=[
                'column'=>in_array($item['column']??'menu',['brand','menu','connect'],true)?($item['column']??'menu'):'menu',
                'order'=>(int)($item['order']??0),
                'label'=>$localizedLabel,
                'url'=>$href,
                'icon'=>(string)($item['icon']??'link'),
                'target'=>!empty($item['new_tab'])?'_blank':'_self',
                'custom'=>true,
                'display'=>'both',
                'style_type'=>in_array((string)($item['style_type']??'link'),['link','button'],true)?(string)$item['style_type']:'link',
                'bg'=>(string)($item['bg']??'#157176'),
                'text_color'=>(string)($item['text_color']??'#ffffff'),
                'hover_bg'=>(string)($item['hover_bg']??'#0f5f63'),
                'border_color'=>(string)($item['border_color']??'#157176'),
                'radius'=>max(0,min(40,(int)($item['radius']??10))),
                'size'=>in_array((string)($item['size']??'md'),['sm','md','lg'],true)?(string)$item['size']:'md',
            ];
        }
    }

    $icons=json_decode(setting('nav_icon_overrides_json',''),true);
    if(is_array($icons)){
        foreach($icons as $key=>$icon) if(isset($items[$key]) && preg_match('/^[a-z0-9_-]+$/i',(string)$icon)) $items[$key]['icon']=(string)$icon;
    }
    return $items;
}

/** One public gate shared by page routes, menus, alternates and sitemap. */
function page_translation_publishable_sql(string $alias='pt'): string {
    if(!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/',$alias)) throw new InvalidArgumentException('Invalid alias');
    return "$alias.status='published' AND $alias.review_state='approved' AND TRIM($alias.title)<>'' AND TRIM($alias.slug)<>'' AND TRIM($alias.content)<>'' AND EXISTS (SELECT 1 FROM pages source_page JOIN page_translations source_ar ON source_ar.page_id=source_page.id AND source_ar.locale='ar' WHERE source_page.id=$alias.page_id AND source_page.status='published' AND source_page.deleted_at IS NULL AND source_ar.status='published' AND source_ar.review_state='approved' AND TRIM(source_ar.title)<>'' AND TRIM(source_ar.slug)<>'' AND TRIM(source_ar.content)<>'')";
}

function configured_navigation(string $locale,string $location='header'): array {
    $catalog=site_navigation_catalog($locale);
    $default=$location==='footer'?['story','explore','community','behind','contact']:['home','categories','podcast','khawatir','community','story'];
    $raw=setting($location.'_menu_json','');
    $keys=$default;
    if($raw!==''){
        $decoded=json_decode($raw,true);
        if(is_array($decoded)) $keys=array_values(array_filter($decoded,'is_string'));
    }
    $out=[];
    $user=auth_user();
    foreach($keys as $key){
        if(!isset($catalog[$key]) && str_starts_with($key,'page:')){
            $alias=\App\Services\ManagedPages::key((int)substr($key,5));
            if($alias!==null) $key=$alias;
        }
        if(!isset($catalog[$key])) continue;
        // Podcast and reflections are Arabic-only editorial products.
        if($locale!=='ar' && in_array($key,['podcast','khawatir'],true)) continue;
        $visibility=json_decode(setting('nav_locale_visibility_json','{}'),true);
        if(is_array($visibility) && isset($visibility[$key]) && is_array($visibility[$key]) && !in_array($locale,$visibility[$key],true)) continue;
        if($key==='dashboard' && !$user) continue;
        if($key==='login' && $user) continue;
        $item=$catalog[$key];
        if(!empty($item['custom'])){
            $display=(string)($item['display']??'both');
            if($location==='mobile' && $display==='desktop') continue;
            if($location!=='mobile' && $display==='mobile') continue;
        }
        if($location==='footer' && isset($item['page_id'])){
            $placement=\App\Services\ManagedPages::placement((int)$item['page_id']);
            $item['column']=$placement['column']; $item['order']=(int)$placement['order'];
            $label=trim((string)($placement['labels'][$locale]??''));
            if($label!=='') $item['label']=$key==='behind' && $locale==='ar' && $label==='عن سراديب' ? 'من خلف سراديب' : $label;
        }
        $item['key']=$key;
        $out[]=$item;
    }
    if($location==='footer') usort($out,static fn($a,$b)=>($a['order']??0)<=>($b['order']??0));
    return $out;
}

function navigation_icon_svg(string $name): string {
    $icons=[
        'home'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 10.5 12 3l8.5 7.5v9a1.5 1.5 0 0 1-1.5 1.5h-4.7v-6H9.7v6H5a1.5 1.5 0 0 1-1.5-1.5v-9Z"/></svg>',
        'compass'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2.1 4.9-4.9 2.1 2.1-4.9 4.9-2.1Z"/></svg>',
        'edit'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l11-11-4-4L4 16v4Zm9.7-13.7 4 4M5 4h6"/></svg>',
        'grid'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>',
        'community'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.5 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm9 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM2.5 20v-1.5A4.5 4.5 0 0 1 7 14h1a4.5 4.5 0 0 1 4.5 4.5V20m1.5 0v-1a4 4 0 0 1 4-4h.5a3 3 0 0 1 3 3v2"/></svg>',
        'bell'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4"/></svg>',
        'user'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>',
        'login'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5M14 8l4 4-4 4m4-4H8"/></svg>',
        'dashboard'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 13h7V4H4v9Zm9 7h7v-7h-7v7Zm0-9h7V4h-7v7ZM4 20h7v-5H4v5Z"/></svg>',
        'page'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm8 1.5V8h3.5M9 12h6M9 16h6"/></svg>',
        'link'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.1.1l2-2a5 5 0 0 0-7.1-7.1l-1.1 1.1M14 11a5 5 0 0 0-7.1-.1l-2 2A5 5 0 0 0 12 20l1.1-1.1"/></svg>',
        'star'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.8 1-6.1-4.4-4.3 6.1-.9L12 3Z"/></svg>',
        'book'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5A3.5 3.5 0 0 1 7.5 2H11v17H7.5A3.5 3.5 0 0 0 4 22V5.5ZM20 5.5A3.5 3.5 0 0 0 16.5 2H13v17h3.5A3.5 3.5 0 0 1 20 22V5.5Z"/></svg>',
        'podcast'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 12a5 5 0 0 1 10 0v4a5 5 0 0 1-10 0v-4Zm-3 3v-3a8 8 0 0 1 16 0v3M10 21h4M12 17v4M7 15H5a2 2 0 0 1-2-2v-1M17 15h2a2 2 0 0 0 2-2v-1"/></svg>',
        'quote'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.5 6H5.8A2.8 2.8 0 0 0 3 8.8v3.7h5.5V18H3.8M21 6h-3.7a2.8 2.8 0 0 0-2.8 2.8v3.7H20V18h-4.7"/></svg>',
        'play'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7L8 5Z"/></svg>',
        'pause'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 5h4v14H7V5Zm6 0h4v14h-4V5Z"/></svg>',
        'info'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 10v7M12 7h.01"/></svg>',
        'mail'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>',
        'search'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4"/></svg>',
        'sun'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>',
        'moon'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 15.2A8.2 8.2 0 0 1 8.8 4a8.5 8.5 0 1 0 11.2 11.2Z"/></svg>',
        'menu'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>',
        'arrow-up'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg>',
        'facebook'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 8h3V4h-3c-3 0-5 2-5 5v2H6v4h3v6h4v-6h3l1-4h-4V9c0-.7.3-1 1-1Z"/></svg>',
        'instagram'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg>',
        'x'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h4.2l3.6 5L17 4h2l-5.2 6.5L20 20h-4.2l-4.1-5.7L7 20H5l5.7-7.2L5 4Z"/></svg>',
        'youtube'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 8.2a3 3 0 0 0-2.1-2.1C17 5.6 12 5.6 12 5.6s-5 0-6.9.5A3 3 0 0 0 3 8.2 31 31 0 0 0 2.6 12 31 31 0 0 0 3 15.8a3 3 0 0 0 2.1 2.1c1.9.5 6.9.5 6.9.5s5 0 6.9-.5a3 3 0 0 0 2.1-2.1 31 31 0 0 0 .4-3.8 31 31 0 0 0-.4-3.8Z"/><path d="m10 9 5 3-5 3V9Z"/></svg>',
        'telegram'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 4-3 16-6-4-3 3-1-5-5-2 18-8Z"/><path d="m8 14 9-7"/></svg>',
        'linkedin'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="9" width="4" height="11"/><path d="M6 4.5v.01"/><path d="M12 20V9h4v2c1-1.7 5-2.3 5 3.2V20h-4v-5c0-2.4-2-2.2-2-2.2-1.4 0-3 .9-3 3.2V20h-4"/></svg>',
        'whatsapp'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.5 11.7a8.5 8.5 0 0 1-12.6 7.5L4 20l.9-3.7a8.5 8.5 0 1 1 15.6-4.6Z"/><path d="M8.4 7.8c.4-.8.8-.8 1.2-.8h.4c.2 0 .4 0 .6.5l.8 1.9c.1.3.1.5-.1.8l-.7.8c-.2.2-.2.4 0 .7.6 1.1 1.5 2 2.6 2.6.3.2.5.2.7 0l.9-1c.2-.3.5-.3.8-.2l1.9.9c.3.1.5.3.5.6 0 .7-.3 1.5-.8 2-1 .9-2.2 1.2-3.6.8-1.8-.5-3.8-1.6-5.4-3.4-1.3-1.5-2.2-3.2-2.4-4.6-.2-1 .1-1.8.6-2.6Z"/></svg>',
        'tiktok'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 4v10.2a4.2 4.2 0 1 1-3.4-4.1"/><path d="M14 4c.7 2.2 2.3 3.7 4.6 4"/></svg>',
        'share'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/><path d="m8.2 10.8 7.6-4.5M8.2 13.2l7.6 4.5"/></svg>',
        'copy'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="11" height="11" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg>',
        'text'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5h14M12 5v14M8 19h8"/></svg>',
        'chevron-down'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>',
        'close'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>',
    ];
    return $icons[$name]??$icons['link'];
}

function navigation_icon_names(): array {
    return ['home'=>'الرئيسية','compass'=>'استكشاف','edit'=>'كتابة','grid'=>'شبكة','community'=>'منتدى','podcast'=>'بودكاست','quote'=>'خواطر','user'=>'حساب','login'=>'دخول','dashboard'=>'لوحة','page'=>'صفحة','link'=>'رابط','star'=>'نجمة','book'=>'كتاب','info'=>'معلومات','mail'=>'بريد','facebook'=>'Facebook','instagram'=>'Instagram','x'=>'X','tiktok'=>'TikTok','youtube'=>'YouTube','telegram'=>'Telegram','linkedin'=>'LinkedIn','whatsapp'=>'WhatsApp'];
}

function footer_social_links(): array { return site_social_links('footer'); }


/** Create a compact, readable sidebar summary from the article excerpt/content. */
function article_summary_points(string $excerpt,string $html,int $limit=4): array {
    $limit=max(2,min(6,$limit));
    $source=trim(strip_tags($excerpt));
    if($source===''){
        $clean=preg_replace('/<(script|style)[^>]*>.*?<\/\1>/isu',' ', $html) ?? $html;
        $source=trim(html_entity_decode(strip_tags($clean),ENT_QUOTES|ENT_HTML5,'UTF-8'));
    }
    $source=preg_replace('/\s+/u',' ',$source) ?? $source;
    if($source==='') return [];
    $sentences=preg_split('/(?<=[.!؟!?])\s+/u',$source,-1,PREG_SPLIT_NO_EMPTY) ?: [];
    if(count($sentences)<2){
        $sentences=preg_split('/[؛;\n]+/u',$source,-1,PREG_SPLIT_NO_EMPTY) ?: [$source];
    }
    $out=[];
    foreach($sentences as $sentence){
        $sentence=trim($sentence," \t\n\r\0\x0B-–—•");
        if(mb_strlen($sentence,'UTF-8')<18) continue;
        if(mb_strlen($sentence,'UTF-8')>150) $sentence=mb_substr($sentence,0,147,'UTF-8').'…';
        if(!in_array($sentence,$out,true)) $out[]=$sentence;
        if(count($out)>=$limit) break;
    }
    if(!$out){
        $out[] = mb_strlen($source,'UTF-8')>150 ? mb_substr($source,0,147,'UTF-8').'…' : $source;
    }
    return $out;
}



/** WhatsApp Contact module defaults migrated from the user's WordPress plugin. */
function whatsapp_default_texts(): array {
    return [
        'ar'=>['title'=>'تواصل معنا عبر واتساب','subtitle'=>'اختر جهة الاتصال المناسبة ثم اكتب رسالتك.','choose'=>'اختر جهة الاتصال','message_label'=>'رسالتك','placeholder'=>'مرحبًا، أريد الاستفسار.','send'=>'إرسال عبر واتساب','cancel'=>'إلغاء','close'=>'إغلاق النافذة','error_contact'=>'اختر جهة الاتصال أولًا.','error_message'=>'اكتب رسالتك أولًا.','source_heading'=>'الصفحة التي أرسل منها الزائر:','source_title'=>'العنوان:','source_url'=>'الرابط:'],
        'en'=>['title'=>'Contact us on WhatsApp','subtitle'=>'Choose the right contact, then write your message.','choose'=>'Choose a contact','message_label'=>'Your message','placeholder'=>'Hello, I would like to ask for more information.','send'=>'Send via WhatsApp','cancel'=>'Cancel','close'=>'Close window','error_contact'=>'Please choose a contact first.','error_message'=>'Please write your message first.','source_heading'=>'Visitor page source:','source_title'=>'Title:','source_url'=>'URL:'],
        'es'=>['title'=>'Contáctanos por WhatsApp','subtitle'=>'Elige el contacto adecuado y escribe tu mensaje.','choose'=>'Elige un contacto','message_label'=>'Tu mensaje','placeholder'=>'Hola, me gustaría solicitar más información.','send'=>'Enviar por WhatsApp','cancel'=>'Cancelar','close'=>'Cerrar ventana','error_contact'=>'Elige un contacto primero.','error_message'=>'Escribe tu mensaje primero.','source_heading'=>'Página de origen del visitante:','source_title'=>'Título:','source_url'=>'URL:'],
        'ru'=>['title'=>'Свяжитесь с нами в WhatsApp','subtitle'=>'Выберите контакт и напишите сообщение.','choose'=>'Выберите контакт','message_label'=>'Ваше сообщение','placeholder'=>'Здравствуйте, я хотел бы узнать подробнее.','send'=>'Отправить в WhatsApp','cancel'=>'Отмена','close'=>'Закрыть окно','error_contact'=>'Сначала выберите контакт.','error_message'=>'Сначала напишите сообщение.','source_heading'=>'Страница, с которой написал посетитель:','source_title'=>'Заголовок:','source_url'=>'Ссылка:'],
        'fr'=>['title'=>'Contactez-nous sur WhatsApp','subtitle'=>'Choisissez un contact puis écrivez votre message.','choose'=>'Choisissez un contact','message_label'=>'Votre message','placeholder'=>'Bonjour, je souhaite obtenir plus d’informations.','send'=>'Envoyer via WhatsApp','cancel'=>'Annuler','close'=>'Fermer la fenêtre','error_contact'=>'Veuillez choisir un contact.','error_message'=>'Veuillez écrire votre message.','source_heading'=>'Page depuis laquelle le visiteur écrit :','source_title'=>'Titre :','source_url'=>'Lien :'],
    ];
}

function whatsapp_frontend_config(): ?array {
    if(setting('wa_enabled','1')!=='1') return null;
    $contacts=[];
    foreach(range(1,4) as $i){
        if(setting('wa_contact_'.$i.'_enabled',$i===1?'1':'0')!=='1') continue;
        $phone=preg_replace('/[^0-9]/','',setting('wa_contact_'.$i.'_phone',$i===1?'905413929436':''));
        if(!$phone) continue;
        $roles=[];
        foreach(array_keys(whatsapp_default_texts()) as $lang){ $roles[$lang]=setting('wa_contact_'.$i.'_role_'.$lang,$lang==='ar'?'قسم التواصل':'Contact'); }
        $contactName=setting('wa_contact_'.$i.'_name',$i===1?'فريق سراديب':'Contact '.$i);
        if(request_locale()!=='ar'&&preg_match('/[\x{0600}-\x{06FF}]/u',$contactName))$contactName=$i===1?'Saradeeeb team':'Contact '.$i;
        $contacts[]=['id'=>'contact-'.$i,'name'=>$contactName,'phone'=>$phone,'roles'=>$roles];
    }
    if(!$contacts) return null;
    $texts=[];
    foreach(whatsapp_default_texts() as $lang=>$rows){
        foreach($rows as $key=>$fallback) $texts[$lang][$key]=setting('wa_text_'.$lang.'_'.$key,$fallback);
    }
    $logo=setting('wa_logo',''); if($logo==='') $logo=setting('brand_logo','');
    $num=fn(string $k,int $d,int $min,int $max)=>max($min,min($max,(int)(setting($k,(string)$d)?:$d)));
    return [
        'contacts'=>$contacts,'texts'=>$texts,'brandName'=>request_locale()==='ar'?setting('wa_brand_name',setting('site_name','سراديب')):'Saradeeeb',
        'logoUrl'=>$logo,'showLogo'=>setting('wa_show_logo','1')==='1','logoWidth'=>$num('wa_logo_width',220,80,420),
        'position'=>'right','buttonDesktop'=>$num('wa_button_desktop',64,44,110),'buttonMobile'=>$num('wa_button_mobile',58,44,90),
        'bottomDesktop'=>$num('wa_bottom_desktop',28,0,400),'sideDesktop'=>$num('wa_side_desktop',28,0,400),'bottomMobile'=>$num('wa_bottom_mobile',18,0,200),'sideMobile'=>$num('wa_side_mobile',16,0,200),
        'showMobile'=>setting('wa_show_mobile','1')==='1','showDesktop'=>setting('wa_show_desktop','1')==='1','floatAnimation'=>setting('wa_float_animation','1')==='1','rippleAnimation'=>setting('wa_ripple_animation','1')==='1',
        'openNewTab'=>setting('wa_open_new_tab','1')==='1','includePageContext'=>setting('wa_include_context','1')==='1',
        'primaryColor'=>setting('wa_primary_color',setting('brand_color','#157176')),'secondaryColor'=>setting('wa_secondary_color','#2a969b'),'iconColor'=>setting('wa_icon_color','#ffffff'),'rippleColor'=>setting('wa_ripple_color',setting('brand_color','#157176')),
        'modalBgColor'=>setting('wa_modal_bg','#ffffff'),'modalTextColor'=>setting('wa_modal_text','#173136'),'mutedTextColor'=>setting('wa_muted_text','#61767a'),'logoBackground'=>setting('wa_logo_bg','#ffffff'),
        'modalWidth'=>$num('wa_modal_width',500,320,760),'modalRadius'=>$num('wa_modal_radius',20,0,40),'languageSource'=>'auto','defaultLanguage'=>default_locale(),'storageKey'=>'saradeeeb_language'
    ];
}

function site_social_links(?string $location=null): array {
    $networks=[
        'facebook'=>'Facebook','instagram'=>'Instagram','x'=>'X','tiktok'=>'TikTok','youtube'=>'YouTube','telegram'=>'Telegram','linkedin'=>'LinkedIn','whatsapp'=>'WhatsApp'
    ];
    $out=[];
    foreach($networks as $key=>$label){
        $fallback=setting('footer_social_'.$key,'');
        $href=trim(setting('social_'.$key.'_url',$fallback));
        if($href==='') continue;
        if(setting('social_'.$key.'_enabled','1')!=='1') continue;
        if($location && setting('social_'.$key.'_'.$location,$location==='footer'?'1':'0')!=='1') continue;
        $out[]=['key'=>$key,'label'=>$label,'url'=>$href];
    }
    return $out;
}


